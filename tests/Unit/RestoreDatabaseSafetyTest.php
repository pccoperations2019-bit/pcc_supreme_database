<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/deployment/RestoreDatabase.Common.php';

final class RestoreDatabaseSafetyTest extends TestCase
{
    private const DIRECTORY = 'C:/ProgramData/SUPREME-Backups/restore-test-0123456789abcdef0123456789abcdef';

    public function test_verified_isolated_identity_and_windows_path_spelling_are_accepted(): void
    {
        $identity = $this->identity();
        $identity['data_directory'] = str_replace('/', '\\', strtolower(self::DIRECTORY)).'\\data\\';
        assertRestoreTestIdentity($identity, self::DIRECTORY);
        $this->addToAssertionCount(1);
    }

    public function test_live_database_and_incorrect_isolation_settings_are_rejected(): void
    {
        foreach ([
            'version' => '8.0.45', 'port' => 3306, 'authenticated_account' => 'root@localhost',
            'data_directory' => 'C:/laragon/data/mysql', 'bind_address' => '0.0.0.0',
            'event_scheduler' => 'ON', 'local_infile' => 1, 'secure_file_priv' => '',
            'log_bin' => 1, 'named_pipe' => 1, 'shared_memory' => 1,
        ] as $key => $value) {
            $identity = $this->identity();
            $identity[$key] = $value;
            $this->assertSame([$key], findRestoreTestIdentityMismatches($identity, self::DIRECTORY));
            $rejected = false;
            try {
                assertRestoreTestIdentity($identity, self::DIRECTORY);
            } catch (RuntimeException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'Unexpected server identity accepted: '.$key);
        }
    }

    public function test_paths_outside_fresh_restore_workspace_are_rejected(): void
    {
        foreach (['C:/laragon/www/supreme-cbed-poc', self::DIRECTORY.'/../live', self::DIRECTORY.'/data', 'D:/ProgramData/SUPREME-Backups/restore-test-'.str_repeat('a', 32)] as $directory) {
            $rejected = false;
            try {
                assertRestoreTestDirectory($directory);
            } catch (RuntimeException) {
                $rejected = true;
            }
            $this->assertTrue($rejected);
        }
    }

    private function identity(): array
    {
        return [
            'version' => '8.0.40', 'port' => 13306, 'authenticated_account' => 'root@127.0.0.1', 'data_directory' => self::DIRECTORY.'/data/',
            'bind_address' => '127.0.0.1', 'event_scheduler' => 'DISABLED', 'local_infile' => 0,
            'secure_file_priv' => null, 'log_bin' => 0, 'named_pipe' => 0, 'shared_memory' => 0,
        ];
    }

    public function test_subprocess_exit_status_is_preserved(): void
    {
        $process = $this->startFixture('exit(7);');
        try {
            $this->assertSame(7, waitForRestoreProcess($process, 5));
        } finally {
            proc_close($process);
        }
    }

    public function test_hung_subprocess_hits_timeout_without_reporting_success(): void
    {
        $process = $this->startFixture('sleep(10);');
        $timedOut = false;
        try {
            waitForRestoreProcess($process, 0);
        } catch (RuntimeException) {
            $timedOut = true;
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
        $this->assertTrue($timedOut);
    }

    public function test_child_cleanup_failure_does_not_skip_server_cleanup_or_reporting(): void
    {
        $report = [];
        $exitCode = 0;
        $serverCleanupAttempted = false;
        runRestoreCleanupSteps([
            'childCleanupNeedsAttention' => static function (): void {
                throw new RuntimeException('Injected cleanup failure');
            },
            'testServerNeedsAdministratorAttention' => static function () use (&$serverCleanupAttempted): void {
                $serverCleanupAttempted = true;
            },
        ], $report, $exitCode);
        $this->assertTrue($serverCleanupAttempted);
        $this->assertSame(1, $exitCode);
        $this->assertSame(['childCleanupNeedsAttention' => true], $report);
        $this->assertSame('{"childCleanupNeedsAttention":true}', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_connection_diagnostic_excludes_message_and_credentials(): void
    {
        $error = new PDOException('Password secret-value');
        $error->errorInfo = ['HY000', 1045, 'Password secret-value'];
        $this->assertSame(['sqlState' => 'HY000', 'driverCode' => 1045], restoreConnectionErrorCodes($error));
        $error->errorInfo = ['secret-value', 'secret-value', 'secret-value'];
        $this->assertSame(['sqlState' => null, 'driverCode' => null], restoreConnectionErrorCodes($error));
    }

    private function startFixture(string $code): mixed
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, null, null, ['bypass_shell' => true]);
        $this->assertIsResource($process);

        return $process;
    }
}

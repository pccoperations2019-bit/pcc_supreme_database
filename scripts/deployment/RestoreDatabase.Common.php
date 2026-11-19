<?php

declare(strict_types=1);

function restoreConnectionErrorCodes(PDOException $error): array
{
    // Never include the driver message: it may contain credentials or SQL.
    $state = $error->errorInfo[0] ?? null;
    $code = $error->errorInfo[1] ?? null;

    return [
        'sqlState' => is_string($state) && preg_match('/\A[A-Z0-9]{5}\z/', $state) ? $state : null,
        'driverCode' => is_int($code) && $code >= 0 && $code <= 65535 ? $code : null,
    ];
}

function assertRestoreTestDirectory(string $directory): void
{
    if (! preg_match('~\AC:/ProgramData/SUPREME-Backups/restore-test-[0-9a-f]{32}\z~i', $directory)) {
        throw new RuntimeException('Unexpected restore-test directory.');
    }
}

function findRestoreTestIdentityMismatches(array $identity, string $directory): array
{
    assertRestoreTestDirectory($directory);
    $dataPath = strtolower(rtrim(str_replace('\\', '/', (string) ($identity['data_directory'] ?? '')), '/'));
    $checks = [
        'version' => ($identity['version'] ?? null) === '8.0.40',
        'port' => (string) ($identity['port'] ?? '') === '13306',
        'authenticated_account' => ($identity['authenticated_account'] ?? null) === 'root@127.0.0.1',
        'data_directory' => $dataPath === strtolower($directory.'/data'),
        'bind_address' => ($identity['bind_address'] ?? null) === '127.0.0.1',
        'event_scheduler' => ($identity['event_scheduler'] ?? null) === 'DISABLED',
        'local_infile' => (string) ($identity['local_infile'] ?? '') === '0',
        'secure_file_priv' => array_key_exists('secure_file_priv', $identity) && $identity['secure_file_priv'] === null,
        'log_bin' => (string) ($identity['log_bin'] ?? '') === '0',
        'named_pipe' => (string) ($identity['named_pipe'] ?? '') === '0',
        'shared_memory' => (string) ($identity['shared_memory'] ?? '') === '0',
    ];

    return array_keys(array_filter($checks, static fn (bool $matched): bool => ! $matched));
}

function assertRestoreTestIdentity(array $identity, string $directory): void
{
    $mismatches = findRestoreTestIdentityMismatches($identity, $directory);
    if ($mismatches !== []) {
        throw new RuntimeException('Connection is not the isolated restore instance.');
    }
}

function waitForRestoreProcess(mixed $process, int $timeoutSeconds): int
{
    $deadline = microtime(true) + $timeoutSeconds;
    do {
        $state = proc_get_status($process);
        if (! $state['running']) {
            return $state['exitcode'];
        }
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Restore-test subprocess timed out.');
        }
        usleep(250000);
    } while (true);
}

// A cleanup failure must not prevent attempts to stop the other owned process.
function runRestoreCleanupSteps(array $steps, array &$report, int &$exitCode): void
{
    foreach ($steps as $failureFlag => $operation) {
        try {
            $operation();
        } catch (Throwable) {
            $report[$failureFlag] = true;
            $exitCode = 1;
        }
    }
}

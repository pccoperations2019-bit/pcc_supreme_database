<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('Runtime warning during isolated restoration.');
});
require __DIR__.'/RestoreDatabase.Common.php';

$directory = str_replace('\\', '/', __DIR__);
$phase = 'validate-isolated-target';
$server = null;
$child = null;
$pdo = null;
$identityVerified = false;
$stopped = true;
$workspaceAccepted = false;
$report = ['status' => 'failed', 'restoreVerified' => false, 'applicationRecoveryVerified' => false, 'activationAllowed' => false];
$exitCode = 1;
try {
    $connectionOnly = count($argv) === 2 && $argv[1] === '--connection-only';
    if (PHP_OS_FAMILY !== 'Windows' || PHP_VERSION !== '8.4.27' || (count($argv) !== 1 && ! $connectionOnly)) {
        throw new RuntimeException('Unexpected runtime or invocation.');
    }
    assertRestoreTestDirectory($directory);
    $backup = 'C:/ProgramData/SUPREME-Backups/database-20261119T063634Z-d27d18a1102c4a978e5d3ae5158d9eff/pcc.sql';
    $backupHash = '94466dbf798dfb9f405a81254b33e8ba43f0482c6b36599beba9dc297bd20126';
    if (hash_file('sha256', $backup) !== $backupHash) {
        throw new RuntimeException('Backup hash mismatch.');
    }
    foreach (['data', 'tmp', 'server.ini', 'client.cnf', 'init.sql', 'restore-report.json', 'unused-login.cnf'] as $name) {
        if (file_exists($directory.'/'.$name)) {
            throw new RuntimeException('Refusing to reuse a restore workspace.');
        }
    }
    $workspaceAccepted = true;
    $base = 'C:/laragon/bin/mysql/mysql-8.0.40-winx64';
    $childEnvironment = getenv();
    foreach (array_keys($childEnvironment) as $name) {
        if (str_starts_with(strtoupper($name), 'MYSQL')) {
            unset($childEnvironment[$name]);
        }
    }
    $childEnvironment['MYSQL_TEST_LOGIN_FILE'] = $directory.'/unused-login.cnf';
    $start = static function (array $command, string $label, string $input = 'NUL') use ($directory, $childEnvironment): mixed {
        $process = proc_open($command, [0 => ['file', $input, 'r'], 1 => ['file', $directory.'/'.$label.'.stdout.log', 'x'], 2 => ['file', $directory.'/'.$label.'.stderr.log', 'x']], $pipes, $directory, $childEnvironment, ['bypass_shell' => true, 'create_new_console' => false]);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start isolated-test subprocess.');
        }

        return $process;
    };
    $write = static function (string $name, string $contents) use ($directory): void {
        if (file_put_contents($directory.'/'.$name, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Could not write complete protected configuration.');
        }
    };
    mkdir($directory.'/data');
    mkdir($directory.'/tmp');
    $password = bin2hex(random_bytes(32));
    $write('client.cnf', "[client]\nprotocol=TCP\nhost=127.0.0.1\nport=13306\nuser=root\npassword=\"{$password}\"\n");
    $write('init.sql', "ALTER USER 'root'@'localhost' IDENTIFIED BY '{$password}';\n");
    $write('server.ini', implode("\n", [
        '[mysqld]', "basedir={$base}", "datadir={$directory}/data", "tmpdir={$directory}/tmp",
        "pid-file={$directory}/test.pid", "log-error={$directory}/server.error.log",
        'port=13306', 'bind-address=127.0.0.1', 'mysqlx=0', 'named-pipe=OFF', 'shared-memory=OFF',
        'event-scheduler=DISABLED', 'local-infile=OFF', 'secure-file-priv=NULL', 'skip-log-bin',
        'persisted-globals-load=OFF', 'skip-name-resolve', 'performance-schema=OFF', 'max-connections=5',
        'innodb-buffer-pool-size=128M', 'innodb-redo-log-capacity=64M', 'temptable-max-ram=64M',
        'max-allowed-packet=256M', 'general-log=OFF', 'slow-query-log=OFF',
        'character-set-server=utf8mb4', 'collation-server=utf8mb4_0900_ai_ci', '',
    ]));
    $phase = 'initialize-new-data-directory';
    $child = $start([$base.'/bin/mysqld.exe', '--defaults-file='.$directory.'/server.ini', '--initialize'], 'initialize');
    $code = waitForRestoreProcess($child, 180);
    proc_close($child);
    $child = null;
    if ($code !== 0) {
        throw new RuntimeException('Isolated data directory initialization failed.');
    }
    $phase = 'start-isolated-server';
    // Windows otherwise launches a monitor whose exit does not stop its child server.
    $server = $start([$base.'/bin/mysqld.exe', '--defaults-file='.$directory.'/server.ini', '--no-monitor', '--init-file='.$directory.'/init.sql'], 'server');
    $stopped = false;
    $report['testServerPid'] = proc_get_status($server)['pid'];
    $report['startedAtUtc'] = gmdate('c');
    $deadline = microtime(true) + 90;
    do {
        if (! proc_get_status($server)['running']) {
            throw new RuntimeException('Test server exited during startup.');
        }
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=13306;charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
            unset($report['lastConnectionError']);
            break;
        } catch (PDOException $connectionError) {
            $report['lastConnectionError'] = restoreConnectionErrorCodes($connectionError);
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Test server did not become ready.');
            }
            usleep(500000);
        }
    } while (true);
    unset($password);
    $phase = 'verify-isolated-server-identity';
    if (trim(file_get_contents($directory.'/test.pid')) !== (string) $report['testServerPid']) {
        throw new RuntimeException('Test server PID is not the owned single-process launcher.');
    }
    $readIdentity = static fn (): array => $pdo->query('SELECT VERSION() AS version, @@port AS port, @@datadir AS data_directory, @@bind_address AS bind_address, @@event_scheduler AS event_scheduler, @@local_infile AS local_infile, @@secure_file_priv AS secure_file_priv, @@log_bin AS log_bin, @@named_pipe AS named_pipe, @@shared_memory AS shared_memory')->fetch(PDO::FETCH_ASSOC);
    assertRestoreTestIdentity($readIdentity(), $directory);
    $identityVerified = true;
    unlink($directory.'/init.sql');
    if ($connectionOnly) {
        $report['status'] = 'isolated-connection-passed';
        $report['connectionVerified'] = true;
        $exitCode = 0;
    } else {
        $pdo->exec('CREATE DATABASE pcc CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $phase = 'import-into-isolated-instance';
        assertRestoreTestIdentity($readIdentity(), $directory);
        $child = $start([$base.'/bin/mysql.exe', '--defaults-file='.$directory.'/client.cnf', '--protocol=TCP', '--host=127.0.0.1', '--port=13306', '--batch', '--binary-mode', '--local-infile=0', '--default-character-set=utf8mb4', '--max-allowed-packet=256M', '--database=pcc'], 'import', $backup);
        $code = waitForRestoreProcess($child, 600);
        proc_close($child);
        $child = null;
        if ($code !== 0 || hash_file('sha256', $backup) !== $backupHash) {
            throw new RuntimeException('Import failed or source backup changed.');
        }
        $phase = 'check-restored-objects';
        assertRestoreTestIdentity($readIdentity(), $directory);
        $tables = $pdo->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='pcc'")->fetchAll(PDO::FETCH_ASSOC);
        $baseTables = array_filter($tables, static fn (array $table): bool => $table['TABLE_TYPE'] === 'BASE TABLE' && $table['ENGINE'] === 'InnoDB');
        $views = array_filter($tables, static fn (array $table): bool => $table['TABLE_TYPE'] === 'VIEW');
        if (count($tables) !== 79 || count($baseTables) !== 78 || count($views) !== 1) {
            throw new RuntimeException('Restored table/view metadata does not match the export.');
        }
        foreach ($tables as $table) {
            $name = str_replace('`', '``', $table['TABLE_NAME']);
            $rows = $pdo->query('CHECK TABLE `pcc`.`'.$name.'` QUICK')->fetchAll(PDO::FETCH_ASSOC);
            $ok = false;
            foreach ($rows as $row) {
                if ($row['Msg_type'] === 'error') {
                    throw new RuntimeException('Restored object check failed.');
                }
                $ok = $ok || ($row['Msg_type'] === 'status' && $row['Msg_text'] === 'OK');
            }
            if (! $ok) {
                throw new RuntimeException('Restored object check did not report OK.');
            }
        }
        $report += ['backupSha256' => $backupHash, 'tableCount' => 78, 'viewCount' => 1, 'objectChecksPassed' => 79, 'importExitCode' => $code];
        $report['status'] = 'isolated-database-restore-passed';
        $report['restoreVerified'] = true;
        $exitCode = 0;
    }
} catch (Throwable $error) {
    $report['failedPhase'] = $phase;
    $report['exceptionType'] = get_class($error);
} finally {
    // Only process handles returned by this invocation can be terminated.
    runRestoreCleanupSteps([
        'childCleanupNeedsAttention' => static function () use ($child): void {
            if (is_resource($child)) {
                proc_terminate($child);
                waitForRestoreProcess($child, 10);
                proc_close($child);
            }
        },
        'shutdownRequestNeedsAttention' => static function () use ($server, $identityVerified, $pdo): void {
            if (is_resource($server) && $identityVerified && $pdo !== null && proc_get_status($server)['running']) {
                try {
                    $pdo->exec('SHUTDOWN');
                } catch (PDOException) {
                    // SHUTDOWN may close the connection; process exit is authoritative.
                }
            }
        },
        'testServerNeedsAdministratorAttention' => static function () use ($server, &$stopped, &$report, &$exitCode): void {
            if (! is_resource($server)) {
                return;
            }
            try {
                waitForRestoreProcess($server, 30);
                $stopped = true;
            } catch (RuntimeException) {
                proc_terminate($server);
                waitForRestoreProcess($server, 10);
                $stopped = true;
                $report['forcedTestServerTermination'] = true;
                $exitCode = 1;
            }
        },
        'serverHandleCleanupNeedsAttention' => static function () use ($server, &$stopped): void {
            if (is_resource($server) && $stopped) {
                proc_close($server);
            }
        },
    ], $report, $exitCode);
}
$report['testLauncherStopped'] = $stopped;
// The elevated wrapper independently checks process inventory and the listener.
$report['testServerStopped'] = false;
$report['completedAtUtc'] = gmdate('c');
if ($exitCode !== 0 && $report['restoreVerified']) {
    $report['status'] = 'restore-passed-cleanup-needs-attention';
} elseif ($exitCode !== 0 && ($report['connectionVerified'] ?? false)) {
    $report['status'] = 'connection-passed-cleanup-needs-attention';
}
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
// The path guard also prevents writing a report when invoked outside the wrapper.
try {
    assertRestoreTestDirectory($directory);
    if (! $workspaceAccepted || file_put_contents($directory.'/restore-report.json', $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Could not write restore report.');
    }
} catch (Throwable) {
    $exitCode = 1;
    $report['reportWriteFailed'] = true;
    if ($report['restoreVerified']) {
        $report['status'] = 'restore-passed-report-write-failed';
    } elseif ($report['connectionVerified'] ?? false) {
        $report['status'] = 'connection-passed-report-write-failed';
    }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}
echo $json.PHP_EOL;
exit($exitCode);

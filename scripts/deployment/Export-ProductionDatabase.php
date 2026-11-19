<?php

declare(strict_types=1);

// Invoke only the protected copy through New-ProductionDatabaseBackup.ps1.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('PHP runtime warning during database backup.');
});
$phase = 'validate-backup-directory';
$optionFile = null;
$process = null;
$exitCode = 1;
try {
    require __DIR__.'/ProductionDatabase.Common.php';
    $directory = str_replace('\\', '/', __DIR__);
    if (count($argv) !== 1 || ! preg_match('~\AC:/ProgramData/SUPREME-Backups/database-[0-9]{8}T[0-9]{6}Z-[0-9a-f]{32}\z~i', $directory)) {
        throw new RuntimeException('Run only from the protected backup directory.');
    }
    $dumpFile = $directory.'/pcc.sql';
    $loginFile = $directory.'/unused-login.cnf';
    foreach ([$dumpFile, $directory.'/mysql-client.cnf', $loginFile, $directory.'/backup-report.json', $directory.'/mysqldump.stderr.log'] as $path) {
        if (file_exists($path)) {
            throw new RuntimeException('Refusing to overwrite backup output.');
        }
    }
    $phase = 'validate-live-metadata';
    $env = readProductionDatabaseEnvironment();
    $metadata = readProductionDatabaseMetadata($env);
    assertProductionBackupMetadata($metadata);
    $phase = 'write-protected-client-options';
    $options = "[client]\nhost=127.0.0.1\nport=3306\nprotocol=TCP\nuser=".quoteMysqlOption($env['DB_USERNAME'])."\npassword=".quoteMysqlOption($env['DB_PASSWORD'])."\n";
    unset($env);
    $optionFile = $directory.'/mysql-client.cnf';
    if (file_put_contents($optionFile, $options, LOCK_EX) !== strlen($options)) {
        throw new RuntimeException('Cannot write complete client options.');
    }
    unset($options);
    // MySQL 8.0 still reads .mylogin.cnf with --defaults-file. Redirect this
    // child's login path to an absent file in the protected directory.
    $childEnvironment = getenv();
    foreach (array_keys($childEnvironment) as $name) {
        if (str_starts_with(strtoupper($name), 'MYSQL')) {
            unset($childEnvironment[$name]);
        }
    }
    $childEnvironment['MYSQL_TEST_LOGIN_FILE'] = $loginFile;
    $command = [
        'C:/laragon/bin/mysql/mysql-8.0.40-winx64/bin/mysqldump.exe',
        '--defaults-file='.$optionFile,
        '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces',
        '--set-gtid-purged=OFF', '--routines', '--events', '--triggers', '--hex-blob',
        '--default-character-set=utf8mb4', '--column-statistics=0',
        '--result-file='.$dumpFile, 'pcc',
    ];
    $phase = 'export';
    $started = gmdate('c');
    $process = proc_open($command, [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', $directory.'/mysqldump.stderr.log', 'x']], $pipes, $directory, $childEnvironment, ['bypass_shell' => true]);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start mysqldump.');
    }
    $deadline = microtime(true) + 1800;
    do {
        $state = proc_get_status($process);
        if (! $state['running']) {
            break;
        }
        if (microtime(true) >= $deadline) {
            $phase = 'export-timeout';
            throw new RuntimeException('Export exceeded 30 minutes.');
        }
        usleep(250000);
    } while (true);
    $dumpExitCode = $state['exitcode'];
    proc_close($process);
    $process = null;
    clearstatcache(true, $dumpFile);
    if ($dumpExitCode !== 0 || ! is_file($dumpFile) || filesize($dumpFile) < 1) {
        throw new RuntimeException('Dump failed; partial output is not a backup.');
    }
    $phase = 'remove-temporary-credentials';
    unlink($optionFile);
    $optionFile = null;
    $phase = 'record-backup';
    $report = [
        'status' => 'export-completed-restore-not-tested',
        'startedAtUtc' => $started, 'completedAtUtc' => gmdate('c'),
        'database' => 'pcc', 'metadataBeforeExport' => $metadata,
        'dumpPath' => $dumpFile, 'bytes' => filesize($dumpFile), 'sha256' => hash_file('sha256', $dumpFile),
        'mysqldumpExitCode' => $dumpExitCode, 'backupCreated' => true,
        'restoreVerified' => false, 'activationAllowed' => false,
        'consistencyCondition' => 'All InnoDB; no concurrent schema changes during export.',
    ];
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($directory.'/backup-report.json', $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Could not record backup metadata.');
    }
    echo $json.PHP_EOL;
    $exitCode = 0;
} catch (Throwable $error) {
    echo json_encode(['status' => 'failed', 'phase' => $phase, 'exceptionType' => get_class($error), 'backupCreated' => false, 'restoreVerified' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    if ($optionFile !== null && is_file($optionFile)) {
        try {
            unlink($optionFile);
        } catch (Throwable) {
            echo "Protected credential file cleanup failed; administrator must remove mysql-client.cnf in this backup directory.\n";
            $exitCode = 1;
        }
    }
}
exit($exitCode);

<?php

// Fixed-target, read-only backup planning. No Laravel application boot,
// business-record queries, exports, service changes or credential output.
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (): never {
    throw new RuntimeException('PHP runtime warning during database inspection.');
});

$phase = 'validate-target';
try {
    $mode = $argv[1] ?? '--target-only';
    if (PHP_OS_FAMILY !== 'Windows' || count($argv) > 2 || ! in_array($mode, ['--target-only', '--metadata'], true)) {
        throw new RuntimeException('Unsupported invocation.');
    }
    require __DIR__.'/ProductionDatabase.Common.php';
    $env = readProductionDatabaseEnvironment();
    $host = '127.0.0.1';
    $port = '3306';
    $database = 'pcc';
    $report = [
        'source' => 'verified-live-folder-env-file',
        'host' => $host,
        'port' => (int) $port,
        'database' => $database,
        'productionEnvironmentFlag' => ($env['APP_ENV'] ?? '') === 'production',
        'databaseQueried' => false,
        'backupCreated' => false,
        'restoreVerified' => false,
    ];
    if ($mode === '--metadata') {
        $phase = 'read-only-metadata';
        $report += readProductionDatabaseMetadata($env);
        $report['databaseQueried'] = true;
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // Do not print exception messages/traces: dotenv/PDO errors may contain
    // credentials or connection details. Report the failed phase explicitly.
    echo json_encode(['status' => 'failed', 'phase' => $phase, 'exceptionType' => get_class($error), 'backupCreated' => false], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}

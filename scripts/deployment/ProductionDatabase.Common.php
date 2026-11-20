<?php

declare(strict_types=1);

// Fixed, inspected production configuration. Loading this file alone performs no I/O.
function readProductionDatabaseEnvironment(): array
{
    $live = 'C:/laragon/www/supreme-cbed-poc';
    if (PHP_OS_FAMILY !== 'Windows' || is_file($live.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Unsupported host or cached configuration.');
    }
    if (hash_file('sha256', $live.'/config/database.php') !== '81db41b35a58662997c95030eef99e62ea9fbe045581134e3bb1a72cf31cd331') {
        throw new RuntimeException('Database configuration changed.');
    }
    require_once $live.'/vendor/autoload.php';
    $env = Dotenv\Dotenv::parse(file_get_contents($live.'/.env'));
    foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DB_SOCKET', 'DB_CHARSET', 'MYSQL_ATTR_SSL_CA'] as $name) {
        if (getenv($name) !== false) {
            throw new RuntimeException('External database override requires inspection.');
        }
    }
    if (($env['DB_CONNECTION'] ?? null) !== 'mysql' || ! empty($env['DB_URL']) || ! empty($env['DB_SOCKET']) || ! empty($env['MYSQL_ATTR_SSL_CA'])) {
        throw new RuntimeException('Unsupported database connection configuration.');
    }
    if (($env['DB_HOST'] ?? '') !== '127.0.0.1' || (string) ($env['DB_PORT'] ?? '3306') !== '3306' || ($env['DB_DATABASE'] ?? '') !== 'pcc') {
        throw new RuntimeException('Unexpected production target.');
    }
    if (! array_key_exists('DB_USERNAME', $env) || ! array_key_exists('DB_PASSWORD', $env)) {
        throw new RuntimeException('Explicit database credentials are required.');
    }

    return $env;
}

function readProductionDatabaseMetadata(array $env): array
{
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pcc;charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    $pdo->exec('SET SESSION MAX_EXECUTION_TIME=5000');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        return [
            'server' => $pdo->query('SELECT VERSION() AS version, DATABASE() AS database_name, CURRENT_USER() AS authenticated_account, @@GLOBAL.gtid_mode AS gtid_mode')->fetch(PDO::FETCH_ASSOC),
            'tableEngines' => $pdo->query("SELECT ENGINE AS engine, COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' GROUP BY ENGINE ORDER BY ENGINE")->fetchAll(PDO::FETCH_ASSOC),
            'viewCount' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'VIEW'")->fetchColumn(),
        ];
    } finally {
        $pdo->rollBack();
    }
}

function assertProductionBackupMetadata(array $metadata): void
{
    if ($metadata['server']['version'] !== '8.0.40' || $metadata['server']['database_name'] !== 'pcc' || $metadata['server']['gtid_mode'] !== 'OFF'
        || count($metadata['tableEngines']) !== 1 || $metadata['tableEngines'][0]['engine'] !== 'InnoDB'
        || (int) $metadata['tableEngines'][0]['table_count'] !== 78 || $metadata['viewCount'] !== 1) {
        throw new RuntimeException('Production metadata changed; inspect before backup.');
    }
}

function quoteMysqlOption(string $value): string
{
    if (str_contains($value, "\0")) {
        throw new RuntimeException('NUL is not supported in a MySQL option.');
    }

    return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', "\x08" => '\\b']).'"';
}

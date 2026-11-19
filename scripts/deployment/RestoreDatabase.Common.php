<?php

declare(strict_types=1);

function assertRestoreTestDirectory(string $directory): void
{
    if (! preg_match('~\AC:/ProgramData/SUPREME-Backups/restore-test-[0-9a-f]{32}\z~i', $directory)) {
        throw new RuntimeException('Unexpected restore-test directory.');
    }
}

function assertRestoreTestIdentity(array $identity, string $directory): void
{
    assertRestoreTestDirectory($directory);
    $dataPath = strtolower(rtrim(str_replace('\\', '/', $identity['data_directory']), '/'));
    if ($identity['version'] !== '8.0.40' || (string) $identity['port'] !== '13306'
        || $dataPath !== strtolower($directory.'/data')
        || $identity['bind_address'] !== '127.0.0.1' || $identity['event_scheduler'] !== 'DISABLED'
        || (string) $identity['local_infile'] !== '0' || $identity['secure_file_priv'] !== null
        || (string) $identity['log_bin'] !== '0' || (string) $identity['named_pipe'] !== '0'
        || (string) $identity['shared_memory'] !== '0') {
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

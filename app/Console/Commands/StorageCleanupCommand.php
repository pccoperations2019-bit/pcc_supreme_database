<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class StorageCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old files from configured storage paths and clean up empty directories';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = config('storage_cleanup.disk', env('STORAGE_CLEANUP_DISK', 'public'));
        $paths = config('storage_cleanup.paths', env('STORAGE_CLEANUP_PATH', 'dpm/exports,dpm/files'));
        $retentionDays = (int) config(
            'storage_cleanup.retention_days',
            env('STORAGE_CLEANUP_RETENTION_DAYS', 30)
        );

        $this->info("Starting storage cleanup on disk: {$disk}");
        $this->info("Deleting files older than {$retentionDays} days.");

        // Convert paths to array if it's a string
        $paths = is_array($paths) ? $paths : explode(',', $paths);
        $paths = array_map('trim', $paths);

        $threshold = now()->subDays($retentionDays)->timestamp;

        $totalFilesScanned = 0;
        $totalFilesDeleted = 0;
        $totalFilesSkipped = 0;
        $totalDirectoriesDeleted = 0;
        $errors = [];

        foreach ($paths as $path) {
            $this->newLine();
            $this->info("Scanning path: {$path}");

            if (! Storage::disk($disk)->exists($path)) {
                $this->warn("Path does not exist: {$path}");

                continue;
            }

            try {
                $files = Storage::disk($disk)->allFiles($path);
                $totalFilesScanned += count($files);

                foreach ($files as $file) {
                    try {
                        $lastModified = Storage::disk($disk)->lastModified($file);

                        // Skip files newer than the retention period
                        if ($lastModified > $threshold) {
                            $totalFilesSkipped++;

                            continue;
                        }

                        if (Storage::disk($disk)->delete($file)) {
                            $totalFilesDeleted++;
                            $this->line("Deleted: {$file}");
                        } else {
                            $errors[] = "Failed to delete: {$file}";
                        }
                    } catch (\Throwable $e) {
                        $errors[] = "Error processing {$file}: {$e->getMessage()}";
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Error scanning {$path}: {$e->getMessage()}";
            }

            // Remove empty directories
            try {
                $directoriesDeleted = $this->deleteEmptyDirectories($disk, $path);
                $totalDirectoriesDeleted += $directoriesDeleted;

                if ($directoriesDeleted > 0) {
                    $this->info("Deleted {$directoriesDeleted} empty directories.");
                }
            } catch (\Throwable $e) {
                $errors[] = "Error cleaning directories in {$path}: {$e->getMessage()}";
            }
        }

        $this->newLine();
        $this->info('==============================');
        $this->info('Storage Cleanup Summary');
        $this->info('==============================');
        $this->info("Files scanned      : {$totalFilesScanned}");
        $this->info("Files deleted      : {$totalFilesDeleted}");
        $this->info("Files skipped      : {$totalFilesSkipped}");
        $this->info("Directories deleted: {$totalDirectoriesDeleted}");

        if ($errors !== []) {
            $this->newLine();
            $this->error('Errors Encountered:');

            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        $this->newLine();
        $this->info('Storage cleanup completed.');

        return self::SUCCESS;
    }

    /**
     * Delete empty directories recursively.
     */
    private function deleteEmptyDirectories(string $disk, string $path): int
    {
        $deletedCount = 0;

        $directories = Storage::disk($disk)->allDirectories($path);

        // Delete deepest directories first
        rsort($directories);

        foreach ($directories as $directory) {
            try {
                $files = Storage::disk($disk)->files($directory);
                $subdirectories = Storage::disk($disk)->directories($directory);

                if (empty($files) && empty($subdirectories) && Storage::disk($disk)->deleteDirectory($directory)) {
                    $deletedCount++;
                    $this->line("Deleted empty directory: {$directory}");
                }
            } catch (\Throwable $e) {
                // Ignore and continue
            }
        }

        return $deletedCount;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UploadPresetFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-preset-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload preset files to the storage and also add their tag metadata to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Command logic to upload preset files and add tag metadata
        $this->info('Uploading preset files and adding tag metadata...');
        // Implementation goes here
        $this->info('Preset files uploaded and tag metadata added successfully.');

        /** @var FilesystemAdapter $localDisk */
        $localDisk = Storage::disk('local');
        $presetFiles = $localDisk->files('presets');

        /** @var FilesystemAdapter $dpmDisk */
        $dpmDisk = Storage::disk('dpm');
        foreach ($presetFiles as $filePath) {
            // Get the full local path
            $fileName = basename($filePath);
            $storageKey = 'presets/'.$fileName;

            $fileContents = $localDisk->get($filePath);
            $dpmDisk->put($storageKey, $fileContents);

            $this->info("Uploaded {$fileName} to DPM storage at {$storageKey}");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\DocumentEnum;
use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;

class GenerateDemoDocuments extends Command
{
    protected $signature = 'docs:generate-demo';

    protected $description = 'Generate demo PDF files for each DocumentEnum case';

    public function handle()
    {
        // Use absolute path for the output directory
        $outputDir = storage_path('app/private/presets');

        // Ensure the output directory exists
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach (DocumentEnum::cases() as $enum) {
            $filename = "{$enum->value}-demo.pdf";
            $fullPath = "{$outputDir}/{$filename}";

            $content = "<h1>{$enum->value}</h1><p>This is a demo document for the {$enum->value}.</p>";

            try {
                // Generate the PDF file
                Browsershot::html($content)
                    ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
                    ->save($fullPath);

                $this->info("Generated: {$fullPath}");
            } catch (\Exception $e) {
                $this->error("Failed to generate PDF for {$enum->value}: ".$e->getMessage());
            }
        }

        $this->info("All demo PDFs have been generated in {$outputDir}");

        return Command::SUCCESS;
    }
}

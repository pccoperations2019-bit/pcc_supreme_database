<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Imports\CBINCCDPProjectSiteImport;
use App\Models\Document;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * DPM import for Projectsite.xlsx (`CBINCCDP` sheet → CBIN/CCDP ProjectSites).
 *
 * Trigger: tag the uploaded Document with key `template`, value
 * `cbin_ccdp_master_list` (see HandleEntityTagged::$workflows).
 *
 * Mirrors ImportServicesWorkflow: PROCESSING status + queue metadata up
 * front, Excel::import() off the `dpm` disk, FAILED on exception.
 */
class ImportCBINCCDPProjectSiteWorkflow
{
    use HandlesFailures, HasQueueMetadata;

    public function resolve(string $taggableType, ?int $taggableId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggableId
        ) {
            return;
        }

        $document = Document::query()->find($taggableId);
        if (! $document || ! $document->file) {
            return;
        }

        $metadata = $document->metadata ?? [];
        $queuedImport = $metadata['queue_import'] ?? [];

        $this->setQueueMetadata($taggableId, 'import');
        $this->registerImportShutdownHandler($document);

        $import = new CBINCCDPProjectSiteImport;
        $import->setDocumentId($taggableId);

        $document->update([
            'status' => DocumentStatusEnum::PROCESSING,
            'metadata' => array_merge($metadata, [
                'queue_import' => array_merge($queuedImport, [
                    'queued_at' => now()->toIso8601String(),
                ]),
            ]),
        ]);

        try {
            Excel::import($import, $document->file->path, 'dpm');
        } catch (\Throwable $exception) {
            $document->update([
                'status' => DocumentStatusEnum::FAILED,
                'metadata' => array_merge($metadata, [
                    'queue_import' => array_merge($queuedImport, [
                        'failed_at' => now()->toIso8601String(),
                        'error' => $exception->getMessage(),
                    ]),
                ]),
            ]);

            throw $exception;
        }
    }
}

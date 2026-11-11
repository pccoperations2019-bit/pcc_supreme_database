<?php

namespace App\Workflows;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Farmer\UpsertFarmerAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Actions\Project\UpsertProjectAction;
use App\Actions\Service\UpsertServiceAction;
use App\Actions\ServiceRecord\UpsertServiceRecordAction;
use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Imports\ServiceRecordImport;
use App\Models\Center;
use App\Models\Document;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportServicesWorkflow
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

        $centerTag = $document->tags()->where('key', 'center')->first();
        $centerId = $centerTag?->value;
        if ($centerId !== null) {
            $center = Center::query()->where('code', $centerId)->first();
            $centerId = $center?->id;
        }

        $import = new ServiceRecordImport(
            app(UpsertServiceRecordAction::class),
            app(UpsertServiceAction::class),
            app(UpsertAddressAction::class),
            app(UpsertPartnerAction::class),
            app(UpsertProjectAction::class),
            app(UpsertFarmerAction::class),
            $centerId,
        );
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

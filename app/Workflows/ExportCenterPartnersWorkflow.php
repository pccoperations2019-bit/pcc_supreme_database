<?php

namespace App\Workflows;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\CenterPartnersExport;
use App\Models\Center;
use App\Models\Document;
use App\Support\Sanitizer;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportCenterPartnersWorkflow
{
    use HandlesFailures, HasQueueMetadata;

    /**
     * Resolve queued export for center partners
     */
    public function resolve(string $taggableType, ?int $taggableId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggableId
        ) {
            return;
        }

        $document = Document::with('file')->find($taggableId);
        if (! $document) {
            return;
        }

        $metadata = $document->metadata ?? [];
        $queuedExport = $metadata['queued_export'] ?? [];

        if (($queuedExport['report_type'] ?? null) !== 'center-partners') {
            return;
        }

        $this->setQueueMetadata($taggableId, 'export');

        $disk = Storage::disk('dpm');

        $this->registerExportShutdownHandler($document);

        try {
            $writerType = match ($queuedExport['download_as']) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
                'pdf' => \Maatwebsite\Excel\Excel::MPDF,
                default => throw new \InvalidArgumentException('Invalid download format specified.'),
            };

            $filters = $queuedExport['filters'] ?? [];
            $parsedFilters = Sanitizer::parseFilters($filters);

            $export = new CenterPartnersExport(
                centerId: $parsedFilters['center_id'] ?? null,
            );

            Excel::store($export, $document->file->path, 'dpm', $writerType);

            $document->file->update([
                'size' => $disk->size($document->file->path),
                'type' => $disk->mimeType($document->file->path) ?: $this->resolveMimeType($queuedExport['download_as']),
            ]);

            $this->markAsProcessed($taggableId);
            $document->update([
                'metadata' => array_merge($metadata, [
                    'queued_export' => array_merge($queuedExport, [
                        'completed_at' => now()->toIso8601String(),
                    ]),
                ]),
            ]);
            $this->markExportCompleted();
        } catch (\Throwable $exception) {
            $document->update([
                'status' => DocumentStatusEnum::FAILED,
                'metadata' => array_merge($metadata, [
                    'queued_export' => array_merge($queuedExport, [
                        'failed_at' => now()->toIso8601String(),
                        'error' => $exception->getMessage(),
                    ]),
                ]),
            ]);

            throw $exception;
        }
    }

    /**
     * Queue export for center partners
     */
    public function queueExport(
        Center $center,
        string $format,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): Document {
        $timestamp = now()->format('Ymd');
        $basename = "center-partners-{$center->id}-{$timestamp}";
        $storageKey = "exports/center-partners/{$basename}.{$format}";

        $document = $createDocumentAction->execute(
            title: Str::headline(str_replace('-', ' ', $basename)),
            fileName: "{$basename}.{$format}",
            fileKey: $storageKey,
            fileType: 'application/octet-stream',
            fileSize: 0,
            uploadedBy: auth()->id(),
            description: "Export for Center {$center->name} partners.",
            status: DocumentStatusEnum::PROCESSING,
            metadata: [
                'queued_export' => [
                    'report_type' => 'center-partners',
                    'center_id' => $center->id,
                    'center_name' => $center->name,
                    'download_as' => $format,
                    'requested_at' => now()->toIso8601String(),
                    'queued_at' => now()->toIso8601String(),
                    'filters' => [
                        'center_id' => $center->id,
                    ],
                ],
            ]
        );

        $createTagAction->execute(
            key: 'report_type',
            value: 'center-partners',
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        $createTagAction->execute(
            key: 'center',
            value: $center->id,
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        $createTagAction->execute(
            key: 'export_type',
            value: 'center-partners',
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        return $document;
    }

    /**
     * Handle direct export (non-queued)
     */
    public function handleDirectExport(Center $center, string $format)
    {
        $filename = "center-{$center->id}-partners-".now()->format('Y-m-d_His');

        $export = new CenterPartnersExport($center->id);

        switch ($format) {
            case 'xlsx':
                return Excel::download($export, $filename.'.xlsx');
            case 'csv':
                return Excel::download($export, $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                return Excel::download($export, $filename.'.pdf', \Maatwebsite\Excel\Excel::MPDF);
            default:
                abort(400, 'Invalid download format specified.');
        }
    }

    /**
     * Resolve MIME type for download format
     */
    private function resolveMimeType(string $downloadFormat): string
    {
        return match ($downloadFormat) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}

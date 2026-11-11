<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\DirectoryDataExport;
use App\Models\Document;
use App\Support\Sanitizer;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportDirectoryDataWorkflow
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

        $document = Document::with('file')->find($taggableId);
        if (! $document) {
            return;
        }

        $metadata = $document->metadata ?? [];
        $queuedExport = $metadata['queued_export'] ?? [];

        if (($queuedExport['report_type'] ?? null) !== 'directory') {
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
            $parsedFilters = Sanitizer::parseDirectoryFilters($filters);

            $export = new DirectoryDataExport(
                template: $parsedFilters['template'] ?? 'centers',
                templateVariant: $parsedFilters['template_variant'] ?? $parsedFilters['variant'] ?? null,
                listAndFigures: $parsedFilters['list_and_figures'] ?? null,
                // The directory filter panel binds Center/Partner/Farmer to
                // `center_id`/`partner`/`farmer` (see reports.*.elements), so accept both
                // the _id and bare keys — otherwise the queued export silently drops the filter.
                centerId: $parsedFilters['center_id'] ?? $parsedFilters['center'] ?? null,
                partnerId: $parsedFilters['partner_id'] ?? $parsedFilters['partner'] ?? null,
                farmerId: $parsedFilters['farmer_id'] ?? $parsedFilters['farmer'] ?? null,
                region: $parsedFilters['region'] ?? null,
                province: $parsedFilters['province'] ?? null,
                district: $parsedFilters['district'] ?? null,
                city: $parsedFilters['city'] ?? null,
                partnerCategory: $parsedFilters['partner_category'] ?? $parsedFilters['business_type'] ?? null,
                status: $parsedFilters['status'] ?? null,
                project: $parsedFilters['project'] ?? null,
                accreditation: $parsedFilters['accreditation'] ?? null,
                division: $parsedFilters['district'] ?? $parsedFilters['division'] ?? null,
            );

            Excel::store($export, $document->file->path, 'dpm', $writerType);

            $document->file->update([
                'size' => $disk->size($document->file->path),
                'type' => $this->resolveMimeType($queuedExport['download_as']),
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
                'status' => DocumentStatusEnum::DRAFT,
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

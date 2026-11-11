<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\BuffaloDerivedProductsExport;
use App\Models\Document;
use App\Support\Sanitizer;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportBuffaloDerivedProductsWorkflow
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

        if (($queuedExport['report_type'] ?? null) !== 'bdp') {
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

            $export = new BuffaloDerivedProductsExport(
                template: $parsedFilters['template'] ?? 'bdp-by-year',
                templateVariant: $parsedFilters['template_variant'] ?? null,
                product: $parsedFilters['product'] ?? 'milk',
                type: $parsedFilters['type'] ?? 'production',
                year: $parsedFilters['year'] ?? null,
                centerId: $parsedFilters['center_id'] ?? null,
                month: $parsedFilters['month'] ?? null,
                region: $parsedFilters['region'] ?? null,
                province: $parsedFilters['province'] ?? null,
                district: $parsedFilters['district'] ?? null,
                city: $parsedFilters['city'] ?? null,
                program: $parsedFilters['program'] ?? null,
                partner: $parsedFilters['partner'] ?? null,
                farmer: $parsedFilters['farmer'] ?? null,
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

<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\MilkFeedingProgramExport;
use App\Exports\MilkFeedingProgramMultipleSheetExport;
use App\Exports\MilkFeedingProgramSummaryExport;
use App\Models\Document;
use App\Support\Sanitizer;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportMilkFeedingProgramsWorkflow
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

        if (($queuedExport['report_type'] ?? null) !== 'milk-feeding-programs') {
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
            $exportType = $queuedExport['export_type'] ?? 'raw';

            $export = $this->makeExport($parsedFilters, $exportType);
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

    private function makeExport(array $parsedFilters, string $exportType): object
    {
        // Only use summary export if explicitly requested, otherwise use raw data export
        if ($exportType === 'summary') {
            return new MilkFeedingProgramSummaryExport(
                year: $parsedFilters['year'] ?? null,
                centerId: $parsedFilters['center_id'] ?? null,
                partnerId: $parsedFilters['partner_id'] ?? null,
                fundedBy: $parsedFilters['funded_by'] ?? null,
                region: $parsedFilters['region'] ?? null,
                milkType: $parsedFilters['milk_type'] ?? null,
                modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
                supplier: $parsedFilters['supplier'] ?? null,
                province: $parsedFilters['province'] ?? null,
            );
        }

        // For raw data, use multiple sheet export if multiple years, otherwise single sheet
        $year = $parsedFilters['year'] ?? null;

        if (is_array($year) && count($year) > 1) {
            return new MilkFeedingProgramMultipleSheetExport(
                year: $year,
                centerId: $parsedFilters['center_id'] ?? null,
                partnerId: $parsedFilters['partner_id'] ?? null,
                fundedBy: $parsedFilters['funded_by'] ?? null,
                region: $parsedFilters['region'] ?? null,
                milkType: $parsedFilters['milk_type'] ?? null,
                modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
                supplier: $parsedFilters['supplier'] ?? null,
                province: $parsedFilters['province'] ?? null,
            );
        }

        // Single sheet for single year or no year filter
        return new MilkFeedingProgramExport(
            year: $year,
            centerId: $parsedFilters['center_id'] ?? null,
            partnerId: $parsedFilters['partner_id'] ?? null,
            fundedBy: $parsedFilters['funded_by'] ?? null,
            region: $parsedFilters['region'] ?? null,
            milkType: $parsedFilters['milk_type'] ?? null,
            modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
            supplier: $parsedFilters['supplier'] ?? null,
            province: $parsedFilters['province'] ?? null,
        );
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

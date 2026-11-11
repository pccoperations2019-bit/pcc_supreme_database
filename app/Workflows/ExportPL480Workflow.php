<?php

namespace App\Workflows;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\PL480Export;
use App\Models\Document;
use App\Services\BrowserShotEnvironmentService;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class ExportPL480Workflow
{
    use HandlesFailures, HasQueueMetadata;

    /**
     * Resolve queued export for PL480
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

        if (($queuedExport['report_type'] ?? null) !== 'pl480') {
            return;
        }

        $this->setQueueMetadata($taggableId, 'export');

        $disk = Storage::disk('dpm');

        $this->registerExportShutdownHandler($document);

        try {
            $downloadAs = $queuedExport['download_as'] ?? null;

            $filters = $queuedExport['filters'] ?? [];
            $reportData = $this->extractReportData($filters);

            if ($downloadAs === 'pdf') {
                // Browser-rendered snapshot of the dashboard (same pipeline as
                // the Milk Production Dashboard PDF export).
                $this->storeDashboardPdf($document, $disk, $reportData);
            } else {
                $writerType = match ($downloadAs) {
                    'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                    'csv' => \Maatwebsite\Excel\Excel::CSV,
                    default => throw new \InvalidArgumentException('Invalid download format specified.'),
                };

                $export = new PL480Export(
                    provinces: $reportData['provinces'],
                    years: $reportData['years'],
                    inputData: $reportData['inputData'],
                    computedData: $reportData['computedData'],
                    selectedWithoutYears: $reportData['selectedWithoutYears'],
                    selectedWithYears: $reportData['selectedWithYears'],
                );

                Excel::store($export, $document->file->path, 'dpm', $writerType);
            }

            $document->file->update([
                'size' => $disk->size($document->file->path),
                'type' => $disk->mimeType($document->file->path) ?: $this->resolveMimeType($queuedExport['download_as'] ?? ''),
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
     * Queue export for PL480
     */
    public function queueExport(
        array $provinces,
        array $years,
        array $inputData,
        array $computedData,
        array $selectedWithoutYears,
        array $selectedWithYears,
        string $format,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): Document {
        $timestamp = now()->format('Ymd');
        $basename = "pl480-milk-production-{$timestamp}";
        $storageKey = "exports/pl480/{$basename}.{$format}";

        $document = $createDocumentAction->execute(
            title: Str::headline(str_replace('-', ' ', $basename)),
            fileName: "{$basename}.{$format}",
            fileKey: $storageKey,
            fileType: 'application/octet-stream',
            fileSize: 0,
            uploadedBy: auth()->id(),
            description: 'Export for PL480 milk production data.',
            status: DocumentStatusEnum::PROCESSING,
            metadata: [
                'queued_export' => [
                    'report_type' => 'pl480',
                    'download_as' => $format,
                    'requested_at' => now()->toIso8601String(),
                    'queued_at' => now()->toIso8601String(),
                    'filters' => [
                        'provinces' => $provinces,
                        'years' => $years,
                        'input_data' => $inputData,
                        'computed_data' => $computedData,
                        'selected_without_years' => $selectedWithoutYears,
                        'selected_with_years' => $selectedWithYears,
                    ],
                ],
            ]
        );

        $createTagAction->execute(
            key: 'report_type',
            value: 'pl480',
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        $createTagAction->execute(
            key: 'export_type',
            value: 'pl480',
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        $this->logExportRequested($document, $format, queued: true);

        return $document;
    }

    /**
     * Record that a PL480 export was asked for.
     *
     * "Export" in the wording is what files the entry under Reports rather than
     * letting it fall through to the System category, which is where a reviewer
     * would never look for it.
     */
    private function logExportRequested(?Document $document, string $format, bool $queued): void
    {
        $description = $queued
            ? 'PL480 Report Export Requested'
            : 'PL480 Report Exported';

        $logger = activity()
            ->causedBy(auth()->user())
            ->withProperties(array_filter([
                'format' => $format,
                'queued' => $queued,
            ]))
            ->event(str($description)->slug()->toString());

        if ($document instanceof Document) {
            $logger->performedOn($document);
        }

        $logger->log($description);
    }

    /**
     * Handle direct export (non-queued)
     */
    public function handleDirectExport(
        array $provinces,
        array $years,
        array $inputData,
        array $computedData,
        array $selectedWithoutYears,
        array $selectedWithYears,
        string $format
    ) {
        $filename = 'pl480-milk-production-'.now()->format('Y-m-d_His');

        // A direct download creates no document at all, so without this the trail
        // cannot tell that a full report on every province left the building.
        $this->logExportRequested(null, $format, queued: false);

        $export = new PL480Export(
            provinces: $provinces,
            years: $years,
            inputData: $inputData,
            computedData: $computedData,
            selectedWithoutYears: $selectedWithoutYears,
            selectedWithYears: $selectedWithYears,
        );

        switch ($format) {
            case 'xlsx':
                return Excel::download($export, $filename.'.xlsx');
            case 'csv':
                return Excel::download($export, $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                return $this->buildDashboardPdf(
                    provinces: $provinces,
                    inputData: $inputData,
                    computedData: $computedData,
                    selectedWithoutYears: $selectedWithoutYears,
                    selectedWithYears: $selectedWithYears,
                )->download($filename.'.pdf');
            default:
                abort(400, 'Invalid download format specified.');
        }
    }

    /**
     * Extract the report datasets from the queued filters.
     *
     * NOTE: the nested input/computed maps must be read raw — a generic
     * flat filter parser would re-index them and drop the province keys.
     */
    private function extractReportData(array $filters): array
    {
        $years = array_map(
            fn ($year) => (int) $year,
            array_values((array) ($filters['years'] ?? []))
        );

        $normalizeYears = function ($value) use ($years) {
            if (in_array($value, [null, '', 'all'], true)) {
                return $years;
            }

            $list = is_array($value) ? array_values($value) : [$value];

            return array_values(array_map(fn ($year) => (int) $year, $list));
        };

        return [
            'provinces' => array_values((array) ($filters['provinces'] ?? [])),
            'years' => $years,
            'inputData' => (array) ($filters['input_data'] ?? []),
            'computedData' => (array) ($filters['computed_data'] ?? []),
            'selectedWithoutYears' => $normalizeYears($filters['selected_without_years'] ?? null),
            'selectedWithYears' => $normalizeYears($filters['selected_with_years'] ?? null),
        ];
    }

    /**
     * Render the browser snapshot PDF of the PL480 dashboard.
     */
    private function storeDashboardPdf(Document $document, $disk, array $reportData): void
    {
        $pdf = $this->buildDashboardPdf(
            provinces: $reportData['provinces'],
            inputData: $reportData['inputData'],
            computedData: $reportData['computedData'],
            selectedWithoutYears: $reportData['selectedWithoutYears'],
            selectedWithYears: $reportData['selectedWithYears'],
        );

        $temporaryFile = tempnam(sys_get_temp_dir(), 'pl480-dashboard-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Unable to create temporary file for dashboard PDF.');
        }

        $temporaryPdf = $temporaryFile.'.pdf';
        rename($temporaryFile, $temporaryPdf);

        $pdf->save($temporaryPdf);
        $contents = file_get_contents($temporaryPdf);
        if ($contents === false) {
            @unlink($temporaryPdf);
            throw new \RuntimeException('Unable to read generated dashboard PDF.');
        }

        $disk->put($document->file->path, $contents, ['visibility' => 'public']);
        @unlink($temporaryPdf);
    }

    private function buildDashboardPdf(
        array $provinces,
        array $inputData,
        array $computedData,
        array $selectedWithoutYears,
        array $selectedWithYears,
    ) {
        $browserShotEnvironmentService = app(BrowserShotEnvironmentService::class);

        $pdf = Pdf::view('pdfs.PL480Dashboard', [
            'provinces' => $provinces,
            'inputData' => $inputData,
            'computedData' => $computedData,
            'selectedWithoutYears' => $selectedWithoutYears,
            'selectedWithYears' => $selectedWithYears,
        ]);

        $pdf->format('A4')->landscape();

        $pdf->withBrowsershot(function (Browsershot $browsershot) use ($browserShotEnvironmentService) {
            $nodePath = $browserShotEnvironmentService->getNodePath();
            $npmPath = $browserShotEnvironmentService->getNpmPath();
            $chromePath = $browserShotEnvironmentService->getChromePath();
            $chromeArguments = $browserShotEnvironmentService->getChromeArguments();

            if ($nodePath !== 'node') {
                $browsershot->setNodeBinary($nodePath);
            }

            if ($npmPath !== 'npm') {
                $browsershot->setNpmBinary($npmPath);
            }

            if ($chromePath) {
                $browsershot->setChromePath($chromePath);
            }

            if ($chromeArguments !== []) {
                $browsershot->addChromiumArguments($chromeArguments);
            }
        });

        return $pdf;
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

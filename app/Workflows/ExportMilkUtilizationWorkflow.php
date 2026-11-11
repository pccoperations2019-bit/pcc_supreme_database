<?php

namespace App\Workflows;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Exports\MilkUtilizationExport;
use App\Models\Document;
use App\Services\BrowserShotEnvironmentService;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class ExportMilkUtilizationWorkflow
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

        if (($queuedExport['report_type'] ?? null) !== 'milk-utilization') {
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
                $this->storeDashboardPdf($document, $disk, $reportData);
            } else {
                $writerType = match ($downloadAs) {
                    'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                    'csv' => \Maatwebsite\Excel\Excel::CSV,
                    default => throw new \InvalidArgumentException('Invalid download format specified.'),
                };

                $export = new MilkUtilizationExport(
                    rows: $reportData['rows'],
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

    public function queueExport(
        array $rows,
        string $format,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ): Document {
        $timestamp = now()->format('Ymd');
        $basename = "milk-utilization-{$timestamp}";
        $storageKey = "exports/milk-utilization/{$basename}.{$format}";

        $document = $createDocumentAction->execute(
            title: Str::headline(str_replace('-', ' ', $basename)),
            fileName: "{$basename}.{$format}",
            fileKey: $storageKey,
            fileType: 'application/octet-stream',
            fileSize: 0,
            uploadedBy: auth()->id(),
            description: 'Export for Milk Utilization report.',
            status: DocumentStatusEnum::PROCESSING,
            metadata: [
                'queued_export' => [
                    'report_type' => 'milk-utilization',
                    'download_as' => $format,
                    'requested_at' => now()->toIso8601String(),
                    'queued_at' => now()->toIso8601String(),
                    'filters' => [
                        'rows' => $rows,
                    ],
                ],
            ]
        );

        $createTagAction->execute(
            key: 'report_type',
            value: 'milk-utilization',
            taggableType: Str::of(class_basename(Document::class))->lower(),
            taggableId: $document->id
        );

        $this->logExportRequested($document, $format, queued: true);

        return $document;
    }

    public function handleDirectExport(
        array $rows,
        string $format
    ) {
        $filename = 'milk-utilization-'.now()->format('Y-m-d_His');

        $this->logExportRequested(null, $format, queued: false);

        $export = new MilkUtilizationExport(
            rows: $rows,
        );

        switch ($format) {
            case 'xlsx':
                return Excel::download($export, $filename.'.xlsx');
            case 'csv':
                return Excel::download($export, $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                return $this->buildDashboardPdf(rows: $rows)->download($filename.'.pdf');
            default:
                abort(400, 'Invalid download format specified.');
        }
    }

    private function extractReportData(array $filters): array
    {
        return [
            'rows' => (array) ($filters['rows'] ?? []),
        ];
    }

    private function storeDashboardPdf(Document $document, $disk, array $reportData): void
    {
        $pdf = $this->buildDashboardPdf(
            rows: $reportData['rows'],
        );

        $temporaryFile = tempnam(sys_get_temp_dir(), 'milk-utilization-');
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

    private function buildDashboardPdf(array $rows)
    {
        $browserShotEnvironmentService = app(BrowserShotEnvironmentService::class);

        $pdf = Pdf::view('pdfs.MilkUtilizationDashboard', [
            'rows' => $rows,
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

    private function logExportRequested(?Document $document, string $format, bool $queued): void
    {
        $description = $queued
            ? 'Milk Utilization Export Requested'
            : 'Milk Utilization Exported';

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

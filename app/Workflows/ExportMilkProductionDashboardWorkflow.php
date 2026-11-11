<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Enums\MilkProductionDataTypeEnum;
use App\Models\Document;
use App\Queries\Reports\MilkProductionDashboardScorecardQuery;
use App\Services\BrowserShotEnvironmentService;
use App\Workflows\Concerns\HandlesFailures;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class ExportMilkProductionDashboardWorkflow
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

        if ($queuedExport['report_type'] !== 'milk-production-dashboard') {
            return;
        }

        $this->setQueueMetadata($taggableId, 'export');

        $disk = Storage::disk('dpm');

        $this->registerExportShutdownHandler($document);

        try {
            $this->storeDashboardPdf($document, $disk, $queuedExport);

            $document->file->update([
                'size' => $disk->size($document->file->path),
                'type' => $disk->mimeType($document->file->path) ?: 'application/pdf',
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

    private function storeDashboardPdf(
        Document $document,
        $disk,
        array $queuedExport,
    ): void {
        $filters = $queuedExport['filters'] ?? [];
        $scorecardQuery = app(MilkProductionDashboardScorecardQuery::class);
        $browserShotEnvironmentService = app(BrowserShotEnvironmentService::class);

        $dashboardYear = (int) ($queuedExport['dashboard_year'] ?? now()->year);
        $dashboardMonth = (int) ($queuedExport['dashboard_month'] ?? now()->month);
        $dashboardReportType = MilkProductionDataTypeEnum::tryFrom(
            (string) ($queuedExport['dashboard_report_type'] ?? MilkProductionDataTypeEnum::PRODUCTION->value)
        ) ?? MilkProductionDataTypeEnum::PRODUCTION;

        $dashboard = $scorecardQuery->execute(
            year: $dashboardYear,
            month: $dashboardMonth,
            reportType: $dashboardReportType,
            filters: $filters,
        );

        $temporaryFile = tempnam(sys_get_temp_dir(), 'milk-dashboard-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Unable to create temporary file for dashboard PDF.');
        }

        $temporaryPdf = $temporaryFile.'.pdf';
        rename($temporaryFile, $temporaryPdf);
        $pdf = Pdf::view('pdfs.MilkProductionDashboard', [
            'dashboard' => $dashboard,
            'reportType' => $dashboardReportType->value,
        ]);

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

        $pdf->save($temporaryPdf);
        $contents = file_get_contents($temporaryPdf);
        if ($contents === false) {
            @unlink($temporaryPdf);
            throw new \RuntimeException('Unable to read generated dashboard PDF.');
        }

        $disk->put($document->file->path, $contents, ['visibility' => 'public']);
        @unlink($temporaryPdf);
    }
}

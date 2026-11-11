<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\DocumentStatusEnum;
use App\Exports\BuffaloDerivedProductsExport;
use App\Exports\DirectoryDataExport;
use App\Exports\MilkFeedingProgramExport;
use App\Exports\MilkFeedingProgramMultipleSheetExport;
use App\Exports\MilkFeedingProgramSummaryExport;
use App\Exports\MilkUtilizationExport;
use App\Models\Document;
use App\Queries\Reports\GenerateReportQuery;
use App\Services\PivotFieldRegistryService;
use App\Services\Reports\ReportCatalogService;
use App\Support\Sanitizer;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateReportController extends Controller
{
    private const TEMPLATE_EXTENSIONS = ['xlsx', 'xls', 'csv', 'pdf', 'docx', 'doc'];

    /**
     * Handle the incoming request.
     */
    public function index(Request $request, ReportCatalogService $reportCatalog, PivotFieldRegistryService $pivotRegistry)
    {
        $queryFilters = $request->get('filter', []);
        $reportData = (new GenerateReportQuery)->execute($queryFilters, $request->user());

        if ($reportData['redirect_filters'] !== null) {
            $redirectQuery = $request->query();
            $redirectQuery['filter'] = $reportData['redirect_filters'];

            return to_route('reports.generate', $redirectQuery);
        }

        $filters = Inertia::getShared('filters');

        $reportTypes = $reportCatalog->reportTypes();
        if (! $request->user()?->can('export-data')) {
            $reportTypes = array_values(array_filter($reportTypes, fn ($t) => ($t['value'] ?? null) !== 'custom-pivot'));
        }

        return inertia('Reports/GenerateReport', [
            'filters' => [
                'report_type' => $reportData['report_type'],
                ...$reportData['report_filters'],
                ...$filters,
                'report_types' => $reportTypes,
                'report_filter_elements' => $reportCatalog->filterElementsByType(),
                'templates' => $this->availableTemplates()
                    ->map(fn (array $t) => [
                        'value' => $t['key'],
                        'label' => $t['label'],
                    ])
                    ->values()
                    ->all(),
                'pivot_sources' => $pivotRegistry->sources(),
            ],
        ]);
    }

    public function post(
        Request $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        // Defence in depth: the route also carries the permission middleware,
        // but a report generator reachable another way must still refuse. An
        // encoder may export, and center_id is pinned to its own center below.
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:milk-feeding-programs,bdp,pcc-commitment,directory,milk-utilization'],
            'download_as' => ['required', 'string', 'in:xlsx,csv,pdf'],
            'export_type' => ['nullable', 'string', 'in:raw,summary'],
            'queue_export' => ['nullable', 'boolean'],
            // Only template-driven reports (bdp, directory) need a template;
            // milk-feeding-programs exports its own sheet layout.
            'template' => [Rule::requiredIf(in_array($request->input('report_type'), ['bdp', 'directory'], true)), 'nullable', 'string'],
            // Hidden for BDP report type (kept for future use) – nullable to allow BDP exports without it
            'list_and_figures' => ['nullable', 'string', 'in:list,figures'],
            'section' => ['nullable', 'string', 'in:institutional,cbed'],
        ]);

        $reportType = $validated['report_type'];
        $downloadFormat = $validated['download_as'];
        $queueExport = (bool) ($validated['queue_export'] ?? false);

        $filters = $request->except(['report_type', 'download_as', 'queue_export']);

        if ($request->user()?->isCenterScoped()) {
            $filters['center_id'] = $request->user()->center_id;
        }

        $exportType = $validated['export_type'] ?? null;

        if ($queueExport) {
            $document = $this->queueReportExport(
                request: $request,
                createDocumentAction: $createDocumentAction,
                createTagAction: $createTagAction,
                reportType: $reportType,
                filters: $filters,
                downloadFormat: $downloadFormat,
                exportType: $exportType,
            );

            return response()->json([
                'message' => 'Export queued successfully.',
                'document_id' => $document->id,
            ], 202);
        }

        $export = $this->makeExport($reportType, $filters, $exportType);
        $filename = $this->filenameBase($reportType, $exportType, $filters);

        // Handle different download formats
        switch ($downloadFormat) {
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

    public function showQueuedExport(Document $document): JsonResponse
    {
        $this->authorizeExportDocument($document);

        $document->load(['file', 'tags']);

        return response()->json([
            'id' => $document->id,
            'title' => $document->title,
            'status' => $document->status?->value,
            'progress' => $document->progress,
            'download_url' => $document->download_url,
            'file_name' => $document->file?->name,
            'file_size' => $document->file?->size,
            'metadata' => $document->metadata,
        ]);
    }

    public function downloadQueuedExport(Document $document): StreamedResponse
    {
        $this->authorizeExportDocument($document);

        abort_unless($document->isReadyForDownload(), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('dpm');

        return $disk->download(
            $document->file->path,
            $document->file->name
        );
    }

    /**
     * A queued export is readable by anyone who may export at national breadth,
     * and otherwise only by the account that queued it.
     *
     * Queued exports carry report tags rather than a `center` tag (a centre tag
     * would route them into the import workflow), so the account that queued the
     * export — recorded on the underlying file — is the only reliable scope
     * available for a center-level account.
     */
    private function authorizeExportDocument(Document $document): void
    {
        $user = request()->user();

        abort_if($user === null, 403);

        if ($user->canExportData()) {
            return;
        }

        abort_unless(
            $user->canExportOwnCenter()
                && $document->file?->uploaded_by === $user->getKey(),
            403,
            'You may only download exports you queued.'
        );
    }

    private function queueReportExport(
        Request $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
        string $reportType,
        array $filters,
        string $downloadFormat,
        ?string $exportType,
    ): Document {
        $baseFilename = $this->filenameBase($reportType, $exportType, $filters);
        $storageKey = "exports/reports/{$baseFilename}.{$downloadFormat}";

        $document = $createDocumentAction->execute(
            title: Str::headline(str_replace('-', ' ', $baseFilename)),
            fileName: "{$baseFilename}.{$downloadFormat}",
            fileKey: $storageKey,
            fileType: 'application/octet-stream',
            fileSize: 0,
            uploadedBy: $request->user()->id,
            description: $this->reportDescription($reportType, $exportType),
            status: DocumentStatusEnum::PROCESSING,
            metadata: [
                'queued_export' => [
                    'report_type' => $reportType,
                    'export_type' => $exportType,
                    'download_as' => $downloadFormat,
                    'requested_at' => now()->toIso8601String(),
                    'filters' => $filters,
                ],
            ],
        );

        // Only the report tags: a `document`-kind tag would route the export
        // into the import workflow and render as a bogus "Document Type" in
        // the details. The export is identified by `report_type` alone.
        foreach (
            [
                ['key' => 'report_type', 'value' => $reportType],
            ] as $tag
        ) {
            $createTagAction->execute(
                key: $tag['key'],
                value: $tag['value'],
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        if ($exportType) {
            $createTagAction->execute(
                key: 'export_type',
                value: $exportType,
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        $document->update([
            'metadata' => array_merge($document->metadata ?? [], [
                'queued_export' => [
                    'report_type' => $reportType,
                    'export_type' => $exportType,
                    'download_as' => $downloadFormat,
                    'filters' => $filters,
                    'queued_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return $document;
    }

    /**
     * Build the Excel export instance for immediate download (same construction as queued workflows).
     *
     * `$downloadFormat` is passed through so a report whose sheet carries live
     * formulas can fall back to plain values for the CSV writer, which has no
     * formula engine. Reports that do not use formulas ignore it.
     */
    private function makeExport(string $reportType, array $filters, ?string $exportType): object
    {
        $parsed = Sanitizer::parseDirectoryFilters($filters);

        return match ($reportType) {
            'milk-feeding-programs' => $this->makeMilkFeedingProgramsExport($parsed, $exportType ?? 'raw'),
            'bdp' => new BuffaloDerivedProductsExport(
                template: $parsed['template'] ?? 'bdp-by-year',
                templateVariant: $parsed['template_variant'] ?? null,
                product: $parsed['product'] ?? 'milk',
                type: $parsed['type'] ?? 'production',
                year: $parsed['year'] ?? null,
                centerId: $parsed['center_id'] ?? null,
                month: $parsed['month'] ?? null,
                region: $parsed['region'] ?? null,
                province: $parsed['province'] ?? null,
                district: $parsed['district'] ?? null,
                city: $parsed['city'] ?? null,
                program: $parsed['program'] ?? null,
                partner: $parsed['partner'] ?? null,
                farmer: $parsed['farmer'] ?? null,
            ),
            'directory' => $this->makeDirectoryExport($parsed),
            default => throw new \InvalidArgumentException('Invalid report type specified.'),
        };
    }

    private function makeMilkUtilizationExport(array $parsedFilters, ?string $downloadFormat = null): MilkUtilizationExport
    {
        return MilkUtilizationExport::fromFilters(
            $parsedFilters,
            in_array((string) $downloadFormat, (array) config('milk_utilization.export.formula_formats', ['xlsx']), true),
        );
    }

    /**
     * Build the directory export for the selected template. Area pivot
     * templates render through DirectoryDataExport; the raw data templates
     * reuse the dedicated masterlist sheet exports.
     */
    private function makeDirectoryExport(array $parsedFilters): object
    {
        // Re-parse with the directory-specific parser so location filters
        // (region, province, city, district) keep their 'all' sentinel value
        // instead of being collapsed to null by the generic parser.
        $parsedFilters = Sanitizer::parseDirectoryFilters($parsedFilters);

        $listAndFigures = $parsedFilters['list_and_figures'] ?? null;

        return new DirectoryDataExport(
            template: $parsedFilters['template'] ?? 'centers',
            templateVariant: $parsedFilters['template_variant'] ?? $parsedFilters['variant'] ?? null,
            listAndFigures: $listAndFigures,
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
            division: $parsedFilters['division'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $parsedFilters
     */
    private function makeMilkFeedingProgramsExport(array $parsedFilters, string $exportType): MilkFeedingProgramExport|MilkFeedingProgramMultipleSheetExport|MilkFeedingProgramSummaryExport
    {
        if ($exportType === 'summary') {
            return new MilkFeedingProgramSummaryExport(
                year: $parsedFilters['year'] ?? null,
                centerId: $parsedFilters['center_id'] ?? null,
                partnerId: $parsedFilters['partner_id'] ?? null,
                fundedBy: $parsedFilters['funded_by'] ?? null,
                region: $parsedFilters['region'] ?? null,
                division: $parsedFilters['division'] ?? null,
                milkType: $parsedFilters['milk_type'] ?? null,
                modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
                supplier: $parsedFilters['supplier'] ?? null,
                province: $parsedFilters['province'] ?? null,
                city: $parsedFilters['city'] ?? null,
            );
        }

        $year = $parsedFilters['year'] ?? null;

        if (is_array($year) && count($year) > 1) {
            return new MilkFeedingProgramMultipleSheetExport(
                year: $year,
                centerId: $parsedFilters['center_id'] ?? null,
                partnerId: $parsedFilters['partner_id'] ?? null,
                fundedBy: $parsedFilters['funded_by'] ?? null,
                region: $parsedFilters['region'] ?? null,
                division: $parsedFilters['division'] ?? null,
                city: $parsedFilters['city'] ?? null,
                milkType: $parsedFilters['milk_type'] ?? null,
                modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
                supplier: $parsedFilters['supplier'] ?? null,
                province: $parsedFilters['province'] ?? null,
            );
        }

        return new MilkFeedingProgramExport(
            year: $year,
            centerId: $parsedFilters['center_id'] ?? null,
            partnerId: $parsedFilters['partner_id'] ?? null,
            fundedBy: $parsedFilters['funded_by'] ?? null,
            region: $parsedFilters['region'] ?? null,
            division: $parsedFilters['division'] ?? null,
            city: $parsedFilters['city'] ?? null,
            milkType: $parsedFilters['milk_type'] ?? null,
            modeOfProcurement: $parsedFilters['mode_of_procurement'] ?? null,
            supplier: $parsedFilters['supplier'] ?? null,
            province: $parsedFilters['province'] ?? null,
        );
    }

    private function filenameBase(string $reportType, ?string $exportType = null, array $filters = []): string
    {
        $date = now()->format('Y-m-d');
        $template = $filters['template'] ?? config("reports.{$reportType}.defaults.template", '');

        if ($reportType === 'bdp' && $template === '') {
            $product = $filters['product'] ?? 'milk';
            $type = $filters['type'] ?? 'production';

            return "bdp-{$product}-{$type}-report-{$date}";
        }

        return match ($reportType) {
            'milk-feeding-programs' => $exportType === 'summary'
                ? "milk-feeding-programs-summary-report-{$date}"
                : "milk-feeding-programs-report-{$date}",
            'bdp', 'directory' => $template !== ''
                ? "{$template}-{$date}"
                : "directory-data-report-{$date}",
            'milk-utilization' => "milk-utilization-report-{$date}",
            default => throw new \InvalidArgumentException('Invalid report type specified.'),
        };
    }

    private function reportDescription(string $reportType, ?string $exportType = null): string
    {
        $normalizedExportType = $exportType ?? 'raw';

        return match ($reportType) {
            'milk-feeding-programs' => $normalizedExportType === 'summary'
                ? 'Export for Milk Feeding Programs summary report.'
                : 'Export for Milk Feeding Programs report.',
            'bdp' => 'Export for Buffalo Derived Products report.',
            'directory' => 'Export for Directory data report.',
            'milk-utilization' => 'Export for Milk Utilization report.',
            default => throw new \InvalidArgumentException('Invalid report type specified.'),
        };
    }

    /**
     * Stream-download a template file, resolved by opaque key.
     * The client never sees or submits a filesystem path.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        // The Supreme Template and other report templates are blank forms, not
        // report data, so any report reader (including a center reviewer) may
        // download them. Export data itself still needs an export permission.
        abort_unless($request->user()?->can('view-reports'), 403, 'You are not allowed to download templates.');

        $request->validate([
            'key' => ['required', 'string'],
        ]);

        $template = $this->availableTemplates()->get($request->input('key'));

        abort_unless($template, 404, 'Template not found.');

        return Storage::disk('public')->download($template['path'], $template['label']);
    }

    /**
     * Single source of truth for downloadable templates.
     *
     * Keyed by a stable opaque identifier (CRC32 of the path) so the client
     * only ever sees short hashes — no filesystem paths cross the trust boundary.
     *
     * @return Collection<string, array{key: string, path: string, label: string}>
     */
    private function availableTemplates(): Collection
    {
        return once(function () {
            $disk = Storage::disk('public');

            return collect($disk->files('templates'))
                ->filter(fn (string $path) => in_array(
                    strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    self::TEMPLATE_EXTENSIONS
                ))
                ->mapWithKeys(function (string $path) {
                    $key = hash('crc32b', $path);
                    $label = preg_replace('/^Template\s*-\s*/i', '', pathinfo($path, PATHINFO_FILENAME))
                        .'.'.pathinfo($path, PATHINFO_EXTENSION);

                    return [$key => [
                        'key' => $key,
                        'path' => $path,
                        'label' => $label,
                    ]];
                });
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\MilkProduction\UpsertMilkCostAction;
use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\MilkProduction\UpsertMilkTradedAction;
use App\Actions\MilkProduction\UpsertMilkUsageAction;
use App\Actions\MilkProduction\UpsertMilkWastageAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\CenterEnum;
use App\Enums\DocumentStatusEnum;
use App\Enums\MilkingTimeEnum;
use App\Enums\MilkProductionDataTypeEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\MonthEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreMilkProductionRequest;
use App\Http\Requests\UpdateMilkProductionRequest;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Herd;
use App\Models\MilkProduction;
use App\Models\Partner;
use App\Queries\Aggregate\AggregatedMilkProductionQuery;
use App\Queries\Reports\MilkProductionDashboardScorecardQuery;
use App\Services\BrowserShotEnvironmentService;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\EntryFormDefaults;
use App\Support\Filters;
use App\Support\Sanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class MilkProductionController extends Controller
{
    public function __construct(
        private readonly UpsertMilkProductionAction $upsertMilkProductionAction,
        private readonly UpsertMilkUsageAction $upsertMilkUsageAction,
        private readonly UpsertMilkWastageAction $upsertMilkWastageAction,
        private readonly UpsertMilkTradedAction $upsertMilkTradedAction,
        private readonly UpsertMilkSaleAction $upsertMilkSaleAction,
        private readonly UpsertMilkCostAction $upsertMilkCostAction,
        private readonly MilkProductionDashboardScorecardQuery $milkProductionDashboardScorecardQuery,
        private readonly BrowserShotEnvironmentService $browserShotEnvironmentService,
    ) {
        $this->authorizeResource(MilkProduction::class, 'milk_production');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = MilkProduction::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_milk_productions_view' => $view]);
        }

        $programFilter = $filters['program'] ?? null;

        // An absent `filter[program]` is the "Total" tab, which combines IH + CBED.
        // Resolve center visibility as "all programs" so both national centers
        // (GP and NIZ) are included instead of hiding GP.
        $centerProgram = $programFilter ?? Filters::ALL_PROGRAM;

        $hiddenCodes = array_map('strtoupper', Centers::hiddenCenters($centerProgram));

        // Auto-filter to latest year for monthly view only
        if ($view === 'monthly' && empty($filters['year']) && $latestYear) {
            $filters['year'] = (string) $latestYear;
        }

        if ($view === 'data') {
            $query = MilkProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $milkProductions = QueryBuilder::for($query)
                ->with(['center', 'partner', 'farmer'])
                ->when(request()->get('search'), fn ($query, $search) => $query->globalSearch($search))
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_productions.center_id)')),
                    'year',
                    'month',
                    'total_volume_produced',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('milk_productions.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $activeCenterCodes = Centers::forActiveProgram($centerProgram)
                ->map(fn (CenterEnum $c) => $c->code())
                ->values()
                ->all();

            $query = MilkProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->whereIn('code', $activeCenterCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', (int) $filters['year']);
            }

            $aggregatedQuery = resolve(AggregatedMilkProductionQuery::class);
            $query = $aggregatedQuery->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('milk_productions.center_id').' asc');

            $milkProductions = QueryBuilder::for($query)
                ->with(['center', 'partner'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                    AllowedFilter::callback('partner_id', fn () => null),
                ])
                ->allowedSorts(array_merge([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_productions.center_id)')),
                ], collect(MonthEnum::labels())->values()->map(fn ($label) => strtolower(substr($label, 0, 3)))->all(), [
                    'total_volume',
                ]))
                ->paginate(14)
                ->appends($request->query());

            $aggregatedQuery->transform($milkProductions, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->where('status', ProfileStatusEnum::ACTIVE->value)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = MilkProduction::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        $defaultYear = $latestYear ?? now()->year;

        $response = [
            'milkProductions' => $milkProductions,
            'centers' => $centers,
            'years' => $years,
            'defaultYear' => $defaultYear,
            '_view' => $view,
        ];

        if ($view !== 'data') {
            $dashboardFilters = $filters;
            unset($dashboardFilters['program']);
            $dashboard = $this->resolveDashboardParams($request);
            $response['dashboard'] = $this->milkProductionDashboardScorecardQuery->execute(
                year: $dashboard['year'],
                month: $dashboard['month'],
                reportType: $dashboard['reportType'],
                filters: $dashboardFilters,
            );
        }

        return Inertia::render('Productions/Milk/Index', $response);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Productions/Milk/Create', [
            // The empty record is seeded from the query so a deep link from a monthly task
            // opens the form already on that month and program. toArray() rather
            // than a bare array because this one page reads named keys off the
            // model, and the defaults have to merge with them rather than replace.
            'milkProduction' => array_merge((new MilkProduction)->toArray(), EntryFormDefaults::for($request)),
            'centers' => Center::query()
                ->visibleTo($user)
                ->excludingHidden(Centers::hiddenCenters())
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->visibleTo($user)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'farmers' => Farmer::query()
                ->visibleTo($user)
                ->orderBy('last_name', 'asc')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name']),
            'herds' => Herd::query()
                ->visibleTo($user)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreMilkProductionRequest $request)
    {
        $validated = $request->validated();

        $period = $this->resolveTransactionPeriod($validated);
        if ($period === null) {
            return back()->withErrors(['collection_date' => 'Invalid collection date.']);
        }

        $program = empty($validated['program']) ? ProgramEnum::DEFAULT : ProgramEnum::from($validated['program']);

        $milkProduction = DB::transaction(function () use ($validated, $period, $program) {
            $centerId = $validated['center_id'] ?? null;
            if (! $centerId && ! empty($validated['partner_id'])) {
                $partner = Partner::query()->find($validated['partner_id']);
                $centerId = $partner?->center_id;
            }

            $year = $validated['year'] ?? $period['year'];
            $month = $validated['month'] ?? $period['month'];

            return $this->upsertMilkProductionAction->execute(
                productionCode: null,
                centerId: $centerId,
                partnerId: $validated['partner_id'] ?? null,
                farmerId: $validated['farmer_id'] ?? null,
                transactionDate: $period['transactionDate'],
                durationStart: $period['durationStart'],
                durationEnd: $period['durationEnd'],
                totalVolume: isset($validated['total_volume_produced']) ? (float) $validated['total_volume_produced'] : null,
                volumeFromFarmers: null,
                milkingTimes: MilkingTimeEnum::DEFAULT,
                numberOfLactating: null,
                avgMilkPerAnimalPerDay: null,
                program: $program,
                status: ProductionStatusEnum::DEFAULT,
                notes: null,
                year: $year,
                month: $month,
                quarter: (int) ceil($month / 3),
                week: $period['week'],
                idNumber: null,
                collectionDate: Sanitizer::parseDate($validated['collection_date'] ?? null),
                herdId: null,
                amVolume: null,
                pmVolume: null,
                sourceOfMilk: null,
                f1: null,
                f2: null,
            );
        });

        return to_route('milk-productions.show', $milkProduction)
            ->with('success', 'Milk production created successfully.');
    }

    public function show(MilkProduction $milkProduction)
    {
        $milkProduction->load(['center', 'partner', 'farmer', 'herd', 'usage', 'wastage', 'traded', 'sale', 'costs']);

        return Inertia::render('Productions/Milk/Show', [
            'milkProduction' => $milkProduction,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function edit(MilkProduction $milkProduction, Request $request)
    {
        $milkProduction->load(['center', 'partner', 'farmer', 'herd', 'usage', 'wastage', 'traded', 'sale', 'costs']);

        $user = $request->user();

        return Inertia::render('Productions/Milk/Edit', [
            'milkProduction' => $milkProduction,
            'centers' => Center::query()
                ->visibleTo($user)
                ->excludingHidden(Centers::hiddenCenters())
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'partners' => Partner::query()
                ->visibleTo($user)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'farmers' => Farmer::query()
                ->visibleTo($user)
                ->orderBy('last_name', 'asc')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name']),
            'herds' => Herd::query()
                ->visibleTo($user)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'center_id']),
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function update(UpdateMilkProductionRequest $request, MilkProduction $milkProduction)
    {
        $validated = $request->validated();

        $centerId = $validated['center_id'] ?? null;
        if (! $centerId && ! empty($validated['partner_id'])) {
            $partner = Partner::query()->find($validated['partner_id']);
            $centerId = $partner?->center_id;
        }

        $year = $validated['year'] ?? $milkProduction->year;
        $month = $validated['month'] ?? $milkProduction->month;

        $milkProduction->update([
            'program' => $validated['program'] ?? ProgramEnum::DEFAULT->value,
            'center_id' => $centerId,
            'partner_id' => $validated['partner_id'] ?? null,
            'farmer_id' => $validated['farmer_id'] ?? null,
            'total_volume_produced' => $validated['total_volume_produced'] ?? null,
            'year' => $year,
            'month' => $month,
            'quarter' => (int) ceil($month / 3),
        ]);

        return to_route('milk-productions.show', $milkProduction)
            ->with('success', 'Milk production updated successfully.');
    }

    public function destroy(Request $request, MilkProduction $milkProduction)
    {
        $milkProduction->delete();

        $query = $request->input('_query', []);
        $params = is_array($query) ? $query : [];

        return to_route('milk-productions.index', $params)
            ->with('success', 'Milk production deleted successfully.');
    }

    public function exportPdf(Request $request)
    {
        // PDF download is an export, so it is closed to any signed-in account
        // that holds neither export permission.
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        $dashboard = $this->resolveDashboardParams($request);

        $filters = $this->pinExportFiltersToOwnCenter($request);

        $dashboardData = $this->milkProductionDashboardScorecardQuery->execute(
            year: $dashboard['year'],
            month: $dashboard['month'],
            reportType: $dashboard['reportType'],
            filters: $filters,
        );

        $filename = 'Milk_Production_Dashboard_'.now()->format('Y-m-d_His').'.pdf';

        $pdf = Pdf::view('pdfs.MilkProductionDashboard', [
            'dashboard' => $dashboardData,
            'reportType' => $dashboard['reportType']->value,
        ]);

        $pdf->withBrowsershot(function (Browsershot $browsershot) {
            $this->configureBrowsershot($browsershot);
        });

        return $pdf->download($filename);
    }

    /**
     * The submitted dashboard filters, forced to the caller's own center when
     * they hold only 'export-own-center'.
     *
     * Without this an encoder could name any center in filter[center_id] and
     * export the national scorecard, which is why this export never relied on
     * the submitted filters alone.
     *
     * @return array<string, mixed>
     */
    private function pinExportFiltersToOwnCenter(Request $request): array
    {
        $filters = $request->query('filter', []);

        if ($request->user()?->canExportData()) {
            return $filters;
        }

        abort_if($request->user()->center_id === null, 403, 'Your account is not assigned to a center.');

        $filters['center_id'] = $request->user()->center_id;

        return $filters;
    }

    public function queueExportPdf(
        Request $request,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        // Queuing a PDF produces a downloadable file, so it carries the same
        // gate as the synchronous export.
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        $dashboard = $this->resolveDashboardParams($request);

        // Same pinning as exportPdf, so a queued PDF cannot widen its own scope.
        $filters = $this->pinExportFiltersToOwnCenter($request);

        $timestamp = now()->format('Ymd');
        $basename = "milk-production-dashboard-{$dashboard['reportType']->value}-{$timestamp}";
        $storageKey = "exports/reports/{$basename}.pdf";

        $document = $createDocumentAction->execute(
            title: Str::headline(str_replace('-', ' ', $basename)),
            fileName: "{$basename}.pdf",
            fileKey: $storageKey,
            fileType: 'application/pdf',
            fileSize: 0,
            uploadedBy: $request->user()->id,
            description: 'Export for Milk Production Dashboard PDF.',
            status: DocumentStatusEnum::PROCESSING,
            metadata: [
                'queued_export' => [
                    'report_type' => 'milk-production-dashboard',
                    'dashboard_report_type' => $dashboard['reportType']->value,
                    'dashboard_year' => $dashboard['year'],
                    'dashboard_month' => $dashboard['month'],
                    'download_as' => 'pdf',
                    'requested_at' => now()->toIso8601String(),
                    'filters' => $filters,
                ],
            ],
        );

        foreach ([
            ['key' => 'document', 'value' => 'processing'],
            ['key' => 'report_type', 'value' => 'milk-production-dashboard'],
        ] as $tag) {
            $createTagAction->execute(
                key: $tag['key'],
                value: $tag['value'],
                taggableType: Str::of(class_basename(Document::class))->lower(),
                taggableId: $document->id
            );
        }

        return response()->json([
            'message' => 'Export queued successfully.',
            'document_id' => $document->id,
        ], 202);
    }

    private function configureBrowsershot(Browsershot $browsershot): void
    {
        $nodePath = $this->browserShotEnvironmentService->getNodePath();
        $npmPath = $this->browserShotEnvironmentService->getNpmPath();
        $chromePath = $this->browserShotEnvironmentService->getChromePath();
        $chromeArguments = $this->browserShotEnvironmentService->getChromeArguments();

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
    }

    private function resolveTransactionPeriod(array $validated): ?array
    {
        $transactionDate = Sanitizer::parseDate($validated['transaction_date'] ?? null)
            ?? Sanitizer::parseDate($validated['collection_date'] ?? null);

        if (! $transactionDate instanceof \DateTimeInterface) {
            // If no date provided, use year and month to create a default date (first day of month)
            $year = $validated['year'] ?? now()->year;
            $month = $validated['month'] ?? now()->month;
            $transactionDate = \DateTime::createFromFormat('Y-m-d', "{$year}-{$month}-01");
        }

        if (! $transactionDate instanceof \DateTimeInterface) {
            return null;
        }

        return [
            'transactionDate' => $transactionDate,
            'durationStart' => $transactionDate,
            'durationEnd' => $transactionDate,
            'dateForPeriod' => $transactionDate,
            'week' => MilkProductionWeekEnum::fromDate($transactionDate),
            'year' => (int) $transactionDate->format('Y'),
            'month' => (int) $transactionDate->format('m'),
            'quarter' => (int) ceil($transactionDate->format('m') / 3),
        ];
    }

    private function resolveDashboardParams(Request $request): array
    {
        $year = (int) ($request->query('dashboard_year')
            ?? MilkProduction::query()->max('year')
            ?? now()->year);
        $month = (int) ($request->query('dashboard_month')
            ?? now()->month);
        $reportType = MilkProductionDataTypeEnum::tryFroMatches((string) $request->query('dashboard_report_type'))
            ?? MilkProductionDataTypeEnum::PRODUCTION;

        if (! in_array($reportType, [MilkProductionDataTypeEnum::PRODUCTION, MilkProductionDataTypeEnum::SALES], true)) {
            $reportType = MilkProductionDataTypeEnum::PRODUCTION;
        }

        return ['year' => $year, 'month' => $month, 'reportType' => $reportType];
    }

    private function upsertRelatedMilkData(
        MilkProduction $milkProduction,
        array $validated,
        ?int $centerId,
        array $period,
    ): void {
        $this->upsertMilkUsageAction->execute(
            milkProductionId: $milkProduction->id,
            fedToCalves: isset($validated['milk_fed_to_calves']) ? (float) $validated['milk_fed_to_calves'] : 0,
            familyConsumption: isset($validated['family_milk_consumption']) ? (float) $validated['family_milk_consumption'] : 0,
        );

        $this->upsertMilkWastageAction->execute(
            milkProductionId: $milkProduction->id,
            milkSpoiledVolume: isset($validated['milk_spoiled']) ? (float) $validated['milk_spoiled'] : 0,
        );

        $traded = isset($validated['milk_traded']) ? (float) $validated['milk_traded'] : 0.0;
        $this->upsertMilkTradedAction->execute(
            milkProductionId: $milkProduction->id,
            centerId: $centerId,
            partnerId: $validated['partner_id'] ?? null,
            farmerId: $validated['farmer_id'] ?? null,
            transactionDate: $period['transactionDate'],
            year: $period['year'],
            month: $period['month'],
            quarter: $period['quarter'],
            week: $period['week']->value,
            periodStart: $period['transactionDate']->format('Y-m-d'),
            periodEnd: $period['transactionDate']->format('Y-m-d'),
            totalVolumeTraded: $traded
        );

        $pricePerKg = isset($validated['price_per_kg']) ? (float) $validated['price_per_kg'] : 0.0;
        $rawMilkTradedSales = $traded > 0 && $pricePerKg > 0 ? $traded * $pricePerKg : 0.0;
        $valueOfTotalMilkProduction = isset($validated['total_volume_produced']) && $pricePerKg > 0
            ? ((float) $validated['total_volume_produced']) * $pricePerKg
            : 0.0;

        $this->upsertMilkSaleAction->execute(
            milkProductionId: $milkProduction->id,
            centerId: $centerId,
            partnerId: $validated['partner_id'] ?? null,
            farmerId: $validated['farmer_id'] ?? null,
            transactionDate: $period['transactionDate'],
            year: $period['year'],
            month: $period['month'],
            quarter: $period['quarter'],
            week: $period['week']->value,
            periodStart: $period['transactionDate']->format('Y-m-d'),
            periodEnd: $period['transactionDate']->format('Y-m-d'),
            totalVolumeSold: 0,
            farmGatePrice: $pricePerKg,
            rawMilkTradedSales: $rawMilkTradedSales,
            valueAddedProductsSales: 0,
            dairyBoxSales: 0,
            milkSales: 0,
            milkFeedingSales: 0,
            salesOfValueAdded: isset($validated['sales_of_value_added_milk']) ? (float) $validated['sales_of_value_added_milk'] : 0,
            valueOfTotalMilkProduction: $valueOfTotalMilkProduction
        );

        $this->upsertMilkCostAction->execute(
            milkProductionId: $milkProduction->id,
            rawMilkCost: isset($validated['value_of_total_milk_prodn_purchased']) ? (float) $validated['value_of_total_milk_prodn_purchased'] : 0,
            rawMilkPrice: $pricePerKg,
            purchasedRawMilkVolume: isset($validated['total_milk_prodn_purchased']) ? (float) $validated['total_milk_prodn_purchased'] : 0,
        );
    }
}

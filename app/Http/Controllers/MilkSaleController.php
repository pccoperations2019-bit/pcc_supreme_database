<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\MilkProductionDataTypeEnum;
use App\Models\Center;
use App\Models\MilkSale;
use App\Queries\Aggregate\AggregatedMilkSaleQuery;
use App\Queries\Reports\MilkProductionDashboardScorecardQuery;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\Filters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class MilkSaleController extends Controller
{
    public function __construct(
        private readonly MilkProductionDashboardScorecardQuery $milkProductionDashboardScorecardQuery,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = MilkSale::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_milk_sales_view' => $view]);
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
            $query = MilkSale::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $milkSales = QueryBuilder::for($query)
                ->with(['center', 'partner', 'farmer'])
                ->when(request()->get('search'), function ($query, $search) {
                    $query->globalSearch($search);
                })
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_volume',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('milk_sales.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $query = MilkSale::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', $filters['year']);
            }

            $query = resolve(AggregatedMilkSaleQuery::class)->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('milk_sales.center_id').' asc');

            $milkSales = QueryBuilder::for($query)
                ->with(['center'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = milk_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_volume',
                    'created_at',
                    AllowedSort::field('center.code'),
                ])
                ->paginate(15)
                ->appends($request->query());

            resolve(AggregatedMilkSaleQuery::class)->transform($milkSales, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = MilkSale::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        $response = [
            'milkSales' => $milkSales,
            'centers' => $centers,
            'years' => $years,
            'defaultYear' => $latestYear ?? now()->year,
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

        return Inertia::render('Productions/Milk/Sales/Index', $response);
    }

    private function resolveDashboardParams(Request $request): array
    {
        $year = (int) ($request->query('dashboard_year')
            ?? MilkSale::query()->max('year')
            ?? now()->year);
        $month = (int) ($request->query('dashboard_month')
            ?? now()->month);
        $reportType = MilkProductionDataTypeEnum::tryFroMatches((string) $request->query('dashboard_report_type'))
            ?? MilkProductionDataTypeEnum::SALES;

        if (! in_array($reportType, [MilkProductionDataTypeEnum::PRODUCTION, MilkProductionDataTypeEnum::SALES], true)) {
            $reportType = MilkProductionDataTypeEnum::SALES;
        }

        return ['year' => $year, 'month' => $month, 'reportType' => $reportType];
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}

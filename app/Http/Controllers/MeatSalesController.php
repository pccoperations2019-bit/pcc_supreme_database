<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreMeatSalesRequest;
use App\Http\Requests\UpdateMeatSalesRequest;
use App\Models\Center;
use App\Models\MeatSales;
use App\Queries\Aggregate\AggregatedMeatSalesQuery;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\EntryFormDefaults;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class MeatSalesController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MeatSales::class, 'meat_sale');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = MeatSales::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_meat_sales_view' => $view]);
        }

        $hiddenCodes = array_map('strtoupper', Centers::hiddenCenters($filters['program'] ?? null));

        $programFilter = $filters['program'] ?? null;

        // Auto-filter to latest year for monthly view only
        if ($view === 'monthly' && empty($filters['year']) && $latestYear) {
            $filters['year'] = (string) $latestYear;
        }

        if ($view === 'data') {
            $query = MeatSales::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $meatSales = QueryBuilder::for($query)
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
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_sales',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('meat_sales.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $query = MeatSales::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', $filters['year']);
            }

            $query = resolve(AggregatedMeatSalesQuery::class)->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('meat_sales.center_id').' asc');

            $meatSales = QueryBuilder::for($query)
                ->with(['center'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_sales',
                    'created_at',
                    AllowedSort::field('center.code'),
                ])
                ->paginate(15)
                ->appends($request->query());

            resolve(AggregatedMeatSalesQuery::class)->transform($meatSales, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = MeatSales::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('MeatSales/Index', [
            'meatSales' => $meatSales,
            'centers' => $centers,
            'years' => $years,
            'defaultYear' => $latestYear ?? now()->year,
            '_view' => $view,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $centers = Center::query()
            ->visibleTo($user)
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        return Inertia::render('MeatSales/Create', [
            'meatSale' => EntryFormDefaults::for($request),
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreMeatSalesRequest $request)
    {
        MeatSales::create($request->validated());

        return to_route('meat-sales.index')
            ->with('success', 'Meat sales created successfully.');
    }

    public function show(MeatSales $meatSale)
    {
        $meatSale->load(['center', 'partner', 'farmer']);

        return Inertia::render('MeatSales/Show', [
            'meatSale' => $meatSale,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function edit(Request $request, MeatSales $meatSale)
    {
        $centers = Center::query()
            ->visibleTo($request->user())
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $meatSale->load(['center', 'partner', 'farmer']);

        return Inertia::render('MeatSales/Edit', [
            'meatSale' => $meatSale,
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function update(UpdateMeatSalesRequest $request, MeatSales $meatSale)
    {
        $meatSale->update($request->validated());

        return to_route('meat-sales.index')
            ->with('success', 'Meat sales updated successfully.');
    }

    public function destroy(MeatSales $meatSale)
    {
        $meatSale->delete();

        return to_route('meat-sales.index')
            ->with('success', 'Meat sales deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreManureSalesRequest;
use App\Http\Requests\UpdateManureSalesRequest;
use App\Models\Center;
use App\Models\ManureSales;
use App\Queries\Aggregate\AggregatedManureSalesQuery;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\EntryFormDefaults;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ManureSalesController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ManureSales::class, 'manure_sale');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = ManureSales::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_manure_sales_view' => $view]);
        }

        $programFilter = $filters['program'] ?? null;
        $hiddenCodes = array_map('strtoupper', Centers::hiddenCenters($programFilter));

        // Auto-filter to latest year for monthly view only
        if ($view === 'monthly' && empty($filters['year']) && $latestYear) {
            $filters['year'] = (string) $latestYear;
        }

        if ($view === 'data') {
            $query = ManureSales::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $manureSales = QueryBuilder::for($query)
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
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_sales',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('manure_sales.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $query = ManureSales::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', $filters['year']);
            }

            $query = resolve(AggregatedManureSalesQuery::class)->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('manure_sales.center_id').' asc');

            $manureSales = QueryBuilder::for($query)
                ->with(['center'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_sales.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_sales',
                    'created_at',
                    AllowedSort::field('center.code'),
                ])
                ->paginate(15)
                ->appends($request->query());

            resolve(AggregatedManureSalesQuery::class)->transform($manureSales, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = ManureSales::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('ManureSales/Index', [
            'manureSales' => $manureSales,
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

        return Inertia::render('ManureSales/Create', [
            'manureSale' => EntryFormDefaults::for($request),
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreManureSalesRequest $request)
    {
        ManureSales::create($request->validated());

        return to_route('manure-sales.index')
            ->with('success', 'Manure sales created successfully.');
    }

    public function show(ManureSales $manureSale)
    {
        $manureSale->load(['center', 'partner', 'farmer']);

        return Inertia::render('ManureSales/Show', [
            'manureSale' => $manureSale,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function edit(Request $request, ManureSales $manureSale)
    {
        $centers = Center::query()
            ->visibleTo($request->user())
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $manureSale->load(['center', 'partner', 'farmer']);

        return Inertia::render('ManureSales/Edit', [
            'manureSale' => $manureSale,
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function update(UpdateManureSalesRequest $request, ManureSales $manureSale)
    {
        $manureSale->update($request->validated());

        return to_route('manure-sales.index')
            ->with('success', 'Manure sales updated successfully.');
    }

    public function destroy(ManureSales $manureSale)
    {
        $manureSale->delete();

        return back()
            ->with('success', 'Manure sales deleted successfully.');
    }
}

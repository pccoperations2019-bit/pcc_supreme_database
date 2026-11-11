<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreManureProductionRequest;
use App\Http\Requests\UpdateManureProductionRequest;
use App\Models\Center;
use App\Models\ManureProduction;
use App\Queries\Aggregate\AggregatedManureProductionQuery;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\EntryFormDefaults;
use App\Support\Filters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ManureProductionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ManureProduction::class, 'manure_production');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = ManureProduction::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_manure_productions_view' => $view]);
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
            $query = ManureProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $manureProductions = QueryBuilder::for($query)
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
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_productions.center_id)')),
                    'year',
                    'month',
                    'total_volume',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('manure_productions.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $activeCenterCodes = Centers::forActiveProgram($centerProgram)
                ->map(fn (CenterEnum $c) => $c->code())
                ->values()
                ->all();

            $query = ManureProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->whereIn('code', $activeCenterCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', $filters['year']);
            }

            $query = resolve(AggregatedManureProductionQuery::class)->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('manure_productions.center_id').' asc');

            $manureProductions = QueryBuilder::for($query)
                ->with(['center', 'partner'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = manure_productions.center_id)')),
                    'code',
                    'year',
                    'month',
                    'total_volume',
                    'created_at',
                    AllowedSort::field('center.code'),
                ])
                ->paginate(15)
                ->appends($request->query());

            resolve(AggregatedManureProductionQuery::class)->transform($manureProductions, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->where('status', ProfileStatusEnum::ACTIVE->value)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = ManureProduction::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('ManureProductions/Index', [
            'manureProductions' => $manureProductions,
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

        return Inertia::render('ManureProductions/Create', [
            'manureProduction' => EntryFormDefaults::for($request),
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreManureProductionRequest $request)
    {
        ManureProduction::create($request->validated());

        return to_route('manure-productions.index')
            ->with('success', 'Manure production created successfully.');
    }

    public function show(ManureProduction $manureProduction)
    {
        $manureProduction->load(['center', 'partner', 'farmer']);

        return Inertia::render('ManureProductions/Show', [
            'manureProduction' => $manureProduction,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function edit(Request $request, ManureProduction $manureProduction)
    {
        $centers = Center::query()
            ->visibleTo($request->user())
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $manureProduction->load(['center', 'partner', 'farmer']);

        return Inertia::render('ManureProductions/Edit', [
            'manureProduction' => $manureProduction,
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function update(UpdateManureProductionRequest $request, ManureProduction $manureProduction)
    {
        $manureProduction->update($request->validated());

        return to_route('manure-productions.index')
            ->with('success', 'Manure production updated successfully.');
    }

    public function destroy(Request $request, ManureProduction $manureProduction)
    {
        $manureProduction->delete();

        $query = $request->input('_query', []);
        $params = is_array($query) ? $query : [];

        return to_route('manure-productions.index', $params)
            ->with('success', 'Manure production deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreMeatProductionRequest;
use App\Http\Requests\UpdateMeatProductionRequest;
use App\Models\Center;
use App\Models\MeatProduction;
use App\Queries\Aggregate\AggregatedMeatProductionQuery;
use App\Sorts\NameSort;
use App\Support\Centers;
use App\Support\EntryFormDefaults;
use App\Support\Filters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class MeatProductionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MeatProduction::class, 'meat_production');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->query('filter', []);
        $latestYear = MeatProduction::query()->max('year');
        $view = $request->query('_view', 'monthly');

        if ($request->has('_view') && in_array($view, ['data', 'monthly', 'dashboard'], true)) {
            session(['_meat_productions_view' => $view]);
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
            $query = MeatProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->excludingHidden($hiddenCodes));

            $meatProductions = QueryBuilder::for($query)
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
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_productions.center_id)')),
                    'program',
                    'year',
                    'month',
                    'total_volume',
                    AllowedSort::field('center.code'),
                ])
                ->defaultSort('-year')
                ->orderByRaw(CenterEnum::codeSortExpression('meat_productions.center_id').' asc')
                ->paginate(10)
                ->appends($request->query());
        } else {
            $activeCenterCodes = Centers::forActiveProgram($centerProgram)
                ->map(fn (CenterEnum $c) => $c->code())
                ->values()
                ->all();

            $query = MeatProduction::query()
                ->visibleTo($user)
                ->whereHas('center', fn ($q) => $q->whereIn('code', $activeCenterCodes));

            // Apply year filter for monthly view
            if (! empty($filters['year'])) {
                $query->where('year', $filters['year']);
            }

            $query = resolve(AggregatedMeatProductionQuery::class)->applyTo($query);

            $query->orderByRaw(CenterEnum::codeSortExpression('meat_productions.center_id').' asc');

            $meatProductions = QueryBuilder::for($query)
                ->with(['center'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    AllowedSort::custom('center_id', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_productions.center_id)')),
                    AllowedSort::custom('name', new NameSort('(SELECT name FROM centers WHERE centers.id = meat_productions.center_id)')),
                    'code',
                    'program',
                    'year',
                    'month',
                    'total_volume',
                    'created_at',
                    AllowedSort::field('center.code'),
                ])
                ->paginate(15)
                ->appends($request->query());

            resolve(AggregatedMeatProductionQuery::class)->transform($meatProductions, $programFilter);
        }

        $centers = Center::query()
            ->visibleTo($user)
            ->where('status', ProfileStatusEnum::ACTIVE->value)
            ->excludingHidden($hiddenCodes)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = MeatProduction::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('MeatProductions/Index', [
            'meatProductions' => $meatProductions,
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

        return Inertia::render('MeatProductions/Create', [
            'meatProduction' => EntryFormDefaults::for($request),
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreMeatProductionRequest $request)
    {
        MeatProduction::create($request->validated());

        return to_route('meat-productions.index')
            ->with('success', 'Meat production created successfully.');
    }

    public function show(MeatProduction $meatProduction)
    {
        $meatProduction->load(['center', 'partner', 'farmer']);

        return Inertia::render('MeatProductions/Show', [
            'meatProduction' => $meatProduction,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function edit(Request $request, MeatProduction $meatProduction)
    {
        $centers = Center::query()
            ->visibleTo($request->user())
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $meatProduction->load(['center', 'partner', 'farmer']);

        return Inertia::render('MeatProductions/Edit', [
            'meatProduction' => $meatProduction,
            'centers' => $centers,
            'enums' => [
                'program' => ProgramEnum::options(),
            ],
        ]);
    }

    public function update(UpdateMeatProductionRequest $request, MeatProduction $meatProduction)
    {
        $meatProduction->update($request->validated());

        return to_route('meat-productions.index')
            ->with('success', 'Meat production updated successfully.');
    }

    public function destroy(Request $request, MeatProduction $meatProduction)
    {
        $meatProduction->delete();

        $query = $request->input('_query', []);
        $params = is_array($query) ? $query : [];

        return to_route('meat-productions.index', $params)
            ->with('success', 'Meat production deleted successfully.');
    }
}

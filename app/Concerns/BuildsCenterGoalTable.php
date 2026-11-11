<?php

namespace App\Concerns;

use App\Enums\CenterEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\CenterSalesGoal;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait BuildsCenterGoalTable
{
    /**
     * Field names (per-program) that indicate a "sales" goal has been set.
     */
    protected const SALES_GOAL_FIELDS = [
        'milk_sales_goal',
        'meat_sales_goal',
        'manure_sales_goal',
        'hide_sales_goals',
    ];

    /**
     * Field names (per-program) that indicate a "production" goal has been set.
     */
    protected const PRODUCTION_GOAL_FIELDS = [
        'milk_production_goal',
        'meat_meat_equivalent_goal',
        'manure_based_goal',
        'hide_production_goals',
    ];

    /**
     * Build the shared payload (goals grouped by center + year, carrying both
     * production and sales fields) for the center goals table.
     *
     * NOTE: grouping/merging happens in PHP because a "row" here spans two
     * separate tables (production + sales) keyed by center_id+year, so pagination
     * is applied in-memory after the full filtered set is loaded. This is fine at
     * current volume but will not scale indefinitely — if this table grows large,
     * consider a denormalized center_goals summary table (upserted whenever a
     * production/sales goal changes) or a UNION-based key query so pagination can
     * happen at the DB level instead.
     *
     * @param  string|null  $mode  'production' or 'sales' to filter records
     * @return array{goals: LengthAwarePaginator, centers: Collection, availableYears: array, programs: array}
     */
    protected function buildCenterGoalTableData(Request $request, ?string $mode = null): array
    {
        $user = $request->user();

        $program = $request->filled('filter.program') ? $request->input('filter.program') : null;
        $hiddenCenters = Centers::hiddenCenters($program);

        $applyFilters = function ($query) use ($request, $user) {
            return $query
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($q) => $q->where('center_id', $user->center_id)
                )
                ->when($request->filled('filter.center_id'), fn ($q) => $q->where('center_id', $request->input('filter.center_id')))
                ->when($request->filled('filter.year'), fn ($q) => $q->where('year', $request->input('filter.year')))
                ->when(
                    $request->filled('filter.program') && ProgramEnum::tryFrom($request->input('filter.program')) !== null,
                    fn ($q) => $q->where('program', $request->input('filter.program'))
                );
        };

        $productions = $applyFilters(CenterProductionGoal::query())
            ->with('center')
            ->orderByRaw(CenterEnum::codeSortExpression('center_id').' asc')
            ->orderByDesc('year')
            ->get();

        $sales = $applyFilters(CenterSalesGoal::query())
            ->with('center')
            ->orderByRaw(CenterEnum::codeSortExpression('center_id').' asc')
            ->orderByDesc('year')
            ->get();

        $salesGroups = $sales->groupBy(fn ($goal) => $goal->center_id.'|'.$goal->year);
        $productionGroups = $productions->groupBy(fn ($goal) => $goal->center_id.'|'.$goal->year);

        // Get all unique center_id|year combinations from both production and sales
        $allKeys = $productionGroups->keys()->concat($salesGroups->keys())->unique();

        $grouped = $allKeys
            ->map(function ($key) use ($productionGroups, $salesGroups) {
                [$centerId, $year] = explode('|', $key);
                $productionGroup = $productionGroups->get($key);
                $salesGroup = $salesGroups->get($key);

                // Use production goal if available, otherwise use sales goal, just to
                // source shared display fields (center, year) — not as a canonical id.
                $first = $productionGroup ? $productionGroup->first() : $salesGroup->first();

                return [
                    'center_id' => $centerId,
                    'year' => $first->year,
                    'center' => $first->center,
                    'ih' => $this->mergeGoal(
                        $productionGroup?->firstWhere('program', ProgramEnum::IH),
                        $salesGroup?->firstWhere('program', ProgramEnum::IH)
                    ),
                    'cbed' => $this->mergeGoal(
                        $productionGroup?->firstWhere('program', ProgramEnum::CBED),
                        $salesGroup?->firstWhere('program', ProgramEnum::CBED)
                    ),
                ];
            })
            ->values()
            ->reject(function ($row) use ($mode, $hiddenCenters) {
                // Filter out hidden centers
                if ($row['center'] && in_array(Str::lower($row['center']->code), $hiddenCenters, true)) {
                    return true;
                }

                if ($mode === 'sales') {
                    return ! $this->rowHasGoalValues($row, self::SALES_GOAL_FIELDS);
                }

                if ($mode === 'production') {
                    return ! $this->rowHasGoalValues($row, self::PRODUCTION_GOAL_FIELDS);
                }

                return false;
            })
            ->values()
            ->sortByDesc('year')
            ->values();

        $page = $request->integer('page', 1);
        $perPage = 15;
        $total = $grouped->count();

        $paginated = new LengthAwarePaginator(
            $grouped->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        $tenant = resolve(TenantContext::class);
        $centers = Center::query()
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->reject(fn ($center) => in_array(Str::lower($center->code), $hiddenCenters, true))
            ->values();

        $availableYears = CenterProductionGoal::query()->select('year')->distinct()
            ->union(CenterSalesGoal::query()->select('year')->distinct())
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        return [
            'goals' => $paginated,
            'centers' => $centers,
            'availableYears' => $availableYears,
            'programs' => ProgramEnum::options(),
        ];
    }

    /**
     * Whether either program's ('ih'/'cbed') merged goal data on this row has a
     * non-null value set for any of the given fields.
     */
    protected function rowHasGoalValues(array $row, array $fields): bool
    {
        foreach (['ih', 'cbed'] as $program) {
            if (! $row[$program]) {
                continue;
            }

            foreach ($fields as $field) {
                if (($row[$program][$field] ?? null) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Merge a production goal with its matching sales goal so the row carries both
     * production and sales fields under a single object.
     */
    protected function mergeGoal(?CenterProductionGoal $production, ?CenterSalesGoal $sales): ?array
    {
        if (! $production && ! $sales) {
            return null;
        }

        $base = $production instanceof CenterProductionGoal ? $production->toArray() : [];
        unset($base['center']);

        // Track each source id explicitly rather than a single ambiguous 'id',
        // since a merged row can be sourced from either or both tables.
        $base['production_id'] = $production?->id;

        if ($sales instanceof CenterSalesGoal) {
            $salesArr = $sales->toArray();
            $base['milk_sales_goal'] = $salesArr['milk_sales_goal'] ?? null;
            $base['meat_sales_goal'] = $salesArr['meat_sales_goal'] ?? null;
            $base['manure_sales_goal'] = $salesArr['manure_sales_goal'] ?? null;
            $base['hide_sales_goals'] = $salesArr['hide_sales_goals'] ?? null;
            $base['sales_id'] = $salesArr['id'] ?? null;
        }

        return $base;
    }
}

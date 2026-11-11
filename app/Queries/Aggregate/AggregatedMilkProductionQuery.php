<?php

namespace App\Queries\Aggregate;

use App\Enums\MonthEnum;
use App\Models\Center;
use App\Models\MilkProduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AggregatedMilkProductionQuery
{
    public function applyTo(Builder $query): Builder
    {
        $selects = array_merge([
            DB::raw('MIN(id) as id'),
            'center_id',
            'year',
        ], $this->monthlySumSelects());

        // No ordering here: the caller appends
        // CenterEnum::codeSortExpression() so milk rows sort by center code
        // exactly like the manure and meat production tables.
        return $query->select($selects)
            ->groupBy('center_id', 'year');
    }

    public function buildSubquery(array $filters = []): Builder
    {
        $query = MilkProduction::query()->select(array_merge([
            'center_id',
        ], $this->monthlySumSelects()));

        if (! empty($filters['program'])) {
            $query->where('program', $filters['program']);
        }
        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }
        if (! empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }
        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', $filters['partner_id']);
        }

        $query->groupBy('center_id');

        return $query;
    }

    public function applyWithCenters(array $filters = []): Builder
    {
        $subquery = $this->buildSubquery($filters);

        return Center::query()
            ->select(array_merge([
                'centers.*',
            ], $this->coalesceSelects()))
            ->leftJoinSub($subquery, 'aggregated_sub', fn ($join) => $join->on('centers.id', '=', 'aggregated_sub.center_id')
            );
    }

    public function transform(LengthAwarePaginator $paginator, ?string $programFilter = null): LengthAwarePaginator
    {
        $monthAbbrs = $this->monthAbbreviations();
        $centers = [];
        $transformed = [];

        foreach ($paginator->getCollection() as $item) {
            if ($item->relationLoaded('center') && $item->center) {
                $centers[$item->center_id] = $item->center;
            }

            $monthlyData = [];
            foreach ($monthAbbrs as $abbr) {
                $monthlyData[$abbr] = (float) ($item->$abbr ?? 0);
            }

            $totalVolume = array_sum($monthlyData);

            $transformed[] = [
                'id' => 'agg_'.$item->center_id.'_'.$item->year,
                'center_id' => $item->center_id,
                'year' => $item->year,
                'monthly_data' => $monthlyData,
                'total_volume' => $totalVolume,
            ];
        }

        $collection = collect($transformed)->transform(function ($item) use ($centers) {
            if (isset($centers[$item['center_id']])) {
                $item['center'] = $centers[$item['center_id']];
            }

            return $item;
        });

        $paginator->setCollection($collection);

        return $paginator;
    }

    public function transformCenters(LengthAwarePaginator $paginator, int $year): LengthAwarePaginator
    {
        $monthAbbrs = $this->monthAbbreviations();

        $transformed = collect($paginator->items())->map(function ($center) use ($monthAbbrs, $year) {
            $monthlyData = [];
            foreach ($monthAbbrs as $abbr) {
                $monthlyData[$abbr] = (float) ($center->$abbr ?? 0);
            }

            $totalVolume = array_sum($monthlyData);

            return [
                'id' => $totalVolume > 0 ? 'agg_'.$center->id.'_'.$year : null,
                'center_id' => $center->id,
                'year' => $year,
                'monthly_data' => $monthlyData,
                'total_volume' => $totalVolume,
                'center' => $center,
            ];
        });

        $paginator->setCollection($transformed);

        return $paginator;
    }

    private function monthlySumSelects(): array
    {
        $selects = [];
        foreach (MonthEnum::labels() as $num => $label) {
            $abbr = strtolower(substr($label, 0, 3));
            $selects[] = DB::raw("SUM(CASE WHEN month = {$num} THEN total_volume_produced ELSE 0 END) as `{$abbr}`");
        }
        $selects[] = DB::raw('SUM(total_volume_produced) as total_volume');

        return $selects;
    }

    private function coalesceSelects(string $alias = 'aggregated_sub'): array
    {
        $selects = [];
        foreach (MonthEnum::labels() as $label) {
            $abbr = strtolower(substr($label, 0, 3));
            $selects[] = DB::raw("COALESCE({$alias}.`{$abbr}`, 0) as `{$abbr}`");
        }
        $selects[] = DB::raw("COALESCE({$alias}.total_volume, 0) as total_volume");

        return $selects;
    }

    private function monthAbbreviations(): array
    {
        return collect(MonthEnum::labels())
            ->values()
            ->map(fn ($label) => strtolower(substr($label, 0, 3)))
            ->all();
    }
}

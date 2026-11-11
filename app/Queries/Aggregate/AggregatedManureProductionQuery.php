<?php

namespace App\Queries\Aggregate;

use App\Enums\MonthEnum;
use App\Enums\ProgramEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AggregatedManureProductionQuery
{
    public function applyTo(Builder $query): Builder
    {
        $cbed = ProgramEnum::CBED->value;
        $ih = ProgramEnum::IH->value;

        return $query
            ->select([
                DB::raw('MIN(id) as id'),
                'center_id',
                'year',
                DB::raw("SUM(CASE WHEN program = '{$cbed}' THEN total_volume ELSE 0 END) as cbed_total_volume"),
                DB::raw("SUM(CASE WHEN program = '{$ih}' THEN total_volume ELSE 0 END) as ih_total_volume"),
                DB::raw('SUM(total_volume) as total_volume'),
            ])
            ->groupBy('center_id', 'year');
    }

    public function transform(
        LengthAwarePaginator $paginator,
        ?string $programFilter = null
    ): LengthAwarePaginator {
        $items = $paginator->getCollection();

        if ($items->isNotEmpty()) {
            $keys = $items->map(fn ($item) => $item->center_id.'_'.$item->year)->values();

            $monthlyRows = DB::table('manure_productions')
                ->select([
                    'center_id',
                    'year',
                    'month',
                    DB::raw('SUM(total_volume) as month_volume'),
                ])
                ->whereNotNull('month')
                ->when($programFilter, fn ($q) => $q->where('program', $programFilter))
                ->whereIn(DB::raw("CONCAT(center_id, '_', year)"), $keys)
                ->groupBy('center_id', 'year', 'month')
                ->get();

            $monthlyMap = $monthlyRows
                ->groupBy(fn ($row) => $row->center_id.'_'.$row->year)
                ->map(fn ($rows) => $rows
                    ->mapWithKeys(fn ($row) => [MonthEnum::from((int) $row->month)->abbreviation() => $row->month_volume])
                    ->toArray()
                );

            $items->transform(function ($item) use ($programFilter, $monthlyMap) {
                $key = $item->center_id.'_'.$item->year;
                $item->monthly_data = $monthlyMap->get($key, []);
                $item->total_volume = (float) array_sum($item->monthly_data);

                if (! $programFilter) {
                    unset($item->cbed_total_volume, $item->ih_total_volume);
                }

                return $item;
            });
        }

        return $paginator;
    }
}

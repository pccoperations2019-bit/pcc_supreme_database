<?php

namespace App\Queries\Aggregate;

use App\Enums\MonthEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AggregatedDairyBoxSalesQuery
{
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->select([
                DB::raw('MIN(id) as id'),
                'center_id',
                'year',
                DB::raw('SUM(total_sales) as total_sales'),
            ])
            ->groupBy('center_id', 'year');
    }

    public function transform(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $items = $paginator->getCollection();

        if ($items->isNotEmpty()) {
            $keys = $items->map(fn ($item) => $item->center_id.'_'.$item->year)->values();

            $monthlyRows = DB::table('dairy_box_sales')
                ->select([
                    'center_id',
                    'year',
                    'month',
                    DB::raw('SUM(total_sales) as month_volume'),
                ])
                ->whereNotNull('month')
                ->whereIn(DB::raw("CONCAT(center_id, '_', year)"), $keys)
                ->groupBy('center_id', 'year', 'month')
                ->get();

            $monthlyMap = $monthlyRows
                ->groupBy(fn ($row) => $row->center_id.'_'.$row->year)
                ->map(fn ($rows) => $rows
                    ->mapWithKeys(fn ($row) => [MonthEnum::from((int) $row->month)->abbreviation() => $row->month_volume])
                    ->toArray()
                );

            $items->transform(function ($item) use ($monthlyMap) {
                $key = $item->center_id.'_'.$item->year;
                $item->monthly_data = $monthlyMap->get($key, []);

                return $item;
            });
        }

        return $paginator;
    }
}

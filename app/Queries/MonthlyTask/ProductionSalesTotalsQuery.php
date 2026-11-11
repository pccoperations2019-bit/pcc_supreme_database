<?php

namespace App\Queries\MonthlyTask;

use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionSalesTotalsQuery
{
    public function execute(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?int $centerId = null,
        ?User $viewer = null,
    ): Collection {
        $scopedCenterId = $this->scopedCenterId($centerId, $viewer);

        return collect(DB::query()->fromSub($this->union($fromYear, $fromMonth, $toYear, $toMonth, $scopedCenterId), 'totals')
            ->select([
                'center_id',
                'program',
                'product_type',
                'task_type',
                'year',
                'month',
                DB::raw('COALESCE(SUM(volume), 0) as volume'),
                DB::raw('COALESCE(SUM(value), 0) as value'),
                DB::raw('COUNT(*) as row_count'),
            ])
            ->groupBy('center_id', 'program', 'product_type', 'task_type', 'year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('center_id')
            ->orderBy('product_type')
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all());
    }

    public function executeByCenter(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?int $centerId = null,
        ?User $viewer = null,
    ): Collection {
        $scopedCenterId = $this->scopedCenterId($centerId, $viewer);

        return collect(DB::query()->fromSub($this->union($fromYear, $fromMonth, $toYear, $toMonth, $scopedCenterId), 'totals')
            ->select([
                'center_id',
                'program',
                'year',
                'month',
                DB::raw("COALESCE(SUM(CASE WHEN task_type = 'production' THEN volume ELSE 0 END), 0) as production_volume"),
                DB::raw("COALESCE(SUM(CASE WHEN task_type = 'sales' THEN volume ELSE 0 END), 0) as sales_volume"),
                DB::raw('COALESCE(SUM(value), 0) as value'),
                DB::raw('COUNT(*) as row_count'),
            ])
            ->groupBy('center_id', 'program', 'year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('center_id')
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all());
    }

    private function union(int $fromYear, int $fromMonth, int $toYear, int $toMonth, ?int $centerId): QueryBuilder
    {
        $from = $fromYear * 100 + $fromMonth;
        $to = $toYear * 100 + $toMonth;

        $union = null;

        foreach (MonthlyTaskProductEnum::cases() as $product) {
            foreach (MonthlyTaskTypeEnum::cases() as $taskType) {
                $table = $product->taskTable($taskType->value);

                if ($table === null) {
                    continue;
                }

                $volumeColumn = $product->measureColumn($taskType->value);

                $select = DB::table($table)
                    ->select([
                        'center_id',
                        'program',
                        DB::raw('? as product_type'),
                        DB::raw('? as task_type'),
                        'year',
                        'month',
                        DB::raw("COALESCE(SUM({$volumeColumn}), 0) as volume"),
                        DB::raw('0 as value'),
                    ])
                    ->whereRaw('(year * 100 + month) between ? and ?', [$from, $to])
                    ->groupBy('center_id', 'program', 'year', 'month');

                if ($centerId !== null) {
                    $select->where('center_id', $centerId);
                }

                $union = $union === null ? $select : $union->union($select);
            }
        }

        return $union ?? DB::query()->selectRaw('1 as center_id')->whereRaw('1 = 0');
    }

    private function scopedCenterId(?int $centerId, ?User $viewer): ?int
    {
        if ($viewer && $viewer->isCenterScoped()) {
            return $viewer->center_id;
        }

        return $centerId;
    }
}

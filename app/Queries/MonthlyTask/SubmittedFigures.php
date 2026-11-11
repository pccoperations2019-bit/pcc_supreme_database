<?php

namespace App\Queries\MonthlyTask;

use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Models\MonthlyTask;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class SubmittedFigures
{
    public static function relation(int $fromYear, int $fromMonth, int $toYear, int $toMonth): QueryBuilder
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

                $select = DB::table($table)
                    ->select('center_id', 'program', 'year', 'month')
                    ->selectRaw('? as product_type', [$product->value])
                    ->selectRaw('? as task_type', [$taskType->value])
                    ->whereRaw('(year * 100 + month) between ? and ?', [$from, $to]);

                $union = $union === null ? $select : $union->union($select);
            }
        }

        return $union ?? DB::query()->selectRaw('1 as center_id')->whereRaw('1 = 0');
    }

    public static function relationForAllPeriods(): QueryBuilder
    {
        return self::relation(1970, 1, 2100, 12);
    }

    public static function join(EloquentBuilder $query, string $alias = 'submitted', ?int $fromYear = null, ?int $fromMonth = null, ?int $toYear = null, ?int $toMonth = null): EloquentBuilder
    {
        $relation = ($fromYear !== null && $fromMonth !== null && $toYear !== null && $toMonth !== null)
            ? self::relation($fromYear, $fromMonth, $toYear, $toMonth)
            : self::relationForAllPeriods();

        return $query->leftJoinSub(
            $relation,
            $alias,
            fn ($join) => $join
                ->on($alias.'.center_id', '=', 'monthly_tasks.center_id')
                ->on($alias.'.program', '=', 'monthly_tasks.program')
                ->on($alias.'.year', '=', 'monthly_tasks.year')
                ->on($alias.'.month', '=', 'monthly_tasks.month')
                ->on($alias.'.product_type', '=', 'monthly_tasks.product_type')
                ->on($alias.'.task_type', '=', 'monthly_tasks.task_type')
        );
    }

    public static function whereStatus(EloquentBuilder $query, string $status, string $alias = 'submitted'): EloquentBuilder
    {
        $hasFigures = $alias.'.center_id is not null';
        $noDataReport = 'monthly_tasks.is_no_data = 1';

        $undecided = 'monthly_tasks.review_status is null or monthly_tasks.review_status = ?';

        return match ($status) {
            'completed' => $query->whereRaw('monthly_tasks.review_status = ?', ['approved']),
            'under_review' => $query->whereRaw($hasFigures)->whereRaw($undecided, ['submitted']),
            'returned' => $query->whereRaw($hasFigures)->whereRaw('monthly_tasks.review_status = ?', ['returned']),
            'no_data' => $query->whereRaw($noDataReport)->whereRaw($alias.'.center_id is null'),
            'pending' => $query->whereRaw('monthly_tasks.is_completed = 0')->whereRaw('monthly_tasks.is_no_data = 0')->whereRaw($alias.'.center_id is null'),
            'overdue' => $query->whereRaw('monthly_tasks.is_completed = 0')
                ->whereRaw('monthly_tasks.is_no_data = 0')
                ->whereRaw($alias.'.center_id is null')
                ->whereRaw('(monthly_tasks.year * 100 + monthly_tasks.month) < ?', [
                    MonthlyTask::overdueBeforePeriod(),
                ]),
            default => $query,
        };
    }
}

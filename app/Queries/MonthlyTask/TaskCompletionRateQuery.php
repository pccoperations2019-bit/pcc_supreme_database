<?php

namespace App\Queries\MonthlyTask;

use App\Enums\MonthlyTaskReviewStatusEnum;
use App\Models\MonthlyTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskCompletionRateQuery
{
    public function executeWithTrend(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?User $viewer = null,
    ): array {
        $rows = $this->aggregate(
            $fromYear,
            $fromMonth,
            $toYear,
            $toMonth,
            $viewer,
            ['year', 'month'],
        );

        $trend = $rows->map(function (array $row) {
            $total = (int) $row['total'];
            $resolved = (int) $row['completed'] + (int) $row['no_data'];

            return [
                'year' => (int) $row['year'],
                'month' => (int) $row['month'],
                'total' => $total,
                'completed' => (int) $row['completed'],
                'completion_rate' => $total > 0 ? round($resolved / $total * 100, 2) : 0.0,
            ];
        })->values();

        return [
            'summary' => $this->summarise($this->aggregate(
                $fromYear,
                $fromMonth,
                $toYear,
                $toMonth,
                $viewer,
                [],
            )),
            'trend' => $trend,
        ];
    }

    /**
     * Summary cards shown above the task list.
     *
     * Mirrors the list's own filters - center, program, product, task type,
     * period and search - so the cards describe what is on screen. Status and
     * overdue are intentionally ignored, being the breakdown the cards report.
     * With no year filter the current year is used, so the cards never count
     * the whole history at once.
     *
     * @param  array<string, mixed>  $filters
     */
    public function summaryFor(array $filters, ?User $viewer = null): array
    {
        [$fromYear, $fromMonth, $toYear, $toMonth] = $this->periodFrom($filters);

        return $this->summarise($this->aggregate(
            $fromYear,
            $fromMonth,
            $toYear,
            $toMonth,
            $viewer,
            [],
            $filters['center_id'] ?? null,
            $filters,
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function periodFrom(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? 0);

        if (! empty($filters['year']) && $month >= 1 && $month <= 12) {
            return [$year, $month, $year, $month];
        }

        return [$year, 1, $year, 12];
    }

    public function execute(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?int $centerId = null,
        ?User $viewer = null,
    ): Collection {
        return $this->aggregate($fromYear, $fromMonth, $toYear, $toMonth, $viewer, ['center_id'], $centerId)
            ->map(fn (array $row) => $this->summarise([$row]) + [
                'center_id' => (int) $row['center_id'],
            ])
            ->sortBy('center_id')
            ->values();
    }

    public function executeByProduct(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?User $viewer = null,
    ): Collection {
        return $this->aggregate($fromYear, $fromMonth, $toYear, $toMonth, $viewer, ['product_type'])
            ->map(fn (array $row) => $this->summarise([$row]) + [
                'product_type' => (string) $row['product_type'],
            ])
            ->sortBy('product_type')
            ->values();
    }

    /**
     * One aggregated row per group of tasks, counted in the database rather
     * than by loading every matching task into PHP.
     *
     * The `submitted` join is narrowed to distinct center/program/period/
     * product/type tuples first: a period can hold several entries, and
     * joining them row-for-row would count a single task once per entry.
     *
     * @param  list<string>  $groupBy
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregate(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        ?User $viewer,
        array $groupBy,
        ?int $centerId = null,
        array $filters = [],
    ): Collection {
        $from = $fromYear * 100 + $fromMonth;
        $to = $toYear * 100 + $toMonth;
        $threshold = MonthlyTask::overdueBeforePeriod();
        $approved = MonthlyTaskReviewStatusEnum::APPROVED->value;
        $returnedStatus = MonthlyTaskReviewStatusEnum::RETURNED->value;

        $submitted = DB::query()
            ->fromSub(SubmittedFigures::relation($fromYear, $fromMonth, $toYear, $toMonth), 'submitted_src')
            ->select('center_id', 'program', 'year', 'month', 'product_type', 'task_type')
            ->distinct();

        $hasFigures = 'submitted.center_id IS NOT NULL';
        $noFigures = 'submitted.center_id IS NULL';
        $isApproved = "COALESCE(monthly_tasks.review_status = '$approved', 0) = 1";
        $isReturned = "COALESCE(monthly_tasks.review_status = '$returnedStatus', 0) = 1";

        $completed = "($isApproved OR ($noFigures AND monthly_tasks.is_completed = 1))";
        $returned = "($hasFigures AND $isReturned)";
        $underReview = "($hasFigures AND NOT $isApproved AND NOT $isReturned)";
        $noData = "($noFigures AND monthly_tasks.is_completed = 0 AND monthly_tasks.is_no_data = 1)";
        $pending = "(NOT $completed AND NOT $underReview AND NOT $returned AND NOT $noData)";
        $overdue = "($pending AND (monthly_tasks.year * 100 + monthly_tasks.month) < $threshold)";

        $query = DB::table('monthly_tasks')
            ->leftJoinSub($submitted, 'submitted', function ($join) {
                $join->on('submitted.center_id', '=', 'monthly_tasks.center_id')
                    ->on('submitted.program', '=', 'monthly_tasks.program')
                    ->on('submitted.year', '=', 'monthly_tasks.year')
                    ->on('submitted.month', '=', 'monthly_tasks.month')
                    ->on('submitted.product_type', '=', 'monthly_tasks.product_type')
                    ->on('submitted.task_type', '=', 'monthly_tasks.task_type');
            })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN $completed THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN $underReview THEN 1 ELSE 0 END) as under_review")
            ->selectRaw("SUM(CASE WHEN $returned THEN 1 ELSE 0 END) as returned")
            ->selectRaw("SUM(CASE WHEN $noData THEN 1 ELSE 0 END) as no_data")
            ->selectRaw("SUM(CASE WHEN $pending THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN $overdue THEN 1 ELSE 0 END) as overdue")
            ->whereRaw('(monthly_tasks.year * 100 + monthly_tasks.month) between ? and ?', [$from, $to])
            ->when($centerId, fn ($q) => $q->where('monthly_tasks.center_id', $centerId))
            ->when($filters['program'] ?? null, fn ($q, $program) => $q->where('monthly_tasks.program', $program))
            ->when($filters['product_type'] ?? null, fn ($q, $product) => $q->where('monthly_tasks.product_type', $product))
            ->when($filters['task_type'] ?? null, fn ($q, $taskType) => $q->where('monthly_tasks.task_type', $taskType))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                $q->whereIn('monthly_tasks.center_id', function ($sub) use ($like) {
                    $sub->from('centers')->select('id')
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when(
                $viewer && $viewer->isCenterScoped(),
                fn ($q) => $viewer->center_id === null
                    ? $q->whereRaw('1 = 0')
                    : $q->where('monthly_tasks.center_id', $viewer->center_id)
            );

        if ($groupBy !== []) {
            $query->selectRaw(implode(', ', array_map(
                fn (string $column) => "monthly_tasks.$column as $column",
                $groupBy,
            )));

            $query->groupBy(array_map(fn (string $column) => "monthly_tasks.$column", $groupBy));
        }

        return $query->get()->map(fn ($row) => (array) $row)->values();
    }

    private function summarise(array|Collection $rows): array
    {
        $rows = $rows instanceof Collection ? $rows->all() : $rows;

        $sum = fn (string $key) => (int) array_sum(array_column($rows, $key));

        $total = $sum('total');
        $resolved = $sum('completed') + $sum('no_data');

        return [
            'total' => $total,
            'completed' => $sum('completed'),
            'under_review' => $sum('under_review'),
            'returned' => $sum('returned'),
            'no_data' => $sum('no_data'),
            'pending' => $sum('pending'),
            'overdue' => $sum('overdue'),
            'completion_rate' => $total > 0 ? round($resolved / $total * 100, 2) : 0.0,
        ];
    }
}

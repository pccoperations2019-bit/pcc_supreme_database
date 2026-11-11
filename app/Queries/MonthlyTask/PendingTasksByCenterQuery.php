<?php

namespace App\Queries\MonthlyTask;

use App\Models\MonthlyTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PendingTasksByCenterQuery
{
    public function execute(
        ?int $centerId = null,
        ?User $viewer = null,
        ?string $productType = null,
        ?string $taskType = null,
        int $limit = 500,
    ): Collection {
        $tasks = SubmittedFigures::join(MonthlyTask::query())
            ->whereRaw('submitted.center_id is null')
            ->where('monthly_tasks.is_completed', false)
            ->where('monthly_tasks.is_no_data', false)
            ->with(['center:id,name,code'])
            ->when(
                $centerId,
                fn (Builder $query) => $query->where('monthly_tasks.center_id', $centerId)
            )
            ->when(
                $viewer && $viewer->isCenterScoped(),
                fn (Builder $query) => $query->where('monthly_tasks.center_id', $viewer->center_id)
            )
            ->when($productType, fn (Builder $query) => $query->where('monthly_tasks.product_type', $productType))
            ->when($taskType, fn (Builder $query) => $query->where('monthly_tasks.task_type', $taskType))
            ->orderByDesc('monthly_tasks.year')
            ->orderByDesc('monthly_tasks.month')
            ->orderBy('monthly_tasks.center_id')
            ->limit($limit)
            ->get(['monthly_tasks.*']);

        return $tasks
            ->sortBy(fn (MonthlyTask $task) => [
                $task->isPastDue() ? 0 : 1,
                $task->dueDate()->timestamp,
                $task->center_id,
            ])
            ->values();
    }

    public function countsByCenter(?User $viewer = null): Collection
    {
        $query = SubmittedFigures::join(MonthlyTask::query())
            ->whereRaw('submitted.center_id is null')
            ->where('monthly_tasks.is_completed', false)
            ->where('monthly_tasks.is_no_data', false)
            ->when(
                $viewer && $viewer->isCenterScoped(),
                fn (Builder $query) => $query->where('monthly_tasks.center_id', $viewer->center_id)
            )
            ->groupBy('monthly_tasks.center_id')
            ->selectRaw('monthly_tasks.center_id, COUNT(*) as pending')
            ->selectRaw('SUM(CASE WHEN (monthly_tasks.year * 100 + monthly_tasks.month) < ? THEN 1 ELSE 0 END) as overdue', [
                MonthlyTask::overdueBeforePeriod(),
            ]);

        return $query
            ->get()
            ->map(fn ($row) => [
                'center_id' => (int) $row->center_id,
                'pending' => (int) $row->pending,
                'overdue' => (int) $row->overdue,
            ])
            ->sortByDesc('overdue')
            ->values();
    }
}

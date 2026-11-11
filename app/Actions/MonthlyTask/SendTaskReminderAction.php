<?php

namespace App\Actions\MonthlyTask;

use App\Enums\UserStatusEnum;
use App\Models\MonthlyTask;
use App\Models\User;
use App\Queries\MonthlyTask\PendingTasksByCenterQuery;
use Illuminate\Support\Collection;

class SendTaskReminderAction
{
    public function __construct(
        private readonly PendingTasksByCenterQuery $pendingTasks = new PendingTasksByCenterQuery,
    ) {}

    public function execute(?int $centerId = null, ?User $viewer = null): Collection
    {
        $tasks = $this->pendingTasks->execute(centerId: $centerId, viewer: $viewer);

        $centerIds = $tasks->pluck('center_id')->unique()->values();
        $recipientsByCenter = $this->recipientsFor($centerIds);

        return $tasks
            ->groupBy(fn (MonthlyTask $task) => $task->center_id)
            ->map(fn (Collection $centerTasks) => [
                'center_id' => (int) $centerTasks->first()->center_id,
                'center_name' => (string) $centerTasks->first()->center?->name,
                'center_code' => $centerTasks->first()->center?->code,
                'recipients' => $recipientsByCenter->get((int) $centerTasks->first()->center_id, collect()),
                'tasks' => $centerTasks->map(fn (MonthlyTask $task) => $this->describe($task))->values(),
                'overdue_count' => $centerTasks->filter(fn (MonthlyTask $task) => $task->isPastDue())->count(),
            ])
            ->sortByDesc(fn (array $entry) => $entry['overdue_count'])
            ->values();
    }

    private function recipientsFor(Collection $centerIds): Collection
    {
        if ($centerIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('center_id', $centerIds)
            ->whereNotNull('email')
            ->where('status', UserStatusEnum::ACTIVE->value)
            ->get()
            ->filter(fn (User $user) => $user->can('update-monthly-tasks'))
            ->groupBy('center_id');
    }

    private function describe(MonthlyTask $task): array
    {
        return [
            'id' => $task->getKey(),
            'program' => $task->program->value,
            'product_type' => $task->product_type->value,
            'task_type' => $task->task_type->value,
            'period' => $task->period(),
            'due_date' => $task->dueDate()->toDateString(),
            'status' => $task->status()->value,
            'is_overdue' => $task->isPastDue(),
            'days_until_due' => $task->daysUntilDue(),
        ];
    }
}

<?php

namespace App\Policies;

use App\Models\MonthlyTask;
use App\Models\User;

class MonthlyTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-monthly-tasks');
    }

    /**
     * The agency-level completion report, which reads across every center.
     */
    public function viewReports(User $user): bool
    {
        return $user->can('view-monthly-task-reports');
    }

    public function view(User $user, MonthlyTask $monthlyTask): bool
    {
        return $user->can('view-monthly-tasks')
            && $this->belongsToUsersCenter($user, $monthlyTask);
    }

    public function create(User $user): bool
    {
        return $user->can('generate-monthly-tasks');
    }

    public function update(User $user, MonthlyTask $monthlyTask): bool
    {
        return $user->can('update-monthly-tasks')
            && $this->belongsToUsersCenter($user, $monthlyTask);
    }

    public function review(User $user, MonthlyTask $monthlyTask): bool
    {
        return $user->can('review-monthly-tasks')
            && $this->belongsToUsersCenter($user, $monthlyTask);
    }

    public function delete(User $user, MonthlyTask $monthlyTask): bool
    {
        return $user->can('generate-monthly-tasks')
            && $this->belongsToUsersCenter($user, $monthlyTask);
    }

    private function belongsToUsersCenter(User $user, MonthlyTask $monthlyTask): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $monthlyTask->center_id;
    }
}

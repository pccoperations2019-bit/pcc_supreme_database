<?php

namespace App\Actions\MonthlyTask;

use App\Models\MonthlyTask;
use App\Models\User;

class MarkTaskCompleteAction
{
    public function execute(MonthlyTask $task, bool $noDataReported, ?User $actor = null): MonthlyTask
    {
        $actor ??= auth()->user();

        if ($noDataReported) {
            $task->markAsNoData($actor);
        } else {
            $task->clearNoDataReport();
        }

        return $task->refresh();
    }
}

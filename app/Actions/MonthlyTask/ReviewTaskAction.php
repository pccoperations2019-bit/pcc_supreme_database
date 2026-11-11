<?php

namespace App\Actions\MonthlyTask;

use App\Models\MonthlyTask;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReviewTaskAction
{
    public function approve(MonthlyTask $task, ?User $reviewer = null, ?string $note = null): MonthlyTask
    {
        $reviewer ??= auth()->user();

        $task->markAsApproved($reviewer, $note);

        return $task->refresh();
    }

    public function returnFigures(MonthlyTask $task, ?User $reviewer = null, string $reason = ''): MonthlyTask
    {
        $reviewer ??= auth()->user();

        if (trim($reason) === '' || strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages([
                'return_reason' => 'Say what needs correcting, so the center knows what to fix.',
            ]);
        }

        $task->markAsReturned($reviewer, trim($reason));

        return $task->refresh();
    }
}

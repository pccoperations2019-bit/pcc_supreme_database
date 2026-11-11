<?php

namespace App\Support;

use App\Enums\MonthlyTaskReviewStatusEnum;
use App\Enums\MonthlyTaskStatusEnum;
use App\Models\MonthlyTask;

class MonthlyTaskPresenter
{
    public static function describe(MonthlyTask $task): array
    {
        $status = $task->status();
        $encoder = $task->encoder();
        $productionData = $task->reviewProductionData();
        $total = $task->totalVolume();
        $review = $task->reviewStatus();

        return [
            ...$task->toArray(),
            'period' => $task->period(),
            'due_date' => $task->dueDate()->toDateString(),
            'days_until_due' => $task->daysUntilDue(),
            'has_entered_data' => $task->hasEnteredData(),
            'total_volume' => $total,
            'total_formatted' => $total !== null ? number_format($total, 2) : '—',
            'encoder_name' => $encoder?->name ?? '—',
            'encoder_role' => $encoder?->getRoleNames()->first() ?? '—',
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_badge_class' => $status->badgeClass(),
            'is_resolved' => $status->isResolved(),
            'is_no_data_report' => $status === MonthlyTaskStatusEnum::NO_DATA,
            'review_status' => $review?->value,
            'review_status_label' => $review?->label(),
            'review_status_badge_class' => $review?->badgeClass(),
            'is_awaiting_review' => $task->isAwaitingReview(),
            'is_approved' => $task->isApproved(),
            'return_reason' => $task->review_status === MonthlyTaskReviewStatusEnum::RETURNED->value
                ? $task->return_reason
                : null,
            'review_note' => $task->review_status === MonthlyTaskReviewStatusEnum::APPROVED->value
                ? $task->review_note
                : null,
            'reviewed_by_name' => $task->reviewedBy?->name,
            'reviewed_by_role' => $task->reviewedBy?->getRoleNames()->first(),
            'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            'submitted_at' => $task->submitted_at?->toDateString(),
            'production_data_rows' => $productionData->all(),
        ];
    }

    public static function describeList(MonthlyTask $task): array
    {
        $status = $task->status();
        $total = $task->totalVolume();
        $review = $task->reviewStatus();
        $productionData = $task->reviewProductionData();

        return [
            ...$task->toArray(),
            'period' => $task->period(),
            'due_date' => $task->dueDate()->toDateString(),
            'days_until_due' => $task->daysUntilDue(),
            'has_entered_data' => $task->hasEnteredData(),
            'total_volume' => $total,
            'total_formatted' => $total !== null ? number_format($total, 2) : '—',
            'encoder_name' => '—',
            'encoder_role' => '—',
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_badge_class' => $status->badgeClass(),
            'is_resolved' => $status->isResolved(),
            'is_no_data_report' => $status === MonthlyTaskStatusEnum::NO_DATA,
            'review_status' => $review?->value,
            'review_status_label' => $review?->label(),
            'review_status_badge_class' => $review?->badgeClass(),
            'is_awaiting_review' => $task->isAwaitingReview(),
            'is_approved' => $task->isApproved(),
            'return_reason' => $task->review_status === MonthlyTaskReviewStatusEnum::RETURNED->value
                ? $task->return_reason
                : null,
            'review_note' => $task->review_status === MonthlyTaskReviewStatusEnum::APPROVED->value
                ? $task->review_note
                : null,
            'reviewed_by_name' => $task->reviewedBy?->name,
            'reviewed_by_role' => $task->reviewedBy?->getRoleNames()->first(),
            'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            'submitted_at' => $task->submitted_at?->toDateString(),
            'production_data_rows' => $productionData->all(),
        ];
    }
}

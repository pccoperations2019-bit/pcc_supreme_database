<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MonthlyTaskReviewStatusEnum: string implements WithLabels
{
    use HasLabels;

    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case RETURNED = 'returned';

    public static function labels(): array
    {
        return [
            self::SUBMITTED->value => 'Awaiting review',
            self::APPROVED->value => 'Approved',
            self::RETURNED->value => 'Returned for correction',
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isAwaitingReview(): bool
    {
        return $this === self::SUBMITTED;
    }

    public function isResolved(): bool
    {
        return $this === self::APPROVED;
    }

    public static function badgeClasses(): array
    {
        return [
            self::SUBMITTED->value => 'bg-blue-100 text-blue-800 hover:bg-blue-200',
            self::APPROVED->value => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
            self::RETURNED->value => 'bg-rose-100 text-rose-800 hover:bg-rose-200',
        ];
    }

    public function badgeClass(): string
    {
        return self::badgeClasses()[$this->value] ?? 'bg-slate-100 text-slate-800';
    }
}

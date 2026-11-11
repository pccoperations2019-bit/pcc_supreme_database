<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MonthlyTaskStatusEnum: string implements WithLabels
{
    use HasLabels;

    case COMPLETED = 'completed';
    case UNDER_REVIEW = 'under_review';
    case RETURNED = 'returned';
    case PENDING = 'pending';
    case OVERDUE = 'overdue';
    case NO_DATA = 'no_data';

    public static function labels(): array
    {
        return [
            self::COMPLETED->value => 'Completed',
            self::UNDER_REVIEW->value => 'Awaiting review',
            self::RETURNED->value => 'Returned for correction',
            self::PENDING->value => 'Pending',
            self::OVERDUE->value => 'Overdue',
            self::NO_DATA->value => 'No data reported',
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isResolved(): bool
    {
        return $this === self::COMPLETED || $this === self::NO_DATA;
    }

    public static function badgeClasses(): array
    {
        return [
            self::COMPLETED->value => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
            self::UNDER_REVIEW->value => 'bg-blue-100 text-blue-800 hover:bg-blue-200',
            self::RETURNED->value => 'bg-rose-100 text-rose-800 hover:bg-rose-200',
            self::PENDING->value => 'bg-amber-100 text-amber-800 hover:bg-amber-200',
            self::OVERDUE->value => 'bg-red-100 text-red-800 hover:bg-red-200',
            self::NO_DATA->value => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        ];
    }

    public function badgeClass(): string
    {
        return self::badgeClasses()[$this->value] ?? 'bg-slate-100 text-slate-800';
    }
}

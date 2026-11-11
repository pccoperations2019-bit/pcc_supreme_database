<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProductionStatusEnum: string implements WithLabels
{
    use HasLabels;

    public const DEFAULT = self::DRAFT;

    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';

    public static function labels(): array
    {
        return [
            self::DRAFT->value => 'Draft',
            self::SUBMITTED->value => 'Submitted',
            self::APPROVED->value => 'Approved',
            self::REJECTED->value => 'Rejected',
            self::COMPLETED->value => 'Completed',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::COMPLETED => 'Completed',
        };
    }
}

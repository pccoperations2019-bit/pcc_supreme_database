<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum PartnerStatusEnum: string implements WithLabels
{
    use HasLabels;

    public const DEFAULT = self::ACTIVE;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => 'Active',
            self::INACTIVE->value => 'Inactive',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithSelectableOptions;

enum ProfileStatusEnum: string implements WithLabels, WithSelectableOptions
{
    use HasLabels, HasSelectableOptions;

    const DEFAULT = self::ACTIVE;
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => 'Active',
            self::INACTIVE->value => 'Inactive',
        ];
    }
}

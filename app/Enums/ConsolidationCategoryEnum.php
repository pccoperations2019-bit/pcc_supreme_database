<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ConsolidationCategoryEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = 'small_hold';

    case SMALL_HOLD = 'small_hold';
    case SEMI_COMMERCIAL = 'semi_commercial';
    case COMMERCIAL = 'commercial';

    public static function options(): array
    {
        return [
            self::SMALL_HOLD->value => 'Small Hold',
            self::SEMI_COMMERCIAL->value => 'Semi Commercial',
            self::COMMERCIAL->value => 'Commercial',
        ];
    }

    public static function labels(): array
    {
        return [
            self::SMALL_HOLD->value => 'Small Hold',
            self::SEMI_COMMERCIAL->value => 'Semi Commercial',
            self::COMMERCIAL->value => 'Commercial',
        ];
    }
}

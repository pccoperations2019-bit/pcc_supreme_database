<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProfileCategorySizeEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::SMALL;
    case MICRO = 'micro';
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';

    public static function labels(): array
    {
        return [
            self::MICRO->value => 'Micro',
            self::SMALL->value => 'Small',
            self::MEDIUM->value => 'Medium',
            self::LARGE->value => 'Large',
        ];
    }
}

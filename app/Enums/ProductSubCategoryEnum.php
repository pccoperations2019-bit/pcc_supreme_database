<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProductSubCategoryEnum: string implements WithLabels
{
    use HasLabels;

    case DAIRY = 'dairy';
    case WELNESS = 'wellness';

    public static function labels(): array
    {
        return [
            self::DAIRY->value => 'Dairy',
            self::WELNESS->value => 'Wellness',
        ];
    }
}

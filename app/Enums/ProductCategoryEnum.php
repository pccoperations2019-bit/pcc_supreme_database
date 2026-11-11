<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProductCategoryEnum: string implements WithLabels
{
    use HasLabels;

    case MILK = 'milk';
    case MEAT = 'meat';
    case MANURE = 'manure';
    case HIDE = 'hide';

    public static function labels(): array
    {
        return [
            self::MILK->value => 'Milk',
            self::MEAT->value => 'Meat',
            self::MANURE->value => 'Manure',
            self::HIDE->value => 'Hide',
        ];
    }
}

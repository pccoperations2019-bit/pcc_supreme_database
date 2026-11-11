<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum MilkProductionDataTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    case PRODUCTION = 'production';
    case SALES = 'sales';
    case TRADED = 'traded';

    public static function labels(): array
    {
        return [
            self::PRODUCTION->value => 'Production',
            self::SALES->value => 'Sales',
            self::TRADED->value => 'Traded',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PRODUCTION->value => ['production', 'Production', 'PRODUCTION', 'prod', 'Prod', 'PROD', 'Milk Production', 'milk production', 'MILK PRODUCTION'],
            self::SALES->value => ['sales', 'Sales', 'SALES', 'sale', 'Sale', 'SALE', 'Milk Sales', 'milk sales', 'MILK SALES'],
            self::TRADED->value => ['traded', 'Traded', 'TRADED', 'trade', 'Trade', 'TRADE', 'Milk Traded', 'milk traded', 'MILK TRADED'],
        ];
    }
}

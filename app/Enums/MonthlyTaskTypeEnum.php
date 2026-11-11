<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MonthlyTaskTypeEnum: string implements WithLabels
{
    use HasLabels;

    case PRODUCTION = 'production';
    case SALES = 'sales';

    public static function labels(): array
    {
        return [
            self::PRODUCTION->value => 'Production',
            self::SALES->value => 'Sales',
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

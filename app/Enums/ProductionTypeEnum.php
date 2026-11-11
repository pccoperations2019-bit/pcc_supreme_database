<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProductionTypeEnum: string implements WithLabels
{
    use HasLabels;

    public const DEFAULT = self::MILK_PRODUCTION;

    case MILK_PRODUCTION = 'milk_production';

    public static function labels(): array
    {
        return [
            self::MILK_PRODUCTION->value => 'Milk Production',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::MILK_PRODUCTION => 'Milk Production',
        };
    }
}

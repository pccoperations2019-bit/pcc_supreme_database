<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MilkUsageTypeEnum: string implements WithLabels
{
    use HasLabels;

    public const DEFAULT = self::FED_TO_CALVES;

    case FED_TO_CALVES = 'fed_to_calves';
    case FAMILY_CONSUMPTION = 'family_consumption';
    case MILK_FEEDING_PROGRAM = 'milk_feeding_program';

    public static function labels(): array
    {
        return [
            self::FED_TO_CALVES->value => 'Fed to Calves',
            self::FAMILY_CONSUMPTION->value => 'Family Consumption',
            self::MILK_FEEDING_PROGRAM->value => 'Milk Feeding Program',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::FED_TO_CALVES => 'Fed to Calves',
            self::FAMILY_CONSUMPTION => 'Family Consumption',
            self::MILK_FEEDING_PROGRAM => 'Milk Feeding Program',
        };
    }
}

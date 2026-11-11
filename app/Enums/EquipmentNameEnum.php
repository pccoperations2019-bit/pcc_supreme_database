<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum EquipmentNameEnum: string
{
    use HasLabels;

    case PASTEURIZER_TANK = 'pasteurizer_tank';
    case MILK_PUMP = 'milk_pump';
    case Y_STRAINER = 'y_strainer';
    case COOLING_TANK_AND_MILK_STORAGE_WITH_FILLING_NOZZLE = 'cooling_tank_and_milk_storage_with_filling_nozzle';
    case HOT_WATER_GENERATOR = 'hot_water_generator';
    case DAIRY_TUBES_AND_FITTINGS = 'dairy_tubes_and_fittings';
    case COOLING_TOWER = 'cooling_tower';

    public static function labels(): array
    {
        return [
            self::PASTEURIZER_TANK->value => 'Pasteurizer Tank',
            self::MILK_PUMP->value => 'Milk Pump',
            self::Y_STRAINER->value => 'Y Strainer',
            self::COOLING_TANK_AND_MILK_STORAGE_WITH_FILLING_NOZZLE->value => 'Cooling Tank and Milk Storage with Filling Nozzle',
            self::HOT_WATER_GENERATOR->value => 'Hot Water Generator',
            self::DAIRY_TUBES_AND_FITTINGS->value => 'Dairy Tubes and Fittings',
            self::COOLING_TOWER->value => 'Cooling Tower',
        ];
    }
}

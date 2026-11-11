<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum MilkTypeEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case PM = 'pm';
    case SMP = 'smp';
    case KARABUN = 'karabun';
    case MILK_TABLE = 'milk_table';
    case OTHERS = 'others';

    public static function labels(): array
    {
        return [
            self::PM->value => 'Pasteurized Milk',
            self::SMP->value => 'Sterilized Milk',
            self::KARABUN->value => 'Karabun',
            self::MILK_TABLE->value => 'Milk Table',
            self::OTHERS->value => 'Others',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PM->value => ['Pasteurized Milk', 'Pasteurized', 'pasteurized milk', 'PM', 'pm'],
            self::SMP->value => ['Sterilized Milk', 'Sterilized', 'sterilized milk', 'SMP', 'smp'],
            self::KARABUN->value => ['Karabun', 'karabun', 'Karabun', 'karabun'],
            self::MILK_TABLE->value => ['Milk Table', 'milk table', 'Milk Table', 'milk table'],
            self::OTHERS->value => ['Others', 'others'],
        ];
    }
}

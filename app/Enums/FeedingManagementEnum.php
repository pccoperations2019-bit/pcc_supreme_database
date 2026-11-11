<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum FeedingManagementEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::CUT_AND_CARRY;

    case CUT_AND_CARRY = 'cut_and_carry';
    case FULL_GRAZING = 'full_grazing';
    case GRAZING_CUT_AND_CARRY = 'grazing_cut_and_carry';

    public static function labels(): array
    {
        return [
            self::CUT_AND_CARRY->value => 'Cut and carry system',
            self::FULL_GRAZING->value => 'Full grazing',
            self::GRAZING_CUT_AND_CARRY->value => 'Grazing + cut and carry',
        ];
    }
}

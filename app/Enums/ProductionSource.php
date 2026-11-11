<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProductionSource: string implements WithLabels
{
    use HasLabels;

    case INSTITUTION = 'institution';
    case FARMER = 'farmer';
    case PARTNER = 'partner';

    public static function labels(): array
    {
        return [
            self::INSTITUTION->value => 'Institution',
            self::FARMER->value => 'Farmer',
            self::PARTNER->value => 'Partner',
        ];
    }
}

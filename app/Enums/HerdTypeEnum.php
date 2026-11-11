<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum HerdTypeEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = 'multiplier_farm';

    case MULTIPLIER_FARM = 'multiplier_farm';
    case COMMUNAL = 'communal';
    case BACKYARD = 'backyard';

    public static function options(): array
    {
        return [
            self::MULTIPLIER_FARM->value => 'Multiplier Farm',
            self::COMMUNAL->value => 'Communal',
            self::BACKYARD->value => 'Backyard',
        ];
    }

    public static function labels(): array
    {
        return [
            self::MULTIPLIER_FARM->value => 'Multiplier Farm',
            self::COMMUNAL->value => 'Communal',
            self::BACKYARD->value => 'Backyard',
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum EntrepreneurTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::SMALL_HOLD;

    case SMALL_HOLD = 'small_hold';
    case SEMI_COMMERCIAL = 'semi_commercial';
    case COMMERCIAL = 'commercial';
    case FAMILY_MODULE = 'family_module';

    public static function labels(): array
    {
        return [
            self::SMALL_HOLD->value => 'Small Hold',
            self::SEMI_COMMERCIAL->value => 'Semi-Commercial',
            self::COMMERCIAL->value => 'Commercial',
            self::FAMILY_MODULE->value => 'Family Module',
        ];
    }

    public static function matches(): array
    {
        return [
            self::SMALL_HOLD->value => ['Smallhold', 'Small Hold', 'smallhold', 'small hold', 'small_hold'],
            self::SEMI_COMMERCIAL->value => ['Semi-commercial', 'Semi-Commercial', 'semi commercial', 'semi-commercial', 'semi_commercial'],
            self::COMMERCIAL->value => ['Commercial', 'commercial'],
            self::FAMILY_MODULE->value => ['Family Module', 'family module', 'family_module', 'module (family)', 'family'],
        ];
    }
}

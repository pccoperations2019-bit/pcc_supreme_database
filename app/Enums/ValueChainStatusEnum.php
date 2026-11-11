<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum ValueChainStatusEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels, HasMatches, HasSelectableOptions;

    case PREPARATORY = 'preparatory';
    case PRODUCTION = 'production';
    case OPERATIONAL = 'operational';
    case GROWING = 'growing';
    case MATURE = 'mature';

    public static function labels(): array
    {
        return [
            self::PREPARATORY->value => 'Preparatory',
            self::PRODUCTION->value => 'Production',
            self::OPERATIONAL->value => 'Operational',
            self::GROWING->value => 'Growing',
            self::MATURE->value => 'Mature',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PREPARATORY->value => ['preparatory', 'Preparatory'],
            self::PRODUCTION->value => ['production', 'Production'],
            self::OPERATIONAL->value => ['operational', 'Operational'],
            self::GROWING->value => ['growing', 'Growing'],
            self::MATURE->value => ['mature', 'Mature'],
        ];
    }
}

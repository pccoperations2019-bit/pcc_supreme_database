<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum MembershipTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::NONE;

    case COOPERATIVE = 'cooperative';
    case ASSOCIATION = 'association';
    case NONE = 'none';

    public static function labels(): array
    {
        return [
            self::COOPERATIVE->value => 'Cooperative',
            self::ASSOCIATION->value => 'Association',
            self::NONE->value => 'None',
        ];
    }

    public static function matches(): array
    {
        return [
            self::COOPERATIVE->value => ['Cooperative', 'cooperative', 'coop'],
            self::ASSOCIATION->value => ['Association', 'association', 'assoc'],
            self::NONE->value => ['None', 'none', 'n/a', 'na', 'not applicable', 'no'],
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum CivilStatusEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::SINGLE;

    case SINGLE = 'single';
    case MARRIED = 'married';
    case WIDOWED = 'widowed';
    case SEPARATED = 'seperated';
    case LIVE_IN_PARTNER = 'live_in_partner';
    case SOLO_PARENT = 'solo_parent';

    public static function labels(): array
    {
        return [
            self::SINGLE->value => 'Single',
            self::MARRIED->value => 'Married',
            self::WIDOWED->value => 'Widowed',
            self::SEPARATED->value => 'Separated',
            self::LIVE_IN_PARTNER->value => 'Live-in Partner',
            self::SOLO_PARENT->value => 'Solo Parent',
        ];
    }

    public static function matches(): array
    {
        return [
            self::SINGLE->value => ['Single', 'single', 'S', 's'],
            self::MARRIED->value => ['Married', 'married', 'M', 'm'],
            self::WIDOWED->value => ['Widowed', 'widowed', 'Widow', 'widow', 'Widower', 'widower', 'Widow/er', 'widow/er'],
            self::SEPARATED->value => ['Separated', 'separated', 'Seperated', 'seperated'],
            self::LIVE_IN_PARTNER->value => ['Live-in Partner', 'Live in Partner', 'live-in partner', 'live in partner', 'common-law', 'common law', 'cohabiting', 'cohabitation'],
            self::SOLO_PARENT->value => ['Solo Parent', 'solo parent', 'single parent', 'Single Parent', 'solo-parent', 'solo parent/guardian'],
        ];
    }
}

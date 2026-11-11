<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum ProjectCategoryEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    const DEFAULT = self::CBED;

    case CBED = 'cbed';
    case PL480 = 'pl480';
    case DHB = 'dhb';
    case CBIN = 'cbin';
    case LGU_LED = 'lgu_led';
    case PRIVATE = 'private';
    case CCDP = 'ccdp';

    public static function labels(): array
    {
        return [
            self::CBED->value => 'CBED',
            self::PL480->value => 'PL480',
            self::DHB->value => 'DHB',
            self::CBIN->value => 'CBIN',
            self::LGU_LED->value => 'LGU LED',
            self::PRIVATE->value => 'Private',
            self::CCDP->value => 'CCDP',
        ];
    }

    public static function matches(): array
    {
        return [
            self::CBED->value => ['CBED', 'cbed'],
            self::PL480->value => ['PL480', 'pl480', 'pl-480', 'pl 480'],
            self::DHB->value => ['DHB', 'dhb'],
            self::CBIN->value => ['CBIN', 'cbin'],
            self::LGU_LED->value => ['LGU LED', 'lgu_led', 'lgu-led', 'lgu led'],
            self::PRIVATE->value => ['Private', 'private', 'PRIVATE'],
            self::CCDP->value => ['CCDP', 'ccdp'],
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum FarmerCategoryEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::INDEPENDENT;
    case INDEPENDENT = 'independent';
    case FCA_MEMBER = 'fca_member';

    public static function labels(): array
    {
        return [
            self::INDEPENDENT->value => 'Independent',
            self::FCA_MEMBER->value => 'FCA Member',
        ];
    }
}

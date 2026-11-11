<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ServiceCategoryEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::PRODUCTION_SUPPORT;

    case PRODUCTION_SUPPORT = 'production_support';
    case MARKETING_DEVELOPMENT = 'marketing_development';
    case IEC_AND_TRAINING = 'iec_and_training';

    public static function labels(): array
    {
        return [
            self::PRODUCTION_SUPPORT->value => 'Production Support',
            self::MARKETING_DEVELOPMENT->value => 'Marketing Development',
            self::IEC_AND_TRAINING->value => 'IEC & Training',
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum SiteTypeEnum: string implements WithLabels
{
    use HasLabels;

    case PRODUCTION_SITE = 'production_site';
    case PROCESSING = 'processing';
    case MARKETING = 'marketing';

    public static function labels(): array
    {
        return [
            self::PRODUCTION_SITE->value => 'Production Site',
            self::PROCESSING->value => 'Processing',
            self::MARKETING->value => 'Marketing',
        ];
    }
}

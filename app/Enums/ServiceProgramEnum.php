<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ServiceProgramEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::CBED;

    case CBED = 'cbed';
    case IH = 'ih';

    public static function labels(): array
    {
        return [
            self::CBED->value => 'CBED',
            self::IH->value => 'IH',
        ];
    }
}

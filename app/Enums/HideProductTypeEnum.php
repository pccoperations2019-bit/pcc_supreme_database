<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum HideProductTypeEnum: string implements WithLabels
{
    use HasLabels;

    case RAW_HIDE = 'raw_hide';
    case TANNED_HIDE = 'tanned_hide';
    case PROCESSED_LEATHER = 'processed_leather';
    case CURR_ED_HIDE = 'cured_hide';

    public static function labels(): array
    {
        return [
            self::RAW_HIDE->value => 'Raw Hide',
            self::TANNED_HIDE->value => 'Tanned Hide',
            self::PROCESSED_LEATHER->value => 'Processed Leather',
            self::CURR_ED_HIDE->value => 'Cured Hide',
        ];
    }
}

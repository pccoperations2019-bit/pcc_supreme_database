<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum EmptyValueEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::NONE;

    case NONE = 'none';
    case NOT_APPLICABLE = 'n/a';
    case UNKNOWN = 'unknown';
    case EMPTY = 'empty';
    case NULL = 'null';
    case EMPTY_STRING = 'empty_string';

    public static function labels(): array
    {
        return [
            self::NONE->value => 'None',
            self::NOT_APPLICABLE->value => 'N/A',
            self::UNKNOWN->value => 'Unknown',
            self::EMPTY->value => 'Empty',
            self::NULL->value => 'Null',
            self::EMPTY_STRING->value => ' ',
        ];
    }
}

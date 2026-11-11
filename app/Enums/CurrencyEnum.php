<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum CurrencyEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::PHP;

    case PHP = 'php';

    public static function labels(): array
    {
        return [
            self::PHP->value => 'PHP',
        ];
    }

    public static function symbols(): array
    {
        return [
            self::PHP->value => '₱',
        ];
    }
}

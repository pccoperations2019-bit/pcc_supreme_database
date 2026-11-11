<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ActiveInactiveEnum: string implements WithLabels
{
    use HasLabels;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => 'Active',
            self::INACTIVE->value => 'Inactive',
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $candidate = trim((string) $value);

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $candidate) === 0) {
                return $case->value;
            }
        }

        foreach (self::labels() as $caseValue => $label) {
            if (strcasecmp($label, $candidate) === 0) {
                return $caseValue;
            }
        }

        return null;
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum RegistrationTypeEnum: string implements WithLabels
{
    use HasLabels;

    case DOLE = 'DOLE';
    case CDA = 'CDA';
    case SEC = 'SEC';
    case OTHERS = 'OTHERS';

    public static function labels(): array
    {
        return [
            self::DOLE->value => 'DOLE',
            self::CDA->value => 'CDA',
            self::SEC->value => 'SEC',
            self::OTHERS->value => 'Others',
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

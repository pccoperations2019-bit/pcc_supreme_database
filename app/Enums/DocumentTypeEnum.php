<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum DocumentTypeEnum: string implements WithLabels
{
    use HasLabels;

    case MOA = 'moa';
    case DOD_USUFRUCT = 'dod_usufruct';
    case MANAGEMENT_AGREEMENT = 'management_agreement';
    case DAIRY_BUFFALO_DISPERSAL = 'dairy_buffalo_dispersal';

    public static function labels(): array
    {
        return [
            self::MOA->value => 'MOA',
            self::DOD_USUFRUCT->value => 'DOD/USUFRUCT',
            self::MANAGEMENT_AGREEMENT->value => 'Management Agreement Contract',
            self::DAIRY_BUFFALO_DISPERSAL->value => 'Dairy Buffalo Dispersal Agreement',
        ];
    }
}

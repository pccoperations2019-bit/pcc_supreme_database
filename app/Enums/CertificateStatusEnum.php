<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum CertificateStatusEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::NO_APPLICATION;

    case ACCREDITED = 'accredited';
    case ON_PROCESS = 'on_process';
    case FOR_ENDORSEMENT = 'for_endorsement';
    case ENDORSEMENT = 'endorsement';
    case NO_APPLICATION = 'no_application';

    public static function labels(): array
    {
        return [
            self::ACCREDITED->value => 'Accredited',
            self::ON_PROCESS->value => 'On Process',
            self::FOR_ENDORSEMENT->value => 'For Endorsement',
            self::ENDORSEMENT->value => 'Endorsement',
            self::NO_APPLICATION->value => 'No Application',
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum PartnerPartnershipTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    case ASSISTED = 'assisted';
    case CONDUIT = 'conduit';
    case PAWI = 'pawi';
    case INSTITUTION = 'institution';
    case COOP_PARTNERSHIP = 'coop_partnership';
    case DAIRY_BUFFALO_ENTRUSTMENT = 'dairy_buffalo_entrustment';

    public static function labels(): array
    {
        return [
            self::ASSISTED->value => 'Assisted',
            self::CONDUIT->value => 'Conduit',
            self::PAWI->value => 'PAWI',
            self::INSTITUTION->value => 'Institution',
            self::COOP_PARTNERSHIP->value => 'Coop Partnership',
            self::DAIRY_BUFFALO_ENTRUSTMENT->value => 'Dairy Buffalo Entrustment',
        ];
    }

    public static function matches(): array
    {
        return [
            self::ASSISTED->value => ['assisted', 'assist', 'support', 'Assisted'],
            self::CONDUIT->value => ['conduit', 'channel', 'pipeline', 'Conduit'],
            self::PAWI->value => ['pawi', 'philippine carabao', 'Pawi', 'Paiwi'],
            self::INSTITUTION->value => ['institution', 'institutional', 'org', 'organization', 'Institution'],
            self::COOP_PARTNERSHIP->value => ['coop partnership', 'cooperative partnership', 'coop', 'cooperative partnership', 'Coop Partnership'],
            self::DAIRY_BUFFALO_ENTRUSTMENT->value => ['dairy buffalo entrustment', 'dbe', 'buffalo entrustment', 'Dairy Buffalo Entrustment'],
        ];
    }
}

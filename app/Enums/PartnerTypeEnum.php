<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum PartnerTypeEnum: string implements WithLabels
{
    use HasLabels;

    case ASSISTED = 'assisted';
    case CONDUIT = 'conduit';
    case ASSOCIATION = 'association';
    case PRIVATE_INDEPENDENT = 'private_independent';
    case INSTITUTION = 'institution';
    case DAIRY_BUFFALO_ENTRUSTMENT = 'dairy_buffalo_entrustment';
    case COOPERATIVE = 'cooperative';
    case COOP_PARTNERSHIP = 'coop_partnership';
    case PAIWI = 'paiwi';
    case NGO_CHARITY = 'ngo_charity';
    case INDIVIDUAL = 'individual';

    public static function labels(): array
    {
        return [
            self::ASSISTED->value => 'Assisted',
            self::CONDUIT->value => 'Conduit',
            self::ASSOCIATION->value => 'Association',
            self::PRIVATE_INDEPENDENT->value => 'Private/Independent',
            self::INSTITUTION->value => 'Institution',
            self::DAIRY_BUFFALO_ENTRUSTMENT->value => 'Dairy Buffalo Entrustment',
            self::COOPERATIVE->value => 'Cooperative',
            self::COOP_PARTNERSHIP->value => 'Coop Partnership',
            self::PAIWI->value => 'Paiwi',
            self::NGO_CHARITY->value => 'NGO/Charity',
            self::INDIVIDUAL->value => 'Individual',
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

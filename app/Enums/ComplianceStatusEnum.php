<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum ComplianceStatusEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::FOR_PROCESSING;

    case FOR_PROCESSING = 'for_processing';
    case FOR_RENEWAL = 'for_renewal';
    case ACCREDITED = 'accredited';
    case PLATINUM = 'platinum';
    case RED_ACCREDITATION = 'red_accreditation';
    case RED_REGISTRATION = 'red_registration';
    case RETAILER_OF_BODACO = 'retailer_of_bodaco';

    public static function options(): array
    {
        return [
            self::FOR_PROCESSING->value => 'For Processing',
            self::FOR_RENEWAL->value => 'For Renewal',
            self::ACCREDITED->value => 'Accredited',
            self::PLATINUM->value => 'Platinum',
            self::RED_ACCREDITATION->value => 'Red Accreditation',
            self::RED_REGISTRATION->value => 'Red Registration',
            self::RETAILER_OF_BODACO->value => 'Retailer of BODACO',
        ];
    }

    public static function labels(): array
    {
        return self::options();
    }

    public static function matches(): array
    {
        return [
            self::FOR_PROCESSING->value => ['for processing', 'processing', 'For Processing', 'FOR_PROCESSING'],
            self::FOR_RENEWAL->value => ['for renewal', 'renewal', 'For Renewal', 'FOR_RENEWAL'],
            self::ACCREDITED->value => ['accredited', 'Accredited', 'ACCREDITED'],
            self::PLATINUM->value => ['platinum', 'Platinum', 'PLATINUM'],
            self::RED_ACCREDITATION->value => ['red accreditation', 'red acc', 'Red Accreditation', 'RED_ACCREDITATION'],
            self::RED_REGISTRATION->value => ['red registration', 'red reg', 'Red Registration only', 'Red Registration', 'RED_REGISTRATION'],
            self::RETAILER_OF_BODACO->value => ['retailer of bodaco', 'bodaco retailer', 'Retailer of BODACO', 'RETAILER_OF_BODACO', 'Retailer of Bodaco'],
        ];
    }
}

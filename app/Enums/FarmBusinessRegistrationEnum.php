<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum FarmBusinessRegistrationEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::NONE;

    case NONE = 'none';
    case LTO = 'lto';
    case DTI = 'dti';
    case MAYORS_BUSINESS_PERMIT = 'mayor_s_business_permit';
    case BRGY_BUSINESS_PERMIT = 'brgy_business_permit';

    public static function labels(): array
    {
        return [
            self::NONE->value => 'None',
            self::LTO->value => 'LTO Registration',
            self::DTI->value => 'DTI Registration',
            self::MAYORS_BUSINESS_PERMIT->value => "Mayor's Business Permit",
            self::BRGY_BUSINESS_PERMIT->value => 'Brgy. Business Permit',
        ];
    }

    public static function matches(): array
    {
        return [
            self::NONE->value => ['None', 'none', 'n/a', 'na'],
            self::LTO->value => ['LTO Registration', 'LTO', 'lto'],
            self::DTI->value => ['DTI Registration', 'DTI', 'dti'],
            self::MAYORS_BUSINESS_PERMIT->value => ["Mayor's Business Permit", 'Mayors Business Permit', "Mayor's Permit", 'Mayors Permit'],
            self::BRGY_BUSINESS_PERMIT->value => ['Brgy. Business Permit', 'Barangay Business Permit', 'Brgy Permit', 'Barangay Permit'],
        ];
    }
}

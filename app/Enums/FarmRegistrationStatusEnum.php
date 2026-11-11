<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum FarmRegistrationStatusEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::NOT_YET_REGISTERED;

    case REGISTERED = 'registered';
    case ON_GOING_APPLICATION = 'on_going_application';
    case NOT_YET_REGISTERED = 'not_yet_registered';

    public static function labels(): array
    {
        return [
            self::REGISTERED->value => 'Registered',
            self::ON_GOING_APPLICATION->value => 'On-going application',
            self::NOT_YET_REGISTERED->value => 'Not yet registered',
        ];
    }

    public static function matches(): array
    {
        return [
            self::REGISTERED->value => ['Registered', 'registered', 'Yes', 'yes'],
            self::ON_GOING_APPLICATION->value => ['On-going application', 'On going application', 'Ongoing', 'ongoing', 'In progress', 'Processing'],
            self::NOT_YET_REGISTERED->value => ['Not yet registered', 'Not registered', 'No', 'no', 'None', 'none'],
        ];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum OwnershipTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    case OWNED = 'owned';
    case DONATION = 'donation';
    case USUFRUCT = 'usufruct';
    case RENTAL = 'rental';

    public static function labels(): array
    {
        return [
            self::OWNED->value => 'Owned',
            self::DONATION->value => 'Donation',
            self::USUFRUCT->value => 'Usufruct',
            self::RENTAL->value => 'Rental',
        ];
    }

    public static function matches(): array
    {
        return [
            self::OWNED->value => ['owned', 'Owned', 'OWNED'],
            self::DONATION->value => ['donation', 'Donation', 'DONATION'],
            self::USUFRUCT->value => ['usufruct', 'Usufruct', 'USUFRUCT'],
            self::RENTAL->value => ['rental', 'Rental', 'RENTAL'],
        ];
    }
}

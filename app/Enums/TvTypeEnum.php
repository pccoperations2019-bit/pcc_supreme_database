<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum TvTypeEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::ANTENNA;

    case ANTENNA = 'antenna';
    case BLACK_BOX = 'black_box';
    case CABLE = 'cable';
    case SATELLITE = 'satellite';

    public static function labels(): array
    {
        return [
            self::ANTENNA->value => 'Antenna',
            self::BLACK_BOX->value => 'Black box',
            self::CABLE->value => 'Cable',
            self::SATELLITE->value => 'Satellite',
        ];
    }

    public static function matches(): array
    {
        return [
            self::ANTENNA->value => ['Antenna', 'antenna'],
            self::BLACK_BOX->value => ['Black box', 'black box', 'Blackbox', 'blackbox'],
            self::CABLE->value => ['Cable', 'cable'],
            self::SATELLITE->value => ['Satellite', 'satellite'],
        ];
    }
}

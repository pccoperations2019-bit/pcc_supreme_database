<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum MilkFeedingStatusEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    case PLANNED = 'planned';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            self::PLANNED->value => 'Planned',
            self::ONGOING->value => 'Ongoing',
            self::COMPLETED->value => 'Completed',
            self::CANCELLED->value => 'Cancelled',
        ];
    }

    public static function matches(): array
    {
        return [
            self::PLANNED->value => ['Planned', 'planning', 'PLANNED', 'planned'],
            self::ONGOING->value => ['Ongoing', 'in progress', 'IN PROGRESS', 'ongoing', 'ONGOING'],
            self::COMPLETED->value => ['Completed', 'complete', 'COMPLETED', 'completed', 'done', 'DONE'],
            self::CANCELLED->value => ['Cancelled', 'canceled', 'CANCELLED', 'cancelled', 'CANCELED', 'cancel'],
        ];
    }
}

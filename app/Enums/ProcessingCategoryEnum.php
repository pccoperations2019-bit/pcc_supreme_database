<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ProcessingCategoryEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = 'kitchen_type';

    case KITCHEN_TYPE = 'kitchen_type';
    case COMMERCIAL_TYPE = 'commercial_type';
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';

    public static function options(): array
    {
        return [
            self::KITCHEN_TYPE->value => 'Kitchen Type',
            self::COMMERCIAL_TYPE->value => 'Commercial Type',
            self::BEGINNER->value => 'Beginner',
            self::INTERMEDIATE->value => 'Intermediate',
        ];
    }

    public static function labels(): array
    {
        return [
            self::KITCHEN_TYPE->value => 'Kitchen Type',
            self::COMMERCIAL_TYPE->value => 'Commercial Type',
            self::BEGINNER->value => 'Beginner',
            self::INTERMEDIATE->value => 'Intermediate',
        ];
    }
}

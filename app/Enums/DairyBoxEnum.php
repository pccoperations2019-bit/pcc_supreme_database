<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum DairyBoxEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::OPERATIONAL;

    case COMPLETED = 'completed';
    case DISCONTINUED_OPERATION = 'discontinued_operation';
    case ON_GOING_CONSTRUCTION = 'on_going_construction';
    case OPERATIONAL = 'operational';

    public static function labels(): array
    {
        return [
            self::COMPLETED->value => 'Completed',
            self::DISCONTINUED_OPERATION->value => 'Discontinued Operation',
            self::ON_GOING_CONSTRUCTION->value => 'On Going Construction',
            self::OPERATIONAL->value => 'Operational',
        ];
    }

    public static function matches(): array
    {
        return [
            self::COMPLETED->value => ['Completed', 'completed', 'complete'],
            self::DISCONTINUED_OPERATION->value => ['Discontinued Operation', 'discontinued_operation', 'discontinued', 'discontinued operation'],
            self::ON_GOING_CONSTRUCTION->value => ['On Going Construction', 'Ongoing Construction', 'on_going_construction', 'ongoing construction', 'ongoing', 'construction'],
            self::OPERATIONAL->value => ['Operational', 'operational', 'operate', 'running'],
        ];
    }

    public static function getBadgeColors(): array
    {
        return [
            self::COMPLETED->value => 'bg-green-100 text-green-800 hover:bg-green-200',
            self::DISCONTINUED_OPERATION->value => 'bg-red-100 text-red-800 hover:bg-red-200',
            self::ON_GOING_CONSTRUCTION->value => 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
            self::OPERATIONAL->value => 'bg-blue-100 text-blue-800 hover:bg-blue-200',
        ];
    }

    public function getBadgeColor(): string
    {
        return self::getBadgeColors()[$this->value] ?? 'bg-gray-100 text-gray-800';
    }
}

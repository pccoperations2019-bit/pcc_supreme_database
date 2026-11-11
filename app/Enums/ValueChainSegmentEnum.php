<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ValueChainSegmentEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::PRODUCTION;

    case PRODUCTION = 'production';
    case CONSOLIDATION = 'consolidation';
    case PROCESSING = 'processing';
    case MARKETING = 'marketing';

    public static function labels(): array
    {
        return [
            self::PRODUCTION->value => 'Production',
            self::CONSOLIDATION->value => 'Consolidation',
            self::PROCESSING->value => 'Processing',
            self::MARKETING->value => 'Marketing',
        ];
    }

    public static function colors(): array
    {
        return [
            self::PRODUCTION->value => 'bg-blue-100 text-blue-800 hover:bg-blue-200',
            self::CONSOLIDATION->value => 'bg-gray-100 text-gray-800 hover:bg-gray-200',
            self::PROCESSING->value => 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
            self::MARKETING->value => 'bg-green-100 text-green-800 hover:bg-green-200',
        ];
    }

    public function color(): string
    {
        return self::colors()[$this->value];
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ManureProductTypeEnum: string implements WithLabels
{
    use HasLabels;

    case FRESH_MANURE = 'fresh_manure';
    case COMPOSTED_MANURE = 'composted_manure';
    case DRIED_MANURE = 'dried_manure';
    case ORGANIC_FERTILIZER = 'organic_fertilizer';
    case BIOGAS_SLURRY = 'biogas_slurry';

    public static function labels(): array
    {
        return [
            self::FRESH_MANURE->value => 'Fresh Manure',
            self::COMPOSTED_MANURE->value => 'Composted Manure',
            self::DRIED_MANURE->value => 'Dried Manure',
            self::ORGANIC_FERTILIZER->value => 'Organic Fertilizer',
            self::BIOGAS_SLURRY->value => 'Biogas Slurry',
        ];
    }
}

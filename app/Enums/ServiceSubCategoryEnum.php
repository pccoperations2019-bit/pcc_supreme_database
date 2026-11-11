<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum ServiceSubCategoryEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::ANIMAL_HEALTH_BREEDING;

    case ANIMAL_HEALTH_BREEDING = 'animal_health_breeding';
    case FEED_NUTRITION_SUPPORT = 'feed_nutrition_support';
    case PROCESSING_VALUE_ADDITION = 'processing_value_addition';
    case MARKETING_ENTERPRISE_DEVELOPMENT = 'marketing_enterprise_development';

    public static function labels(): array
    {
        return [
            self::ANIMAL_HEALTH_BREEDING->value => 'Animal Health & Breeding',
            self::FEED_NUTRITION_SUPPORT->value => 'Feed & Nutrition Support',
            self::PROCESSING_VALUE_ADDITION->value => 'Processing & Value Addition',
            self::MARKETING_ENTERPRISE_DEVELOPMENT->value => 'Marketing & Enterprise Development',
        ];
    }
}

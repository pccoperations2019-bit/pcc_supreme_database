<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ConstructionTypeEnum: string
{
    use HasLabels;

    case PRODUCTION_SITE = 'production_site';
    case PRODUCTS = 'products';
    case DAIRYBOX = 'dairybox';

    public static function labels(): array
    {
        return [
            self::PRODUCTION_SITE->value => 'Production Site',
            self::PRODUCTS->value => 'Products',
            self::DAIRYBOX->value => 'Dairybox',
        ];
    }
}

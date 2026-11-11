<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MilkProductTypeEnum: string implements WithLabels
{
    use HasLabels;

    case TONED_MILK = 'toned_milk';
    case PASTILLAS = 'pastillas';
    case FRESH_MILK = 'fresh_milk';
    case CHOCO_MILK = 'choco_milk';
    case YOGURT_DRINK = 'yogurt_drink';
    case KESONG_PUTI = 'kesong_puti';
    case CONSIGNED_PRODUCTS = 'consigned_products';
    case ICE_CANDY_AVOCADO = 'ice_candy_avocado';
    case ICE_CANDY_CHOCO = 'ice_candy_choco';
    case ICE_CANDY_MILK = 'ice_candy_milk';
    case BREAD_AND_PASTRY = 'bread_and_pastry';

    public static function labels(): array
    {
        return [
            self::TONED_MILK->value => 'Toned Milk',
            self::PASTILLAS->value => 'Pastillas',
            self::FRESH_MILK->value => 'Fresh Milk',
            self::CHOCO_MILK->value => 'Choco Milk',
            self::YOGURT_DRINK->value => 'Yogurt Drink',
            self::KESONG_PUTI->value => 'Kesong Puti',
            self::CONSIGNED_PRODUCTS->value => 'Consigned Products',
            self::ICE_CANDY_AVOCADO->value => 'Ice Candy Avocado',
            self::ICE_CANDY_CHOCO->value => 'Ice Candy Choco',
            self::ICE_CANDY_MILK->value => 'Ice Candy Milk',
            self::BREAD_AND_PASTRY->value => 'Bread and Pastry',
        ];
    }
}

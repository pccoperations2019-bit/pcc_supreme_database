<?php

namespace App\Enums;

enum AddressCategoryEnum: string
{
    const DEFAULT = self::HOME;

    case HOME = 'home';
    case OFFICE = 'office';
    case BILLING = 'billing';
    case SHIPPING = 'shipping';
    case FARM = 'farm';
    case CENTER = 'center';
}

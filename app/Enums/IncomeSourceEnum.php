<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;

enum IncomeSourceEnum: string implements WithLabels, WithMatches
{
    use HasLabels;
    use HasMatches;

    const DEFAULT = self::FARMING;

    case BUSINESS = 'Business';
    case CHILD_SUPPORT = 'Child Support';
    case CONSTRUCTION = 'Construction';
    case DAIRY_PRODUCTION = 'Dairy Production';
    case DRIVER_MECHANIC = 'Driver/Mechanic';
    case EMPLOYED = 'Employed';
    case FARMING = 'Farming';
    case LIVESTOCK_POULTRY_PRODUCTION_AMPERSAND = 'Livestock & Poultry Production';
    case LIVESTOCK_AND_POULTRY_PRODUCTION = 'Livestock and Poultry Production';
    case LIVESTOCK_PRODUCTION = 'Livestock Production';
    case OWNED_BUSINESS = 'Owned Business';
    case PENSIONER = 'Pensioner';
    case POULTRY_PRODUCTION = 'Poultry Production';
    case SELF_EMPLOYED = 'Self Employed';

    public static function labels(): array
    {
        return [
            self::BUSINESS->value => 'Business',
            self::CHILD_SUPPORT->value => 'Child Support',
            self::CONSTRUCTION->value => 'Construction',
            self::DAIRY_PRODUCTION->value => 'Dairy Production',
            self::DRIVER_MECHANIC->value => 'Driver/Mechanic',
            self::EMPLOYED->value => 'Employed',
            self::FARMING->value => 'Farming',
            self::LIVESTOCK_POULTRY_PRODUCTION_AMPERSAND->value => 'Livestock & Poultry Production',
            self::LIVESTOCK_AND_POULTRY_PRODUCTION->value => 'Livestock and Poultry Production',
            self::LIVESTOCK_PRODUCTION->value => 'Livestock Production',
            self::OWNED_BUSINESS->value => 'Owned Business',
            self::PENSIONER->value => 'Pensioner',
            self::POULTRY_PRODUCTION->value => 'Poultry Production',
            self::SELF_EMPLOYED->value => 'Self Employed',
        ];
    }

    public static function matches(): array
    {
        return [
            self::BUSINESS->value => ['business', 'business income', 'biz', 'entrepreneur', 'entrepreneurship'],
            self::CHILD_SUPPORT->value => ['child support', 'support', 'financial support', 'allowance'],
            self::CONSTRUCTION->value => ['construction', 'construction work', 'construction worker', 'laborer', 'labourer', 'contractor', 'carpenter', 'mason'],
            self::DAIRY_PRODUCTION->value => ['dairy production', 'dairy', 'milk production', 'milk'],
            self::DRIVER_MECHANIC->value => ['driver/mechanic', 'driver / mechanic', 'driver & mechanic', 'driver', 'mechanic', 'tricycle driver', 'jeepney driver', 'truck driver'],
            self::EMPLOYED->value => ['employed', 'employee', 'employment', 'regular employee', 'salaried', 'wage earner', 'wage-earner', 'daily wage'],
            self::FARMING->value => ['farming', 'farmer', 'crop farming', 'agriculture', 'agri', 'rice farming', 'corn farming'],
            self::LIVESTOCK_POULTRY_PRODUCTION_AMPERSAND->value => ['livestock & poultry production', 'livestock and poultry production', 'livestock/poultry production', 'livestock / poultry production', 'livestock & poultry', 'livestock and poultry', 'livestock and poultry prod', 'livestock & poultry prod'],
            self::LIVESTOCK_PRODUCTION->value => ['livestock production', 'livestock', 'animal production', 'cattle raising', 'goat raising', 'hog raising', 'swine raising'],
            self::OWNED_BUSINESS->value => ['owned business', 'own business', 'business owner', 'owner', 'store owner', 'sari-sari store', 'sari sari store', 'sari-sari'],
            self::PENSIONER->value => ['pensioner', 'pension', 'retired', 'retiree'],
            self::POULTRY_PRODUCTION->value => ['poultry production', 'poultry', 'chicken raising', 'duck raising', 'poultry raising', 'layer', 'broiler'],
            self::SELF_EMPLOYED->value => ['self employed', 'self-employed', 'selfemployment', 'self employment', 'freelance', 'freelancer', 'contractual', 'Self-employed', 'informal work'],
        ];
    }
}

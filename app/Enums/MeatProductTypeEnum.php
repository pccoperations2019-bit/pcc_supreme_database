<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum MeatProductTypeEnum: string implements WithLabels
{
    use HasLabels;

    // Choice Cuts (per gram)
    case RIBLET = 'riblet';
    case BRISKET = 'brisket';
    case PORTEHOUSE = 'portehouse';
    case TENDERLOIN = 'tenderloin';
    case RIBEYE = 'ribeye';
    case EYE_OF_ROUND = 'eye_of_round';
    case T_BONE = 't_bone';

    // Sausage
    case HOTDOG = 'hotdog';
    case HUNGARIAN = 'hungarian';
    case BEERWURST = 'beerwurst';
    case SCHUBLIG = 'schublig';
    case BRATWURST = 'bratwurst';
    case ITALIAN = 'italian';
    case SALAMETTI = 'salametti';
    case KIELBASA = 'kielbasa';

    // Special Cuts
    case TAPA = 'tapa';
    case TOCINO = 'tocino';
    case PAPAITAN = 'papaitan';

    // Other Cuts
    case MEAT_CUBES = 'meat_cubes';
    case GROUND_BEEF = 'ground_beef';
    case BELLY = 'belly';
    case SOUP_BONES = 'soup_bones';

    public static function labels(): array
    {
        return [
            // Choice Cuts
            self::RIBLET->value => 'Riblet',
            self::BRISKET->value => 'Brisket',
            self::PORTEHOUSE->value => 'Portehouse',
            self::TENDERLOIN->value => 'Tenderloin',
            self::RIBEYE->value => 'Ribeye',
            self::EYE_OF_ROUND->value => 'Eye of Round',
            self::T_BONE->value => 'T-Bone',

            // Sausage
            self::HOTDOG->value => 'Hotdog',
            self::HUNGARIAN->value => 'Hungarian',
            self::BEERWURST->value => 'Beerwurst',
            self::SCHUBLIG->value => 'Schublig',
            self::BRATWURST->value => 'Bratwurst',
            self::ITALIAN->value => 'Italian',
            self::SALAMETTI->value => 'Salametti',
            self::KIELBASA->value => 'Kielbasa',

            // Special Cuts
            self::TAPA->value => 'Tapa',
            self::TOCINO->value => 'Tocino',
            self::PAPAITAN->value => 'Papaitan',

            // Other Cuts
            self::MEAT_CUBES->value => 'Meat Cubes',
            self::GROUND_BEEF->value => 'Ground Beef',
            self::BELLY->value => 'Belly',
            self::SOUP_BONES->value => 'Soup Bones',
        ];
    }

    public static function categories(): array
    {
        return [
            'choice_cuts' => [
                'label' => 'Choice Cuts (per gram)',
                'products' => [
                    self::RIBLET->value,
                    self::BRISKET->value,
                    self::PORTEHOUSE->value,
                    self::TENDERLOIN->value,
                    self::RIBEYE->value,
                    self::EYE_OF_ROUND->value,
                    self::T_BONE->value,
                ],
            ],
            'sausage' => [
                'label' => 'Sausage',
                'products' => [
                    self::HOTDOG->value,
                    self::HUNGARIAN->value,
                    self::BEERWURST->value,
                    self::SCHUBLIG->value,
                    self::BRATWURST->value,
                    self::ITALIAN->value,
                    self::SALAMETTI->value,
                    self::KIELBASA->value,
                ],
            ],
            'special_cuts' => [
                'label' => 'Special Cuts',
                'products' => [
                    self::TAPA->value,
                    self::TOCINO->value,
                    self::PAPAITAN->value,
                ],
            ],
            'other_cuts' => [
                'label' => 'Other Cuts',
                'products' => [
                    self::MEAT_CUBES->value,
                    self::GROUND_BEEF->value,
                    self::BELLY->value,
                    self::SOUP_BONES->value,
                ],
            ],
        ];
    }
}

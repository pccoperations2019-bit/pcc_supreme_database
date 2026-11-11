<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum PL480ProvinceEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case CAGAYAN = 'Cagayan';
    case ISABELA = 'Isabela';
    case BULACAN = 'Bulacan';
    case NUEVA_ECIJA = 'Nueva Ecija';
    case PAMPANGA = 'Pampanga';
    case BOHOL = 'Bohol';
    case CEBU = 'Cebu';
    case ZAMBOANGA_DEL_SUR = 'Zamboanga Del Sur';
    case BUKIDNON = 'Bukidnon';

    public static function labels(): array
    {
        return [
            self::CAGAYAN->value => self::CAGAYAN->value,
            self::ISABELA->value => self::ISABELA->value,
            self::BULACAN->value => self::BULACAN->value,
            self::NUEVA_ECIJA->value => self::NUEVA_ECIJA->value,
            self::PAMPANGA->value => self::PAMPANGA->value,
            self::BOHOL->value => self::BOHOL->value,
            self::CEBU->value => self::CEBU->value,
            self::ZAMBOANGA_DEL_SUR->value => self::ZAMBOANGA_DEL_SUR->value,
            self::BUKIDNON->value => self::BUKIDNON->value,
        ];
    }

    public static function matches(): array
    {
        return [
            self::CAGAYAN->value => [self::CAGAYAN->value],
            self::ISABELA->value => [self::ISABELA->value],
            self::BULACAN->value => [self::BULACAN->value],
            self::NUEVA_ECIJA->value => [self::NUEVA_ECIJA->value, 'Nueva Ecija Province'],
            self::PAMPANGA->value => [self::PAMPANGA->value],
            self::BOHOL->value => [self::BOHOL->value],
            self::CEBU->value => [self::CEBU->value],
            self::ZAMBOANGA_DEL_SUR->value => [self::ZAMBOANGA_DEL_SUR->value, 'Zamboanga del Sur'],
            self::BUKIDNON->value => [self::BUKIDNON->value],
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

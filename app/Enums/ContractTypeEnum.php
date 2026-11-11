<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;

enum ContractTypeEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels, HasMatches, HasSelectableOptions;

    case KONTRATA_NG_PAGHIRAM = 'kontrata_ng_paghiram';
    case MOA = 'moa';
    case BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW = 'bagong_kasunduan_sa_pagpapahiram_ng_gatasang_kalabaw';
    case PUMAPALIT_SA_BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW = 'pumapalit_sa_bagong_kasunduan_sa_pagpapahiram_ng_gatasang_kalabaw';
    case KASUNDUAN_SA_PAGPAPAMAHAGI_NG_GATASANG_KALABAW = 'kasunduan_sa_pagpapamahagi_ng_gatasang_kalabaw';
    case KASUNDUAN_SA_PAGPAPAMAHAGI_NG_PUHUNANG_GATASANG_KALABAW = 'kasunduan_sa_pagpapamahagi_ng_puhunang_gatasang_kalabaw';
    case MULTIPLIER_FARM_DAIRY_BUFFALO_COOPERATOR = 'multiplier_farm_dairy_buffalo_cooperator';
    case COOP_CONDUIT_CONTRACT = 'coop_conduit_contract';

    public static function labels(): array
    {
        return [
            self::KONTRATA_NG_PAGHIRAM->value => 'Kontrata ng Paghiram',
            self::MOA->value => 'MOA',
            self::BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW->value => 'Bagong Kasunduan sa Pagpapahiram ng Gatasang Kalabaw',
            self::PUMAPALIT_SA_BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW->value => 'Pumapalit sa Bagong Kasunduan sa Pagpapahiram ng Gatasang Kalabaw',
            self::KASUNDUAN_SA_PAGPAPAMAHAGI_NG_GATASANG_KALABAW->value => 'Kasunduan sa Pagpapamahagi ng Gatasang Kalabaw',
            self::KASUNDUAN_SA_PAGPAPAMAHAGI_NG_PUHUNANG_GATASANG_KALABAW->value => 'Kasunduan sa Pagpapamahagi ng Puhunang Gatasang Kalabaw',
            self::MULTIPLIER_FARM_DAIRY_BUFFALO_COOPERATOR->value => 'Multiplier Farm Dairy Buffalo Cooperator',
            self::COOP_CONDUIT_CONTRACT->value => 'Coop-Conduit Contract',
        ];
    }

    public static function matches(): array
    {
        return [
            self::KONTRATA_NG_PAGHIRAM->value => ['Kontrata ng Paghiram'],
            self::MOA->value => ['MOA'],
            self::BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW->value => ['Bagong Kasunduan sa Pagpapahiram ng Gatasang Kalabaw'],
            self::PUMAPALIT_SA_BAGONG_KASUNDUAN_SA_PAGPAPAHIRAM_NG_GATASANG_KALABAW->value => ['Pumapalit sa Bagong Kasunduan sa Pagpapahiram ng Gatasang Kalabaw'],
            self::KASUNDUAN_SA_PAGPAPAMAHAGI_NG_GATASANG_KALABAW->value => ['Kasunduan sa Pagpapamahagi ng Gatasang Kalabaw'],
            self::KASUNDUAN_SA_PAGPAPAMAHAGI_NG_PUHUNANG_GATASANG_KALABAW->value => ['Kasunduan sa Pagpapamahagi ng Puhunang Gatasang Kalabaw'],
            self::MULTIPLIER_FARM_DAIRY_BUFFALO_COOPERATOR->value => ['Multiplier Farm Dairy Buffalo Cooperator'],
            self::COOP_CONDUIT_CONTRACT->value => ['Coop-Conduit Contract'],
        ];
    }
}

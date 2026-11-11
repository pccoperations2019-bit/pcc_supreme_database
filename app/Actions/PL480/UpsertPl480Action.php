<?php

namespace App\Actions\PL480;

use App\Enums\PL480ProvinceEnum;
use App\Models\Pl480;

class UpsertPl480Action
{
    public function execute(
        PL480ProvinceEnum|string $province,
        int $year,
        ?float $value = null,
        ?int $centerId = null,
        ?int $partnerId = null,
    ): Pl480 {
        $provinceValue = $province instanceof PL480ProvinceEnum ? $province->value : $province;

        return Pl480::updateOrCreate(
            [
                'province' => $provinceValue,
                'year' => $year,
            ],
            [
                'province' => $provinceValue,
                'year' => $year,
                'value' => (float) ($value ?? 0),
                'center_id' => $centerId,
                'partner_id' => $partnerId,
            ]
        );
    }
}

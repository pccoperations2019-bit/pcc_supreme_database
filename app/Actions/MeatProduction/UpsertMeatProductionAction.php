<?php

namespace App\Actions\MeatProduction;

use App\Enums\ProgramEnum;
use App\Models\MeatProduction;

class UpsertMeatProductionAction
{
    public function execute(
        ?int $centerId,
        ?int $partnerId,
        ?int $farmerId,
        ?int $year = null,
        ProgramEnum $program = ProgramEnum::DEFAULT,
        mixed $totalVolume = null,
        ?array $monthlyData = null,
        ?int $month = null,
        ?int $quarter = null
    ): MeatProduction {
        return MeatProduction::updateOrCreate(
            [
                'center_id' => $centerId,
                'program' => $program->value,
                'year' => $year,
                'month' => $month,
            ],
            [
                'partner_id' => $partnerId,
                'farmer_id' => $farmerId,
                'total_volume' => $totalVolume ?? 0,
                'monthly_data' => $monthlyData,
                'quarter' => $quarter,
            ]
        );
    }
}

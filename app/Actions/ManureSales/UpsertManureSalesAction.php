<?php

namespace App\Actions\ManureSales;

use App\Enums\ProgramEnum;
use App\Models\ManureSales;

class UpsertManureSalesAction
{
    public function execute(
        ?int $centerId,
        ?int $partnerId,
        ?int $farmerId,
        ?int $year = null,
        ProgramEnum $program = ProgramEnum::DEFAULT,
        mixed $totalSales = null,
        ?array $monthlyData = null,
        ?int $month = null,
        ?int $quarter = null
    ): ManureSales {
        return ManureSales::updateOrCreate(
            [
                'center_id' => $centerId,
                'program' => $program->value,
                'year' => $year,
                'month' => $month,
            ],
            [
                'partner_id' => $partnerId,
                'farmer_id' => $farmerId,
                'total_sales' => $totalSales ?? 0,
                'monthly_data' => $monthlyData,
                'quarter' => $quarter,
            ]
        );
    }
}

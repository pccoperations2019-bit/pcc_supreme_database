<?php

namespace App\Actions\MilkProduction;

use App\Models\MilkTraded;

class UpsertMilkTradedAction
{
    public function execute(
        int $milkProductionId,
        ?int $centerId = null,
        ?int $partnerId = null,
        ?int $farmerId = null,
        ?\DateTimeInterface $transactionDate = null,
        ?int $year = null,
        ?int $month = null,
        ?int $quarter = null,
        ?int $week = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        float $totalVolumeTraded = 0
    ): MilkTraded {
        return MilkTraded::updateOrCreate(
            [
                'milk_production_id' => $milkProductionId,
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'year' => $year,
                'week' => $week,
            ],
            array_filter([
                'milk_production_id' => $milkProductionId,
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'farmer_id' => $farmerId,
                'transaction_date' => $transactionDate,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'week' => $week,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_volume_traded' => $totalVolumeTraded,
            ])
        );
    }
}

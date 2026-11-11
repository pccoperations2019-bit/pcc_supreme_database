<?php

namespace App\Actions\MilkProduction;

use App\Models\MilkUsage;

class UpsertMilkUsageAction
{
    public function execute(
        int $milkProductionId,
        float $fedToCalves = 0,
        float $familyConsumption = 0,
        ?string $processing = null
    ): MilkUsage {
        return MilkUsage::updateOrCreate(
            ['milk_production_id' => $milkProductionId],
            [
                'fed_to_calves' => $fedToCalves,
                'family_consumption' => $familyConsumption,
                'processing' => $processing,
            ]
        );
    }
}

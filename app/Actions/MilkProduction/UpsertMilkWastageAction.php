<?php

namespace App\Actions\MilkProduction;

use App\Models\MilkWastage;

class UpsertMilkWastageAction
{
    public function execute(
        int $milkProductionId,
        float $milkSpoiledVolume = 0,
        float $milkSpoiledCost = 0,
        float $milkSpoiledPrice = 0,
        ?string $reason = null
    ): MilkWastage {
        return MilkWastage::updateOrCreate(
            ['milk_production_id' => $milkProductionId],
            [
                'milk_spoiled_volume' => $milkSpoiledVolume,
                'milk_spoiled_cost' => $milkSpoiledCost,
                'milk_spoiled_price' => $milkSpoiledPrice,
                'reason' => $reason,
            ]
        );
    }
}

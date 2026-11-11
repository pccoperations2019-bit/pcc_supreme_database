<?php

namespace App\Actions\MilkProduction;

use App\Models\MilkCost;

class UpsertMilkCostAction
{
    public function execute(
        int $milkProductionId,
        float $rawMilkCost = 0,
        float $rawMilkPrice = 0,
        float $purchasedRawMilkVolume = 0,
        int $numberOfStaffMale = 0,
        int $numberOfStaffFemale = 0,
        float $avgSalaryOfStaff = 0
    ): MilkCost {
        return MilkCost::updateOrCreate(
            ['milk_production_id' => $milkProductionId],

            [
                'raw_milk_cost' => $rawMilkCost,
                'raw_milk_price' => $rawMilkPrice,
                'purchased_raw_milk_volume' => $purchasedRawMilkVolume,
                'number_of_staff_male' => $numberOfStaffMale,
                'number_of_staff_female' => $numberOfStaffFemale,
                'avg_salary_of_staff' => $avgSalaryOfStaff,
            ]
        );
    }
}

<?php

namespace App\Actions\Center;

use App\Enums\ProgramEnum;
use App\Models\CenterProductionGoal;
use Illuminate\Support\Facades\DB;

class UpsertProductionGoalAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        int $centerId,
        int $year,
        ProgramEnum $program = ProgramEnum::IH,
        ?float $milkProductionGoal = null,
        ?float $meatMeatEquivalentGoal = null,
        ?float $manureBasedGoal = null,
        ?float $hideProductionGoal = null,
    ): CenterProductionGoal {

        return DB::transaction(function () use (
            $centerId,
            $year,
            $program,
            $milkProductionGoal,
            $meatMeatEquivalentGoal,
            $manureBasedGoal,
            $hideProductionGoal
        ) {
            return CenterProductionGoal::updateOrCreate(
                [
                    'center_id' => $centerId,
                    'year' => $year,
                    'program' => $program,
                ],
                [
                    'milk_production_goal' => $milkProductionGoal,
                    'meat_meat_equivalent_goal' => $meatMeatEquivalentGoal,
                    'manure_based_goal' => $manureBasedGoal,
                    'hide_production_goals' => $hideProductionGoal,
                ]
            );
        });
    }
}

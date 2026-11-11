<?php

namespace App\Actions\Center;

use App\Enums\ProgramEnum;
use App\Models\CenterSalesGoal;
use Illuminate\Support\Facades\DB;

class UpsertSalesGoalAction
{
    public function execute(
        int $centerId,
        int $year,
        ProgramEnum $program = ProgramEnum::IH,
        ?float $milkSalesGoal = null,
        ?float $meatSalesGoal = null,
        ?float $manureSalesGoal = null,
        ?float $hideSalesGoals = null,
    ): CenterSalesGoal {
        return DB::transaction(function () use (
            $centerId,
            $year,
            $program,
            $milkSalesGoal,
            $meatSalesGoal,
            $manureSalesGoal,
            $hideSalesGoals
        ) {
            return CenterSalesGoal::updateOrCreate(
                [
                    'center_id' => $centerId,
                    'year' => $year,
                    'program' => $program,
                ],
                [
                    'milk_sales_goal' => $milkSalesGoal,
                    'meat_sales_goal' => $meatSalesGoal,
                    'manure_sales_goal' => $manureSalesGoal,
                    'hide_sales_goals' => $hideSalesGoals,
                ]
            );
        });
    }
}

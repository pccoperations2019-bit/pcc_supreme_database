<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class ProductionStaffCountQuery
{
    public function execute(
        ?int $centerId
    ): int {
        return (int) DB::table('production_units')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->sum(DB::raw('number_of_staff_male + number_of_staff_female'));
    }
}

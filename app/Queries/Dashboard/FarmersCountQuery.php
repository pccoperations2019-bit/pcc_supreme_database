<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class FarmersCountQuery
{
    public function execute(
        ?int $centerId
    ): int {
        // Count all farmers (both independent and FCA members)
        return DB::table('farmers')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->count();
    }
}

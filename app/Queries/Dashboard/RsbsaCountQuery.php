<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class RsbsaCountQuery
{
    public function execute(
        ?int $centerId,
        ?string $program = null
    ): int {
        // Farmers don't have program field - they are shared between IH and CBED
        // Program parameter is ignored for farmers
        return DB::table('farmers')
            ->where('has_rsbsa', true)
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->count();
    }
}

<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class NdaLtoCountQuery
{
    public function execute(
        ?int $centerId,
        ?string $program = null
    ): int {
        // Count partners with NDA-LTO
        $partnerCount = DB::table('partners')
            ->where('has_nda_lto', true)
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->when($program, function ($query) use ($program) {
                $query->where('program', $program);
            })
            ->count();

        // Count farmers with NDA-LTO (if applicable)
        $farmerCount = DB::table('farmers')
            ->where('has_nda_lto', true)
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->count();

        return $partnerCount + $farmerCount;
    }
}

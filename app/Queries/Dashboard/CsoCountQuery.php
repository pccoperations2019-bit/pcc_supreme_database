<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class CsoCountQuery
{
    public function execute(
        ?int $centerId,
        ?string $program = null
    ): int {
        return DB::table('partners')
            ->where('has_cso', true)
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->when($program, function ($query) use ($program) {
                $query->where('program', $program);
            })
            ->count();
    }
}

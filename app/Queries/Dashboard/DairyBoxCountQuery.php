<?php

namespace App\Queries\Dashboard;

use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class DairyBoxCountQuery
{
    public function execute(
        ?int $centerId,
        ?ProgramEnum $program = null
    ): int {
        // Operational dairy boxes are shown on both the IH and CBED dashboards,
        // so the program/owner filter is intentionally not applied here.
        return DB::table('dairy_boxes')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->where('dairy_boxes.status', 'operational')
            ->count();
    }
}

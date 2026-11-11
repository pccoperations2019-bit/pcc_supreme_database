<?php

namespace App\Queries\Dashboard;

use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class KnpCountQuery
{
    public function execute(
        ?int $centerId,
        ?ProgramEnum $program = null
    ): int {
        $query = DB::table('dairy_boxes')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('dairy_boxes.center_id', $centerId);
            })
            ->where('dairy_boxes.kadiwa_integration', 'yes');

        // Filter by partner program if specified
        // Use LEFT JOIN to include units without partners (center-owned units)
        if ($program instanceof ProgramEnum) {
            $query->leftJoin('partners', 'dairy_boxes.partner_id', '=', 'partners.id')
                ->where(function ($q) use ($program) {
                    $q->whereNull('partners.id')
                        ->orWhere('partners.program', $program->value);
                });
        }

        return $query->count();
    }
}

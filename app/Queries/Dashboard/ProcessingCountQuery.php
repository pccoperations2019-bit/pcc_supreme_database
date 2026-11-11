<?php

namespace App\Queries\Dashboard;

use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class ProcessingCountQuery
{
    public function execute(
        ?int $centerId,
        ?ProgramEnum $program = null
    ): int {
        $query = DB::table('processing_units')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('processing_units.center_id', $centerId);
            });

        // IH shows center-owned processing, CBED shows partner-owned processing
        if ($program === ProgramEnum::IH) {
            $query->whereNull('processing_units.partner_id');
        } elseif ($program === ProgramEnum::CBED) {
            $query->whereNotNull('processing_units.partner_id');
        }

        // Filter by partner program if specified
        // Use LEFT JOIN to include units without partners (center-owned units)
        if ($program) {
            $query->leftJoin('partners', 'processing_units.partner_id', '=', 'partners.id')
                ->where(function ($q) use ($program) {
                    $q->whereNull('partners.id')
                        ->orWhere('partners.program', $program->value);
                });
        }

        return $query->count();
    }
}

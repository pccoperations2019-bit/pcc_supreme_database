<?php

namespace App\Queries\Dashboard;

use App\Enums\PartnerCategoryEnum;
use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class FamersFCACountQuery
{
    public function execute(
        ?int $centerId,
        ?ProgramEnum $program = null
    ): int {
        // Count FCAs from partners table (Cooperatives, Associations, Family Module)
        $partnerFcaCount = DB::table('partners')
            ->whereIn('category', [
                PartnerCategoryEnum::COOPERATIVE->value,
                PartnerCategoryEnum::ASSOCIATION->value,
                PartnerCategoryEnum::FAMILY_MODULE->value,
            ])
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->when($program, function ($query) use ($program) {
                $query->where('program', $program->value);
            })
            ->count();

        // Count Farmer Individuals and Farmer-members from farmers table
        // Farmers don't have program field - they are shared between IH and CBED
        $farmerFcaCount = DB::table('farmers')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })
            ->count();

        return $partnerFcaCount + $farmerFcaCount;
    }
}

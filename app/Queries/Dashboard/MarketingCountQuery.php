<?php

namespace App\Queries\Dashboard;

use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class MarketingCountQuery
{
    public function execute(
        ?int $centerId,
        ?ProgramEnum $program = null
    ): int {
        // IH shows center-owned marketing, CBED shows partner-owned marketing
        return $this->marketingUnitsCount($centerId, $program)
            + ($program === ProgramEnum::IH
                ? 0
                : $this->partnerDairyBoxesCount($centerId, $program));
    }

    private function marketingUnitsCount(?int $centerId, ?ProgramEnum $program): int
    {
        $query = DB::table('marketing_units')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('marketing_units.center_id', $centerId);
            });

        if ($program === ProgramEnum::IH) {
            $query->whereNull('marketing_units.partner_id');
        } elseif ($program === ProgramEnum::CBED) {
            $query->whereNotNull('marketing_units.partner_id');
        }

        // Filter by partner program if specified
        // Use LEFT JOIN to include units without partners (center-owned units)
        if ($program) {
            $query->leftJoin('partners', 'marketing_units.partner_id', '=', 'partners.id')
                ->where(function ($q) use ($program) {
                    $q->whereNull('partners.id')
                        ->orWhere('partners.program', $program->value);
                });
        }

        return $query->count();
    }

    private function partnerDairyBoxesCount(?int $centerId, ?ProgramEnum $program): int
    {
        $query = DB::table('dairy_boxes')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('dairy_boxes.center_id', $centerId);
            })
            ->whereNotNull('dairy_boxes.partner_id');

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

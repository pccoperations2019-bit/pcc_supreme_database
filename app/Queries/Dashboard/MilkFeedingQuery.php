<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MilkFeedingQuery
{
    public function execute(
        ?string $agency = 'depEd',
        ?int $centerId = null,
        mixed $dateRange = 'this_year',
        ?string $program = null
    ): Collection {
        return DB::table('milk_feeding_programs')
            ->leftJoin('addresses', function ($join) {
                $join->on('addresses.addressable_id', '=', 'milk_feeding_programs.id')
                    ->where('addresses.addressable_type', '=', 'milk_feeding_program');
            })
            ->selectRaw('SUM(milk_feeding_programs.number_of_beneficiaries) as total_beneficiaries')
            ->selectRaw('SUM(milk_feeding_programs.milk_packs) as total_milk_packs')
            ->selectRaw('COUNT(DISTINCT milk_feeding_programs.division) as total_sdos_engaged')
            ->selectRaw('COUNT(DISTINCT addresses.city) as total_lgu_engaged')
            ->selectRaw('COUNT(DISTINCT milk_feeding_programs.partner_id) as total_cooperatives_engaged')
            ->selectRaw('SUM(milk_feeding_programs.gross_income) as gross_income')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('milk_feeding_programs.center_id', $centerId);
            })
            ->when($agency, function ($query) use ($agency) {
                $query->where('milk_feeding_programs.funded_by', $agency);
            })
            ->when($program, function ($query) use ($program) {
                $query->where('milk_feeding_programs.program', $program);
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                if (is_int($dateRange) || (is_string($dateRange) && ctype_digit($dateRange))) {
                    $year = (int) $dateRange;
                    $query->whereBetween('milk_feeding_programs.date_started', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)]);
                } elseif (is_array($dateRange) && count($dateRange) === 2) {
                    $query->whereBetween('milk_feeding_programs.date_started', [$dateRange[0], $dateRange[1]]);
                } elseif (is_string($dateRange)) {
                    match ($dateRange) {
                        'this_month' => $query->whereBetween('milk_feeding_programs.date_started', [now()->startOfMonth(), now()->endOfMonth()]),
                        'last_month' => $query->whereBetween('milk_feeding_programs.date_started', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                        'this_year' => $query->whereBetween('milk_feeding_programs.date_started', [now()->startOfYear(), now()->endOfYear()]),
                        'last_year' => $query->whereBetween('milk_feeding_programs.date_started', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]),
                        'this_quarter' => $query->whereBetween('milk_feeding_programs.date_started', [now()->firstOfQuarter(), now()->lastOfQuarter()]),
                        'last_quarter' => $query->whereBetween('milk_feeding_programs.date_started', [now()->subQuarter()->firstOfQuarter(), now()->subQuarter()->lastOfQuarter()]),
                        default => null,
                    };
                }
            })->get();
    }
}

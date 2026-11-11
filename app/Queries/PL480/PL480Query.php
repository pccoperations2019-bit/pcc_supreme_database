<?php

namespace App\Queries\PL480;

use App\Enums\PL480ProvinceEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PL480Query
{
    public function execute(array $provinces, int $startYear, int $endYear, ?int $centerId = null): Collection
    {
        $provinceExpression = $centerId === null
            ? "COALESCE(partner_addresses.province, center_addresses.province, 'Unknown')"
            : "COALESCE(partner_addresses.province, center_addresses.province, farmer_addresses.province, 'Unknown')";

        $query = DB::table('milk_productions')
            ->selectRaw("{$provinceExpression} as province")
            ->selectRaw('milk_productions.year')
            ->selectRaw('SUM(milk_productions.total_volume_produced) as total_volume')
            ->leftJoin('partners', 'milk_productions.partner_id', '=', 'partners.id')
            ->leftJoin('centers', 'milk_productions.center_id', '=', 'centers.id')
            ->leftJoin('addresses as partner_addresses', function ($join) {
                $join->on('partner_addresses.addressable_id', '=', 'partners.id')
                    ->where('partner_addresses.addressable_type', '=', 'partner');
            })
            ->leftJoin('addresses as center_addresses', function ($join) {
                $join->on('center_addresses.addressable_id', '=', 'centers.id')
                    ->where('center_addresses.addressable_type', '=', 'center');
            })
            ->whereBetween('milk_productions.year', [$startYear, $endYear]);

        if ($centerId !== null) {
            $query->leftJoin('farmers', 'milk_productions.farmer_id', '=', 'farmers.id')
                ->leftJoin('addresses as farmer_addresses', function ($join) {
                    $join->on('farmer_addresses.addressable_id', '=', 'farmers.id')
                        ->where('farmer_addresses.addressable_type', '=', 'farmer');
                });
        }

        $query->where(function ($query) use ($centerId) {
            $query
                ->whereNotNull('partner_addresses.province')
                ->orWhereNotNull('center_addresses.province');

            if ($centerId !== null) {
                $query->orWhereNotNull('farmer_addresses.province');
            }
        })
            ->groupByRaw("{$provinceExpression}, milk_productions.year");

        if ($centerId !== null) {
            $query->where(function ($scope) use ($centerId) {
                $scope->where('milk_productions.center_id', $centerId)
                    ->orWhere('partners.center_id', $centerId)
                    ->orWhere('farmers.center_id', $centerId);
            });
        }

        $rows = $query->get();

        $provinceSet = collect($provinces)->map(fn ($p) => (string) $p)->flip();
        $totals = [];

        foreach ($rows as $row) {
            $rawProvince = (string) ($row->province ?? 'Unknown');
            $canonical = PL480ProvinceEnum::tryFroMatches($rawProvince)?->value ?? $rawProvince;

            if (! $provinceSet->isEmpty() && ! $provinceSet->has($canonical)) {
                continue;
            }

            $year = (int) $row->year;
            $totals[$canonical] ??= [];
            $totals[$canonical][$year] = ($totals[$canonical][$year] ?? 0) + (float) $row->total_volume;
        }

        return collect($totals)->map(function (array $yearTotals) use ($startYear, $endYear) {
            $data = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                $data[$year] = (float) ($yearTotals[$year] ?? 0);
            }

            return $data;
        });
    }
}

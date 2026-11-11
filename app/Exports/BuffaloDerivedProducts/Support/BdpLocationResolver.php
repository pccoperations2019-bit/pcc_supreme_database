<?php

namespace App\Exports\BuffaloDerivedProducts\Support;

use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BdpLocationResolver
{
    /**
     * @return array<int, array{region: string, province: string, municipality: string, district: string}>
     */
    public function resolve(array $centerIds): array
    {
        return $this->resolveByType('center', $centerIds);
    }

    public function resolveByType(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('addresses')
            ->select(['addressable_id', 'region', 'province', 'city', 'district'])
            ->where('addressable_type', $type)
            ->whereIn('addressable_id', $ids)
            ->get()
            ->groupBy('addressable_id')
            ->map(fn (Collection $group) => [
                'region' => $group->first()->region ?? '',
                'province' => $group->first()->province ?? '',
                'municipality' => $group->first()->city ?? '',
                'district' => $group->first()->district ?? '',
            ])
            ->all();
    }

    /**
     * Resolve addresses for BDP rows, picking the most specific entity:
     * - farmer address if farmer_id present (CBED farmer)
     * - partner address if partner_id present (CBED partner)
     * - center address otherwise (IH)
     * When program filter is explicit (ih/cbed), it biases the choice;
     * when program is all/null, row-level presence decides.
     *
     * @param  Collection<int, object>  $rows  must contain center_id, partner_id, farmer_id, program
     * @return array<string, array{region:string,province:string,municipality:string,district:string}>
     */
    public function resolveForRows(Collection $rows, ?string $programFilter = null): array
    {
        $centerIds = $rows->pluck('center_id')->filter()->unique()->values()->all();
        $partnerIds = $rows->pluck('partner_id')->filter()->unique()->values()->all();
        $farmerIds = $rows->pluck('farmer_id')->filter()->unique()->values()->all();

        $centerAddresses = $this->resolveByType('center', $centerIds);
        $partnerAddresses = $this->resolveByType('partner', $partnerIds);
        $farmerAddresses = $this->resolveByType('farmer', $farmerIds);

        $result = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row);
            $program = strtolower(trim((string) ($row->program ?? $programFilter ?? '')));
            $address = null;

            // Explicit program filter takes precedence — for partners/farmers, do not fallback to center address
            if ($program === 'ih') {
                $address = $centerAddresses[$row->center_id] ?? null;
            } elseif ($program === 'cbed') {
                if (! empty($row->farmer_id)) {
                    $address = $farmerAddresses[$row->farmer_id] ?? null;
                } elseif (! empty($row->partner_id)) {
                    $address = $partnerAddresses[$row->partner_id] ?? null;
                } else {
                    $address = $centerAddresses[$row->center_id] ?? null;
                }
            } elseif (! empty($row->farmer_id)) {
                // program=all or null: pick most specific available entity; empty if partner/farmer has no address
                $address = $farmerAddresses[$row->farmer_id] ?? null;
            } elseif (! empty($row->partner_id)) {
                $address = $partnerAddresses[$row->partner_id] ?? null;
            } else {
                $address = $centerAddresses[$row->center_id] ?? null;
            }

            $result[$key] = $address ?? ['region' => '', 'province' => '', 'municipality' => '', 'district' => ''];
        }

        return $result;
    }

    private function rowKey(object $row): string
    {
        return implode('|', [
            $row->center_id ?? '',
            $row->partner_id ?? '',
            $row->farmer_id ?? '',
            $row->year ?? '',
            $row->program ?? '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function locationLabels(int $depth = 5): array
    {
        $labels = [];
        if ($depth >= 2) {
            $labels[] = 'REGION';
        }
        if ($depth >= 3) {
            $labels[] = 'PROVINCE';
        }
        if ($depth >= 4) {
            $labels[] = 'MUNICIPALITY';
        }
        if ($depth >= 5) {
            $labels[] = 'DISTRICT';
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public function resolveLocationLabels(array $location, int $depth = 5): array
    {
        $region = $location['region'] ?? '';
        $regionLabel = AddressRegionEnum::labels()[$region] ?? $region;

        return [
            'region' => strtoupper($regionLabel),
            'province' => $depth >= 3 ? strtoupper($location['province'] ?? '') : '',
            'municipality' => $depth >= 4 ? strtoupper($location['municipality'] ?? '') : '',
            'district' => $depth >= 5 ? self::districtLabel($location['district'] ?? '') : '',
        ];
    }

    /**
     * Normalize a raw stored district value to its canonical ordinal name, e.g.
     * "III" / "3" / "District 3" / "3rd District" -> "3rd". Values that cannot be
     * mapped to a known district are returned unchanged (uppercased), preserving
     * genuinely-named districts and any unexpected data.
     */
    public function districtLabel(mixed $raw): string
    {
        $district = trim((string) ($raw ?? ''));
        if ($district === '') {
            return '';
        }

        $enum = AddressDistrictEnum::tryFroMatches($district);

        if (! $enum instanceof AddressDistrictEnum) {
            return strtoupper($district);
        }

        return AddressDistrictEnum::ordinals()[$enum->value] ?? $district;
    }
}

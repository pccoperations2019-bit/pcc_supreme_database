<?php

namespace App\Exports\BuffaloDerivedProducts\Support;

use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BdpQueryBuilder
{
    public BuffaloDerivedProductsFilters $filters;

    public function __construct(BuffaloDerivedProductsFilters $filters)
    {
        $this->filters = $filters;
    }

    public function fetchRows(string $table, string $metric): Collection
    {
        return DB::table($table)
            ->select([
                "$table.year as year",
                "$table.month as month",
                "$table.partner_id as partner_id",
                DB::raw("SUM($table.$metric) as value"),
            ])
            ->whereNotNull("$table.year")
            ->tap(fn ($query) => $this->applyFilters($query, $table))
            ->groupBy(["$table.year", "$table.month", "$table.partner_id"])
            ->orderBy("$table.year")
            ->get();
    }

    public function fetchAreaRows(string $table, string $metric): Collection
    {
        return DB::table($table)
            ->select([
                "$table.center_id as center_id",
                "$table.partner_id as partner_id",
                "$table.year as year",
                "$table.program as program",
                DB::raw("SUM($table.$metric) as value"),
            ])
            ->whereNotNull("$table.year")
            ->tap(fn ($query) => $this->applyFilters($query, $table))
            ->groupBy(["$table.center_id", "$table.partner_id", "$table.year", "$table.program"])
            ->orderBy("$table.center_id")
            ->get();
    }

    public function fetchCenterYearRows(string $table, string $metric): Collection
    {
        return DB::table($table)
            ->select([
                "$table.center_id as center_id",
                "$table.year as year",
                DB::raw("SUM($table.$metric) as value"),
            ])
            ->whereNotNull("$table.year")
            ->tap(fn ($query) => $this->applyFilters($query, $table))
            ->groupBy(["$table.center_id", "$table.year"])
            ->orderBy("$table.center_id")
            ->orderBy("$table.year")
            ->get();
    }

    public function fetchCenterPartnerYearRows(string $table, string $metric): Collection
    {
        return DB::table($table)
            ->select([
                "$table.center_id as center_id",
                "$table.partner_id as partner_id",
                "$table.farmer_id as farmer_id",
                "$table.year as year",
                DB::raw("SUM($table.$metric) as value"),
            ])
            ->whereNotNull("$table.year")
            ->tap(fn ($query) => $this->applyFilters($query, $table))
            ->groupBy(["$table.center_id", "$table.partner_id", "$table.farmer_id", "$table.year"])
            ->orderBy("$table.center_id")
            ->orderBy("$table.year")
            ->get();
    }

    private function applyFilters($query, string $table): void
    {
        $filters = $this->filters;

        $query
            ->when($filters->year && $filters->year !== 'all', fn ($q) => $this->applyWhereIn($q, "$table.year", $filters->year))
            ->when($filters->centerId && $filters->centerId !== 'all', fn ($q) => $this->applyWhereIn($q, "$table.center_id", $filters->centerId))
            ->when($filters->month && $filters->month !== 'all', fn ($q) => $this->applyWhereIn($q, "$table.month", $filters->month))
            ->when($filters->program && $filters->program !== 'all', fn ($q) => $q->where("$table.program", $filters->program))
            // Partner filter keeps center/ih rows (partner_id IS NULL) so the Center
            // column stays populated, narrowing only the partner contribution.
            ->when(
                $filters->partner && $filters->partner !== 'all',
                function ($q) use ($table, $filters) {
                    $ids = is_array($filters->partner)
                        ? array_map('intval', $filters->partner)
                        : [(int) $filters->partner];
                    $q->where(function ($inner) use ($table, $ids) {
                        $inner->whereNull("$table.partner_id")
                            ->orWhereIn("$table.partner_id", $ids);
                    });
                }
            )
            ->when(
                $filters->farmer && $filters->farmer !== 'all',
                function ($q) use ($table, $filters) {
                    $ids = is_array($filters->farmer)
                        ? array_map('intval', $filters->farmer)
                        : [(int) $filters->farmer];
                    $q->where(function ($inner) use ($table, $ids) {
                        // Farmer filter keeps center/ih rows (farmer_id is null AND partner_id is null)
                        // and partner-own rows (farmer_id is null AND partner_id is set)
                        // so those columns stay populated, narrowing only the farmer contribution.
                        $inner->whereNull("$table.farmer_id")
                            ->orWhereIn("$table.farmer_id", $ids);
                    });
                }
            )
            ->where(function ($query) use ($table, $filters) {
                $hasLocationFilter = ($filters->region && $filters->region !== 'all')
                    || ($filters->province && $filters->province !== 'all')
                    || ($filters->district && $filters->district !== 'all')
                    || ($filters->city && $filters->city !== 'all');

                if (! $hasLocationFilter) {
                    return;
                }

                $program = $filters->program !== '' ? strtolower((string) $filters->program) : null;

                if ($program === 'ih') {
                    $query->whereExists(function ($sub) use ($table, $filters) {
                        $sub->selectRaw(1)
                            ->from('addresses')
                            ->whereColumn('addresses.addressable_id', '=', "$table.center_id")
                            ->where('addresses.addressable_type', '=', 'center')
                            ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                $regions = array_map(
                                    fn ($region) => AddressRegionEnum::fromLabel($region)?->value ?? $region,
                                    (array) $filters->region
                                );
                                $q->whereIn('addresses.region', $regions);
                            })
                            ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                            ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                            ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                    });
                } elseif ($program === 'cbed') {
                    $query->where(function ($outer) use ($table, $filters) {
                        $outer->whereExists(function ($sub) use ($table, $filters) {
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.partner_id")
                                ->where('addresses.addressable_type', '=', 'partner')
                                ->whereNotNull("$table.partner_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        })->orWhereExists(function ($sub) use ($table, $filters) {
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.farmer_id")
                                ->where('addresses.addressable_type', '=', 'farmer')
                                ->whereNotNull("$table.farmer_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        })->orWhereExists(function ($sub) use ($table, $filters) {
                            // Fallback to center when CBED row has no partner/farmer (institutional)
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.center_id")
                                ->where('addresses.addressable_type', '=', 'center')
                                ->whereNull("$table.partner_id")
                                ->whereNull("$table.farmer_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        });
                    });
                } else {
                    // program=all or null: match any of the three, picking the most specific entity per row
                    $query->where(function ($outer) use ($table, $filters) {
                        $outer->whereExists(function ($sub) use ($table, $filters) {
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.center_id")
                                ->where('addresses.addressable_type', '=', 'center')
                                ->whereNull("$table.partner_id")
                                ->whereNull("$table.farmer_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        })->orWhereExists(function ($sub) use ($table, $filters) {
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.partner_id")
                                ->where('addresses.addressable_type', '=', 'partner')
                                ->whereNotNull("$table.partner_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        })->orWhereExists(function ($sub) use ($table, $filters) {
                            $sub->selectRaw(1)
                                ->from('addresses')
                                ->whereColumn('addresses.addressable_id', '=', "$table.farmer_id")
                                ->where('addresses.addressable_type', '=', 'farmer')
                                ->whereNotNull("$table.farmer_id")
                                ->when($filters->region && $filters->region !== 'all', function ($q) use ($filters) {
                                    $regions = array_map(fn ($r) => AddressRegionEnum::fromLabel($r)?->value ?? $r, (array) $filters->region);
                                    $q->whereIn('addresses.region', $regions);
                                })
                                ->when($filters->province && $filters->province !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.province', $filters->province))
                                ->when($filters->district && $filters->district !== 'all', fn ($q) => $this->applyDistrictMatch($q, $filters->district))
                                ->when($filters->city && $filters->city !== 'all', fn ($q) => $this->applyAddressMatch($q, 'addresses.city', $filters->city));
                        });
                    });
                }
            });
    }

    private function applyWhereIn($query, string $column, int|array|null $value): void
    {
        if (is_array($value)) {
            $query->whereIn($column, $value);
        } else {
            $query->where($column, $value);
        }
    }

    private function applyAddressMatch($query, string $column, string|array|null $values): void
    {
        $values = (array) $values;
        $query->where(function ($inner) use ($column, $values) {
            foreach ($values as $value) {
                $inner->orWhereRaw("$column = ?", [trim((string) $value)]);
            }
        });
    }

    private function applyDistrictMatch($query, string|array|null $values): void
    {
        $aliases = collect((array) $values)
            ->flatMap(fn ($value) => $this->districtAliases($value))
            ->unique()
            ->values();

        $query->where(function ($inner) use ($aliases) {
            foreach ($aliases as $alias) {
                $inner->orWhereRaw('addresses.district = ?', [trim((string) $alias)]);
            }
        });
    }

    private function districtAliases(string|int $value): array
    {
        $enum = AddressDistrictEnum::tryFrom((string) $value)
            ?? AddressDistrictEnum::tryFroMatches((string) $value);

        if ($enum === null) {
            return [(string) $value];
        }

        return array_merge([$enum->value], AddressDistrictEnum::matches()[$enum->value] ?? []);
    }
}

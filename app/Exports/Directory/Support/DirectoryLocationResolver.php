<?php

namespace App\Exports\Directory\Support;

use App\Enums\AddressCategoryEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Handles deterministic address resolution and location filtering.
 */
final class DirectoryLocationResolver
{
    /**
     * Resolve a single deterministic address for entities.
     * Prefers default/primary category, then newest by id.
     *
     * @param  array<int>  $entityIds
     * @return array<int, object>
     */
    public static function resolveAddresses(string $entityType, array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        return DB::table('addresses')
            ->select(['addressable_id', 'region', 'province', 'city', 'district', 'category'])
            ->where('addressable_type', $entityType)
            ->whereIn('addressable_id', $entityIds)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [AddressCategoryEnum::DEFAULT->value])
            ->orderByDesc('id')
            ->get()
            ->keyBy('addressable_id')
            ->all();
    }

    /**
     * Apply escaped, driver-aware case-insensitive district filtering.
     */
    public static function applyDistrictFilter(Builder $query, string $table, string|array|null $district): void
    {
        if ($district === null || $district === 'all') {
            return;
        }

        $dbValues = collect((array) $district)
            ->map(fn ($value) => self::escapeDistrictWildcard($value))
            ->all();

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $query->where(function ($nested) use ($table, $dbValues, $driver) {
            foreach ($dbValues as $value) {
                if ($driver === 'pgsql') {
                    // PostgreSQL: use ILIKE for case-insensitive matching
                    $nested->orWhere("{$table}.district", 'ILIKE', $value);
                } else {
                    // MySQL and others: use LOWER() LIKE for case-insensitive matching
                    $nested->orWhere(DB::raw("LOWER({$table}.district)"), 'LIKE', strtolower($value));
                }
            }
        });
    }

    /**
     * Escape wildcard characters in district input to prevent SQL injection.
     */
    private static function escapeDistrictWildcard(string $value): string
    {
        // Escape % and _ by prepending backslash, then wrap with % for partial matching
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);

        return "%{$escaped}%";
    }

    /**
     * Apply address filtering using a whereExists predicate to avoid entity duplication.
     */
    public static function applyAddressFilter(
        Builder $query,
        string $entityType,
        string $entityColumn,
        string|array|null $region,
        string|array|null $province,
        string|array|null $city,
        string|array|null $district,
    ): void {
        $query->whereExists(function ($subQuery) use ($entityType, $entityColumn, $region, $province, $city, $district) {
            $subQuery->select(DB::raw(1))
                ->from('addresses')
                ->whereColumn('addresses.addressable_id', $entityColumn)
                ->where('addresses.addressable_type', $entityType);

            if ($region !== null && $region !== 'all') {
                $subQuery->whereIn('addresses.region', (array) $region);
            }

            if ($province !== null && $province !== 'all') {
                $subQuery->whereIn('addresses.province', (array) $province);
            }

            if ($city !== null && $city !== 'all') {
                $subQuery->whereIn('addresses.city', (array) $city);
            }

            if ($district !== null && $district !== 'all') {
                // Apply escaped district filter inline since we're in a subquery
                $subQuery->where(function ($nested) use ($district) {
                    $dbValues = collect((array) $district)
                        ->map(fn ($value) => self::escapeDistrictWildcard($value))
                        ->all();

                    $driver = DB::connection()->getDriverName();
                    foreach ($dbValues as $value) {
                        if ($driver === 'pgsql') {
                            $nested->orWhere('addresses.district', 'ILIKE', $value);
                        } else {
                            $nested->orWhere(DB::raw('LOWER(addresses.district)'), 'LIKE', strtolower($value));
                        }
                    }
                });
            }
        });
    }
}

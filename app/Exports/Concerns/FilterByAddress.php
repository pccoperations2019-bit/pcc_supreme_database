<?php

namespace App\Exports\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FilterByAddress
{
    protected function applyAddressFilters(
        Builder $query,
        string|array|null $region,
        string|array|null $province,
        string|array|null $city,
        ?string $addressKeyword,
    ): Builder {
        return $query
            ->when($region, function (Builder $query) use ($region) {
                $query->whereHas('address', function (Builder $addressQuery) use ($region) {
                    if (is_array($region)) {
                        $addressQuery->whereIn('region', $region);

                        return;
                    }

                    $addressQuery->where('region', $region);
                });
            })
            ->when($province, function (Builder $query) use ($province) {
                $query->whereHas('address', function (Builder $addressQuery) use ($province) {
                    if (is_array($province)) {
                        $addressQuery->where(function (Builder $innerQuery) use ($province) {
                            foreach ($province as $value) {
                                $innerQuery->orWhereRaw('LOWER(TRIM(province)) = LOWER(TRIM(?))', [$value]);
                            }
                        });

                        return;
                    }

                    $addressQuery->whereRaw('LOWER(TRIM(province)) = LOWER(TRIM(?))', [$province]);
                });
            })
            ->when($city, function (Builder $query) use ($city) {
                $query->whereHas('address', function (Builder $addressQuery) use ($city) {
                    if (is_array($city)) {
                        $addressQuery->where(function (Builder $innerQuery) use ($city) {
                            foreach ($city as $value) {
                                $innerQuery->orWhereRaw('LOWER(TRIM(city)) = LOWER(TRIM(?))', [$value]);
                            }
                        });

                        return;
                    }

                    $addressQuery->whereRaw('LOWER(TRIM(city)) = LOWER(TRIM(?))', [$city]);
                });
            })
            ->when($addressKeyword, function (Builder $query) use ($addressKeyword) {
                $keyword = trim((string) $addressKeyword);

                $query->whereHas('address', function (Builder $addressQuery) use ($keyword) {
                    $addressQuery->where(function (Builder $innerQuery) use ($keyword) {
                        $innerQuery
                            ->where('street', 'like', "%{$keyword}%")
                            ->orWhere('barangay', 'like', "%{$keyword}%")
                            ->orWhere('city', 'like', "%{$keyword}%")
                            ->orWhere('province', 'like', "%{$keyword}%")
                            ->orWhere('region', 'like', "%{$keyword}%");
                    });
                });
            });
    }
}

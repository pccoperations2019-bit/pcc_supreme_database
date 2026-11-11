<?php

namespace App\Casts;

use App\Enums\AddressRegionEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class RegionEnumCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof AddressRegionEnum) {
            return $value;
        }

        return AddressRegionEnum::tryFroMatches($value)
            ?? AddressRegionEnum::fromLabel($value)
            ?? AddressRegionEnum::tryFrom($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof AddressRegionEnum) {
            return $value->value;
        }

        return AddressRegionEnum::tryFroMatches($value)
            ?? AddressRegionEnum::fromLabel($value)
            ?? AddressRegionEnum::tryFrom($value)?->value
            ?? $value;
    }
}

<?php

namespace App\Support;

use App\Enums\AddressRegionEnum;

/**
 * Resolves a region for a row during service record imports.
 *
 * Resolves an explicit region label into an AddressRegionEnum value.
 */
class RegionResolver
{
    /**
     * Normalize a region label into an AddressRegionEnum value.
     *
     * Handles enum matches plus PSGC-style names like
     * "Region IX (Zamboanga Peninsula)", "Region IV-A (CALABARZON)" and
     * "National Capital Region (NCR)".
     */
    public static function resolveRegionValue(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $case = AddressRegionEnum::tryFroMatches($name)
            ?? AddressRegionEnum::fromLabel($name)
            ?? AddressRegionEnum::tryFrom($name);

        if ($case) {
            return $case->value;
        }

        // "Region IX (Zamboanga Peninsula)", "Region IV-A (CALABARZON)", "Region XIII (Caraga)"
        if (preg_match('/Region\s+(IV\s*-\s*A|IV\s*-\s*B|[IVX]+\b)/i', $name, $matches)) {
            $candidate = strtoupper(preg_replace('/\s+/', '', $matches[1]));

            $case = AddressRegionEnum::tryFrom($candidate);

            if ($case) {
                return $case->value;
            }
        }

        // "National Capital Region (NCR)", "Cordillera Administrative Region (CAR)", "(BARMM)", "(NIR)"
        if (preg_match('/\b(NCR|CAR|BARMM|NIR)\b/i', $name, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}

<?php

namespace App\Support;

use App\Enums\ProgramEnum;
use App\Models\Center;

class Filters
{
    public const PARTNER_STATUS = 'partner_status';

    public const DOCUMENT_STATUS = 'document_status';

    public const PRODUCTION_SOURCE = 'production_source';

    public const PRODUCTION_ITEM = 'production_item';

    public const CONSOLIDATION_CATEGORY = 'consolidation_category';

    public const CENTER = 'center';

    public const PROGRAM = 'program';

    public const ALL_PROGRAM = 'all';

    /**
     * Resolve the program used for data aggregation.
     *
     * - 'all' (or Filters::ALL_PROGRAM) returns null so queries are not
     *   filtered by program, summing IH + CBED together.
     * - a specific program ('cbed' / 'ih') returns that program.
     * - an invalid or unset value falls back to the page's default program.
     */
    public static function resolveDataProgram(?string $value, ProgramEnum $default): ?ProgramEnum
    {
        if ($value === self::ALL_PROGRAM) {
            return null;
        }

        return ProgramEnum::tryFrom((string) $value) ?? $default;
    }

    public function historicalYears(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = [];

        for ($year = $currentYear; $year >= 2020; $year--) {
            $years[] = (string) $year;
        }

        return $years;
    }

    public function centers(): array
    {
        return Center::query()
            ->with('address')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }
}

<?php

namespace App\Support\Audits;

use App\Enums\AddressRegionEnum;
use App\Enums\Contracts\WithLabels;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a stored value into the wording a reviewer should read.
 *
 * The activity log stores what the database holds, which is right for diffing and
 * useless for reading. Two things leak through:
 *
 * - An enum's stored value. `land_ownership` holds `lgu`, which means nothing to
 *   someone auditing a dairy box; "Local Government Unit" means something.
 * - A PSGC code. The address cascade switched to storing codes, so a location that
 *   used to be stored as the name "Trinidad" now reads back as `701244000`. Worse,
 *   that reads as an *edit* - the same place, spelled differently, reported as
 *   somebody having moved the dairy box.
 *
 * Resolving before the comparison is what fixes both. The location collapses to one
 * name on both sides and is correctly reported as unchanged, and everything that
 * does move is recorded in words rather than in codes.
 */
class ValueLabels
{
    /**
     * Columns whose value is a PSGC code or name, and the table that resolves it.
     *
     * `district` is deliberately absent: it is stored as a label already ("1st",
     * "Lone District"), and the `districts` table keys those labels per city, so
     * looking one up by code could easily resolve to another city's district.
     *
     * @var array<string, string>
     */
    private const PSGC_TABLES = [
        'province' => 'provinces',
        'city' => 'cities',
        'municipality' => 'cities',
    ];

    /**
     * Resolved values, keyed "table:code", so a page of entries costs one lookup per
     * distinct code rather than one per value.
     *
     * @var array<string, string|null>
     */
    private static array $resolved = [];

    /**
     * The label for a value, or the value itself when there is nothing better to
     * say about it.
     */
    public static function for(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '' || is_array($value)) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return self::label($value);
        }

        if ($field === 'region') {
            return self::region($value);
        }

        $table = self::PSGC_TABLES[$field] ?? null;

        return $table === null ? $value : (self::psgcName($table, $value) ?? $value);
    }

    /**
     * The label for a stored value, given the enum its column is cast to.
     *
     * The activity log holds what the database holds, so an enum-cast column
     * arrives as its raw value and a field name is not enough to read it back:
     * `status` is a `ProfileStatusEnum` on a partner and a `DairyBoxEnum` on a
     * dairy box. The subject's own cast is the only thing that knows which.
     *
     * @param  class-string  $enum
     */
    public static function fromEnum(string $enum, mixed $value): mixed
    {
        if ($value === null || $value === '' || is_array($value)) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return self::label($value);
        }

        $case = $enum::tryFrom((string) $value);

        return $case === null ? $value : self::label($case);
    }

    /**
     * The wording for an enum case.
     *
     * Enums that publish labels are asked for theirs; the rest fall back to a
     * readable form of the value, which is how they are written in the first place
     * (`for_processing` reads as "For Processing").
     */
    private static function label(BackedEnum $case): string
    {
        if ($case instanceof WithLabels) {
            return $case::labels()[$case->value] ?? (string) $case->value;
        }

        return Str::headline((string) $case->value);
    }

    /**
     * A region's label, whether it arrived as a PSGC code, the full name, or the
     * short form the column held before the cascade switched to codes.
     */
    private static function region(mixed $value): string
    {
        $name = is_numeric($value)
            ? self::psgcName('regions', $value)
            : (string) $value;

        if ($name === null) {
            return (string) $value;
        }

        $case = AddressRegionEnum::tryFroMatches($name)
            ?? AddressRegionEnum::fromLabel($name)
            ?? AddressRegionEnum::tryFrom($name);

        return $case === null ? $name : (self::label($case) ?: $name);
    }

    /**
     * The name behind a PSGC code, or behind a value that is already a name.
     *
     * Both spellings are tried so that the two sides of a comparison converge: a
     * location stored as "Trinidad" and the same location stored as `701244000`
     * both come back as "Trinidad".
     */
    private static function psgcName(string $table, mixed $value): ?string
    {
        $key = $table.':'.$value;

        if (! array_key_exists($key, self::$resolved)) {
            self::$resolved[$key] = DB::table($table)
                ->where('code', $value)
                ->orWhere('name', $value)
                ->value('name');
        }

        return self::$resolved[$key];
    }

    /**
     * Forget what has been resolved. Only useful between tests - a long-lived
     * worker would otherwise hold a stale name for a code the data has since
     * renamed.
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }
}

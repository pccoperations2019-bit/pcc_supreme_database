<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parser for the registry training-activity date cells (BJ:CM pairs).
 *
 * A cell may hold a single date, a same-month range ("03/30-31/2022",
 * "05/24-25/2022", "Dec. 12-13, 2022"), an "&" or "to" range
 * ("11/18 & 21/2022", "09/29/2022 to 10/01/2022"), or a newline-separated
 * list of dates. Start is the first date, end the last (null for a single
 * date).
 *
 * Strictness (per spec section 8): date columns are written ONLY when the
 * whole cell is dates. Any non-date remainder (status words, notes such as
 * "(Graduation)") yields start/end null with the remainder in `note`, so
 * the caller keeps everything raw in metadata plus the ledger.
 *
 * @return array{start: ?string, end: ?string, note: ?string, ambiguous_order: bool}
 */
final class RegistryActivityCell
{
    /**
     * @return array{start: ?string, end: ?string, note: ?string, ambiguous_order: bool}
     */
    public static function parse(mixed $value): array
    {
        $empty = ['start' => null, 'end' => null, 'note' => null, 'ambiguous_order' => false];

        if ($value === null) {
            return $empty;
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: []),
            static fn (string $line): bool => $line !== ''
        ));

        if ($lines === []) {
            return $empty;
        }

        $dates = [];
        $ambiguous = false;

        foreach ($lines as $line) {
            $parsed = self::parseLine($line);

            if ($parsed === null) {
                return [
                    'start' => null,
                    'end' => null,
                    'note' => trim((string) $value),
                    'ambiguous_order' => false,
                ];
            }

            foreach ($parsed['dates'] as $date) {
                $dates[] = $date;
            }

            $ambiguous = $ambiguous || $parsed['ambiguous'];
        }

        if ($dates === []) {
            return $empty;
        }

        return [
            'start' => $dates[0],
            'end' => count($dates) > 1 ? $dates[count($dates) - 1] : null,
            'note' => null,
            'ambiguous_order' => $ambiguous,
        ];
    }

    /**
     * @return array{dates: list<string>, ambiguous: bool}|null null when the line is not pure dates.
     */
    private static function parseLine(string $line): ?array
    {
        $single = RegistryStrictDate::parse($line);

        if ($single !== null) {
            return ['dates' => [$single['date']], 'ambiguous' => $single['ambiguous_order']];
        }

        // "09/29/2022 to 10/01/2022".
        if (preg_match('/^(.*?)\s+to\s+(.*?)$/i', $line, $m)) {
            return self::parsePair($m[1], $m[2]);
        }

        // "11/18 & 21/2022" (left side may omit the year).
        if (str_contains($line, '&')) {
            $parts = array_map('trim', explode('&', $line));

            if (count($parts) === 2) {
                return self::parsePair($parts[0], $parts[1]);
            }

            return null;
        }

        // "03/30-31/2022" and "05/24-25/2022".
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{4})$/', $line, $m)) {
            return self::buildRange(
                self::strict("{$m[1]}/{$m[2]}/{$m[4]}"),
                self::strict("{$m[1]}/{$m[3]}/{$m[4]}")
            );
        }

        // "Dec. 12-13, 2022".
        if (preg_match('/^([A-Za-z]+\.?)\s+(\d{1,2})\s*-\s*(\d{1,2}),\s*(\d{4})$/', $line, $m)) {
            return self::buildRange(
                self::strict("{$m[1]} {$m[2]}, {$m[4]}"),
                self::strict("{$m[1]} {$m[3]}, {$m[4]}")
            );
        }

        return null;
    }

    /**
     * Parse a two-sided range. Either side may omit a component the other
     * side provides: a year-less left ("11/18 & 21/2022" inherits the
     * right side's year) or a month-less right ("11/18 & 21/2022" inherits
     * the left side's month).
     *
     * @return array{dates: list<string>, ambiguous: bool}|null
     */
    private static function parsePair(string $left, string $right): ?array
    {
        $leftText = trim($left);
        $rightText = trim($right);

        $leftParsed = RegistryStrictDate::parse($leftText);
        $rightParsed = RegistryStrictDate::parse($rightText);

        if ($leftParsed !== null && $rightParsed !== null) {
            return [
                'dates' => [$leftParsed['date'], $rightParsed['date']],
                'ambiguous' => $leftParsed['ambiguous_order'] || $rightParsed['ambiguous_order'],
            ];
        }

        // "11/18 & 21/2022": left is month/day, right is day/year.
        if (preg_match('/^(\d{1,2})\s*[\/\-]\s*(\d{1,2})$/', $leftText, $lm)
            && preg_match('/^(\d{1,2})\s*[\/\-]\s*(\d{4})$/', $rightText, $rm)
        ) {
            return self::buildRange(
                self::strict("{$lm[1]}/{$lm[2]}/{$rm[2]}"),
                self::strict("{$lm[1]}/{$rm[1]}/{$rm[2]}")
            );
        }

        // Year-less side inherits the other side's year ("09/29 to 10/01/2022").
        if ($leftParsed === null && $rightParsed !== null
            && preg_match('/^\d{1,2}\s*[\/\-]\s*\d{1,2}$/', $leftText)
        ) {
            $leftParsed = RegistryStrictDate::parse($leftText.'/'.substr($rightParsed['date'], 0, 4));
        }

        if ($leftParsed !== null && $rightParsed === null
            && preg_match('/^\d{1,2}\s*[\/\-]\s*\d{1,2}$/', $rightText)
        ) {
            $rightParsed = RegistryStrictDate::parse($rightText.'/'.substr($leftParsed['date'], 0, 4));
        }

        if ($leftParsed === null || $rightParsed === null) {
            return null;
        }

        return [
            'dates' => [$leftParsed['date'], $rightParsed['date']],
            'ambiguous' => $leftParsed['ambiguous_order'] || $rightParsed['ambiguous_order'],
        ];
    }

    /**
     * @return array{dates: list<string>, ambiguous: bool}|null
     */
    private static function buildRange(?array $start, ?array $end): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        return [
            'dates' => [$start['date'], $end['date']],
            'ambiguous' => $start['ambiguous_order'] || $end['ambiguous_order'],
        ];
    }

    /**
     * @return array{date: string, ambiguous_order: bool}|null
     */
    private static function strict(string $text): ?array
    {
        return RegistryStrictDate::parse($text);
    }
}

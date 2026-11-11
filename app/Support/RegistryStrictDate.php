<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Strict entire-string date parser for the CBIN/CCDP registry import.
 *
 * A value is accepted ONLY when the whole trimmed string is a date, an
 * Excel serial, or a datetime. A date embedded in a sentence
 * ("Construction started August 16, 2022", "2/10/2025 (Soft opening)")
 * is NEVER extracted — it returns null so the caller keeps it raw in
 * metadata plus the ledger.
 *
 * Expected order is month/day/year. When both numbers are 12 or less the
 * value still parses but `ambiguous_order` is true so the caller can add
 * a ledger warning. A first number above 12 is not a valid month and is
 * rejected (null).
 */
final class RegistryStrictDate
{
    private const SERIAL_MIN = 32874; // 1990-01-01 as an Excel serial.

    private const SERIAL_MAX = 73050; // 2100-01-01 as an Excel serial.

    /**
     * @return array{date: string, ambiguous_order: bool}|null Y-m-d on success.
     */
    public static function parse(mixed $value): ?array
    {
        if ($value instanceof DateTimeInterface) {
            return ['date' => $value->format('Y-m-d'), 'ambiguous_order' => false];
        }

        if (is_int($value) || is_float($value) || self::isNumericString($value)) {
            $serial = (float) $value;

            if ($serial < self::SERIAL_MIN || $serial > self::SERIAL_MAX) {
                return null;
            }

            try {
                $date = ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }

            return ['date' => $date, 'ambiguous_order' => false];
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        // ISO `2022-03-30` with an optional time part.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ]\d{1,2}:\d{2}(?::\d{2})?(?:\s*[APap][Mm])?)?$/', $text, $m)) {
            return self::checked((int) $m[1], (int) $m[2], (int) $m[3], false);
        }

        // US numeric `m/d/Y` (2- or 4-digit year) with an optional time part.
        // Spaces around the separators are tolerated (Excel artefacts).
        if (preg_match('/^(\d{1,2})\s*[\/\-]\s*(\d{1,2})\s*[\/\-]\s*(\d{2}|\d{4})(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:[APap][Mm])?)?$/', $text, $m)) {
            $month = (int) $m[1];
            $day = (int) $m[2];

            if ($month < 1 || $month > 12) {
                return null;
            }

            $parsed = self::checked(self::expandYear($m[3]), $month, $day, false);

            if ($parsed === null) {
                return null;
            }

            $parsed['ambiguous_order'] = $day <= 12;

            return $parsed;
        }

        // Month name, entire string only: "Dec. 12, 2022", "August 16, 2022".
        if (preg_match('/^([A-Za-z]+)\.?\s+(\d{1,2}),\s+(\d{4})$/', $text, $m)) {
            $month = self::monthNumber($m[1]);

            if ($month === null) {
                return null;
            }

            return self::checked((int) $m[3], $month, (int) $m[2], false);
        }

        return null;
    }

    private static function isNumericString(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\s*[+-]?\d+(\.\d+)?\s*$/', $value) === 1;
    }

    private static function expandYear(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        $short = (int) $year;

        return $short < 50 ? 2000 + $short : 1900 + $short;
    }

    private static function monthNumber(string $name): ?int
    {
        $months = [
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sept' => 9, 'sep' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
        ];

        return $months[strtolower($name)] ?? null;
    }

    /**
     * @return array{date: string, ambiguous_order: bool}|null
     */
    private static function checked(int $year, int $month, int $day, bool $ambiguous): ?array
    {
        if ($year < 1990 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return [
            'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'ambiguous_order' => $ambiguous,
        ];
    }
}

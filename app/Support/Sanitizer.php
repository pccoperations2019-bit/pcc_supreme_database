<?php

namespace App\Support;

use App\Enums\MilkProductionWeekEnum;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class Sanitizer
{
    public static function parseDate($value): ?DateTimeInterface
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Date::instance(
                    ExcelDate::excelToDateTimeObject($value)
                );
            }

            return Date::parse($value);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$value}");

            return null;
        }
    }

    public static function parseDateWithTypoCorrection($value): ?DateTimeInterface
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Date::instance(
                    ExcelDate::excelToDateTimeObject($value)
                );
            }

            // Handle common typos and non-date values
            $normalizedValue = self::normalizeDateStringWithTypoCorrection($value);

            if ($normalizedValue === null) {
                return null;
            }

            return Date::parse($normalizedValue);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$value}");

            return null;
        }
    }

    public static function normalizeDateStringWithTypoCorrection($value): ?string
    {
        $value = trim((string) $value);

        // Handle non-date values
        if (in_array(strtoupper($value), ['N/A', 'NO MOA', '']) ||
            strpos(strtoupper($value), 'NO MOA') === 0) {
            return null;
        }

        // Common month name typo corrections - order matters to avoid conflicts
        $typos = [
            'Febuary' => 'February',
            'Janauary' => 'January',
            'Janaury' => 'January',
            'Septembr' => 'September',
            'Sepember' => 'September',
            'Sepetmber' => 'September',
            'Novemebr' => 'November',
            'Juner' => 'June',
            'Aprill' => 'April',
            'Decenber' => 'December',
        ];

        // Replace typos - use case-insensitive replacement
        foreach ($typos as $typo => $correct) {
            $value = preg_replace('/'.preg_quote($typo, '/').'/i', $correct, $value);
        }

        // Handle malformed dates like "3/3/2026/"
        if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{4})\/$/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    public static function makeUniqueCode(string $baseCode, string $modelClass, string $column = 'code', int $maxLength = 12): string
    {
        $prefix = strtoupper(substr($baseCode !== '' ? $baseCode : 'CTR', 0, $maxLength));
        $code = $prefix;
        $i = 1;

        while ($modelClass::where($column, $code)->exists()) {
            $i++;
            $suffix = (string) $i;
            $code = substr($prefix, 0, max(1, $maxLength - strlen($suffix))).$suffix;
        }

        return $code;
    }

    public static function parseNumeric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = (string) $value;

        $cleaned = str_replace(',', '', $cleaned);

        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        return (float) $cleaned;
    }

    public static function parseString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalize a project involvement tag (e.g. "CBIN 2019") to its canonical
     * hyphenated form ("CBIN-2019") so fund source values stay comparable with
     * the `projects.project_involvement` options they are selected from.
     *
     * Mirrors `normalizeProjectInvolvement` in resources/js/lib/utils.js.
     */
    public static function parseProjectInvolvement(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_bool($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '-', $trimmed) ?? $trimmed;
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a list of project involvement tags, dropping blanks and duplicates.
     *
     * @param  iterable<mixed>  $values
     * @return array<int, string>
     */
    public static function parseProjectInvolvementList(iterable $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $parsed = self::parseProjectInvolvement($value);

            if ($parsed !== null && ! in_array($parsed, $normalized, true)) {
                $normalized[] = $parsed;
            }
        }

        return $normalized;
    }

    public static function normalizePartnerNameForLookup(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return strtolower($name);
    }

    public static function parseBooleanNullable(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public static function parseMilkVolume(mixed $value, int $rowIndex, int $week): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            Log::warning("Row {$rowIndex}, Week {$week}: Non-numeric volume '{$value}', skipping");

            return null;
        }

        $volume = (float) $value;

        if ($volume < 0) {
            Log::warning("Row {$rowIndex}, Week {$week}: Negative volume '{$value}', skipping");

            return null;
        }

        return $volume;
    }

    public static function parseWeekDateRange(string $header, int $week, ?int $year): array
    {
        $result = ['start' => null, 'end' => null];

        if (! preg_match('/([A-Za-z]+)\s+(\d+)(?:\s*-\s*([A-Za-z]+\s+\d+|\d+))?/i', $header, $matches)) {
            Log::warning("Could not parse date range from header: {$header}", ['week' => $week]);

            return $result;
        }

        $startMonth = $matches[1];
        $startDay = (int) $matches[2];
        $endPart = $matches[3] ?? null;
        $year = $year ?? Date::now()->year;

        try {
            $startDate = Date::createFromFormat('F j Y', "{$startMonth} {$startDay} {$year}");
            $result['start'] = $startDate;

            if ($endPart) {
                if (preg_match('/([A-Za-z]+)\s+(\d+)/i', $endPart, $endMatches)) {
                    $endMonth = $endMatches[1];
                    $endDay = (int) $endMatches[2];
                    $result['end'] = Date::createFromFormat('F j Y', "{$endMonth} {$endDay} {$year}");
                } elseif (is_numeric($endPart)) {
                    $endDay = (int) $endPart;
                    $result['end'] = Date::createFromFormat('F j Y', "{$startMonth} {$endDay} {$year}");
                } else {
                    $result['end'] = $startDate->copy();
                }
            } else {
                $result['end'] = $startDate->copy();
            }

        } catch (\Exception $e) {
            Log::warning("Failed parsing date range from header: {$header}", [
                'error' => $e->getMessage(),
                'week' => $week,
            ]);
        }

        return $result;
    }

    public static function parseWeekRangeFromHeader(string $header, ?int $defaultYear = null): ?array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($header));
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/([A-Za-z]+\.?)[\s]*(\d{1,2})\s*-\s*([A-Za-z]+\.?)*\s*(\d{1,2})/', $normalized, $matches)) {
            return null;
        }

        $startMonth = $matches[1] ?? null;
        $startDay = $matches[2] ?? null;
        $endMonth = $matches[3] ?: $startMonth;
        $endDay = $matches[4] ?? null;
        $year = $defaultYear ?? (int) now()->format('Y');

        $start = self::parseMonthDay($startMonth, $startDay, $year);
        if (! $start instanceof \DateTimeImmutable) {
            return null;
        }

        $endYear = $year;
        if ($endMonth && $startMonth) {
            $startMonthIndex = self::monthIndex($startMonth);
            $endMonthIndex = self::monthIndex($endMonth);
            if ($startMonthIndex && $endMonthIndex && $endMonthIndex < $startMonthIndex) {
                $endYear = $year + 1;
            }
        }

        $end = self::parseMonthDay($endMonth, $endDay, $endYear) ?? $start;

        return [
            'start' => $start,
            'end' => $end,
            'week' => null,
        ];
    }

    public static function parseMonthDay(?string $month, ?string $day, int $year): ?\DateTimeImmutable
    {
        if (! $month || ! $day) {
            return null;
        }

        $month = rtrim(trim($month), '.');
        $month = strtolower($month) === 'sept' ? 'Sep' : $month;

        $date = \DateTimeImmutable::createFromFormat('!M j Y', "{$month} {$day} {$year}");
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        return \DateTimeImmutable::createFromFormat('!F j Y', "{$month} {$day} {$year}") ?: null;
    }

    public static function monthIndex(string $month): ?int
    {
        $month = rtrim(trim($month), '.');
        $month = strtolower($month) === 'sept' ? 'Sep' : $month;

        $date = \DateTimeImmutable::createFromFormat('!M', $month) ?: \DateTimeImmutable::createFromFormat('!F', $month);

        return $date instanceof \DateTimeImmutable ? (int) $date->format('n') : null;
    }

    /**
     * Parse date range from a week header like "January 1-5 (WK 1)".
     */
    public static function parseDateRange(string $header, ?int $fallbackYear = null): array
    {
        $result = ['start' => null, 'end' => null];
        $year = $fallbackYear ?? Date::now()->year;

        if (preg_match('/([A-Za-z]+)\s+(\d+)(?:\s*-\s*([A-Za-z]+\s+\d+|\d+))?/i', $header, $matches)) {
            $startMonth = $matches[1];
            $startDay = (int) $matches[2];
            $endPart = $matches[3] ?? null;

            try {
                $startDate = Date::createFromFormat('F j Y', "{$startMonth} {$startDay} {$year}");
                $result['start'] = $startDate;

                if ($endPart) {
                    if (preg_match('/([A-Za-z]+)\s+(\d+)/i', $endPart, $endMatches)) {
                        $endMonth = $endMatches[1];
                        $endDay = (int) $endMatches[2];
                        $result['end'] = Date::createFromFormat('F j Y', "{$endMonth} {$endDay} {$year}");
                    } elseif (is_numeric($endPart)) {
                        $endDay = (int) $endPart;
                        $result['end'] = Date::createFromFormat('F j Y', "{$startMonth} {$endDay} {$year}");
                    } else {
                        $result['end'] = $startDate->copy();
                    }
                } else {
                    $result['end'] = $startDate->copy();
                }
            } catch (\Exception $e) {
                Log::warning("Failed to parse date range from header: {$header}", ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning("Could not parse date range from header: {$header}");
        }

        return $result;
    }

    /**
     * Parse week headers and year column index from a header row collection.
     */
    public static function parseWeekHeaders(Collection $headerRow): array
    {
        $weekHeaders = [];
        $yearColumnIndex = null;

        foreach ($headerRow as $colIndex => $cell) {
            $headerStr = trim((string) $cell);

            if (strtoupper($headerStr) === 'YEAR') {
                $yearColumnIndex = $colIndex;

                continue;
            }

            if ($colIndex < 2 || ($headerStr === '' || $headerStr === '0')) {
                continue;
            }

            if (preg_match('/\(WK\s*(\d+)\)/i', $headerStr, $matches)) {
                $weekNumber = (int) $matches[1];

                // Validate week number using enum
                if (MilkProductionWeekEnum::isValid($weekNumber)) {
                    $weekHeaders[$colIndex] = [
                        'week' => $weekNumber,
                        'header' => $headerStr,
                    ];
                } else {
                    Log::warning("Invalid week number '{$weekNumber}' in header: {$headerStr}", [
                        'column' => $colIndex,
                    ]);
                }
            }
        }

        return [
            'weekHeaders' => $weekHeaders,
            'yearColumnIndex' => $yearColumnIndex,
        ];
    }

    /**
     * Parse week headers with Production, Sales, and Traded columns from merged headers (row 2) and sub-headers (row 3).
     * Returns an array mapping column indices to week info with type (production, sales, traded).
     */
    public static function parseWeekHeadersWithTypes(Collection $mergedHeaderRow, Collection $subHeaderRow): array
    {
        $weekHeaders = [];
        $yearColumnIndex = null;
        $weekBlocks = []; // Store week blocks: [startCol => weekInfo]

        Log::info('[Sanitizer::parseWeekHeadersWithTypes] Starting parsing', [
            'merged_header_count' => $mergedHeaderRow->count(),
            'sub_header_count' => $subHeaderRow->count(),
        ]);

        // First pass: find year column and identify week header blocks
        // Note: Merged cells in Excel only have the value in the first column, others are empty
        foreach ($mergedHeaderRow as $colIndex => $cell) {
            $headerStr = trim((string) $cell);

            if (strtoupper($headerStr) === 'YEAR') {
                $yearColumnIndex = $colIndex;
                Log::info("[Sanitizer::parseWeekHeadersWithTypes] Found YEAR column at index {$colIndex}");

                continue;
            }

            if ($colIndex < 3) { // Skip CENTER CODE (0), PARTNER CODE (1), YEAR (2) columns
                continue;
            }

            // Check if this is a week header (contains "(WK N)")
            // When cells are merged, only the first column has the text, others are empty
            if (preg_match('/\(WK\s*(\d+)\)/i', $headerStr, $matches)) {
                $weekNumber = (int) $matches[1];

                if (MilkProductionWeekEnum::isValid($weekNumber)) {
                    // Store this as the start of a week block
                    // The merged header spans 3 columns: this one (Production), next (Sales), next (Traded)
                    $weekBlocks[$colIndex] = [
                        'week' => $weekNumber,
                        'header' => $headerStr,
                    ];
                    Log::debug('[Sanitizer::parseWeekHeadersWithTypes] Found week block', [
                        'column_index' => $colIndex,
                        'week_number' => $weekNumber,
                        'header' => $headerStr,
                        'spans_columns' => [$colIndex, $colIndex + 1, $colIndex + 2],
                    ]);
                } else {
                    Log::warning('[Sanitizer::parseWeekHeadersWithTypes] Invalid week number in header', [
                        'column_index' => $colIndex,
                        'week_number' => $weekNumber,
                        'header' => $headerStr,
                    ]);
                }
            }
        }

        Log::info('[Sanitizer::parseWeekHeadersWithTypes] Found week blocks', [
            'week_blocks_count' => count($weekBlocks),
            'week_blocks' => $weekBlocks,
        ]);

        // Second pass: map sub-headers to week blocks
        // Row 3 has: empty/merged cells for CENTER CODE, PARTNER CODE, YEAR (columns 0-2)
        // Then Production, Sales, Traded for each week (3 columns per week)
        foreach ($subHeaderRow as $colIndex => $cell) {
            $subHeaderStr = strtoupper(trim((string) $cell));

            if ($colIndex < 3) { // Skip CENTER CODE (0), PARTNER CODE (1), YEAR (2) columns - these are empty/merged in row 3
                continue;
            }

            // Find which week block this column belongs to
            // Each week block spans exactly 3 columns: Production, Sales, Traded
            $matchedWeek = null;
            $matchedStartCol = null;

            foreach ($weekBlocks as $startCol => $weekInfo) {
                // Check if this column is within the 3-column range for this week
                // startCol is the first column of the merged header (has the week text)
                // startCol, startCol+1, startCol+2 are the 3 columns for Production, Sales, Traded
                if ($colIndex >= $startCol && $colIndex < $startCol + 3) {
                    $matchedWeek = $weekInfo;
                    $matchedStartCol = $startCol;
                    break;
                }
            }

            if ($matchedWeek && in_array($subHeaderStr, ['PRODUCTION', 'SALES', 'TRADED'])) {
                $weekHeaders[$colIndex] = [
                    'week' => $matchedWeek['week'],
                    'header' => $matchedWeek['header'],
                    'type' => strtolower($subHeaderStr),
                    'startCol' => $matchedStartCol,
                ];
                Log::debug('[Sanitizer::parseWeekHeadersWithTypes] Mapped sub-header', [
                    'column_index' => $colIndex,
                    'sub_header' => $subHeaderStr,
                    'week' => $matchedWeek['week'],
                    'type' => strtolower($subHeaderStr),
                    'week_start_col' => $matchedStartCol,
                ]);
            } elseif ($matchedWeek) {
                Log::debug('[Sanitizer::parseWeekHeadersWithTypes] Sub-header not recognized (might be empty merged cell)', [
                    'column_index' => $colIndex,
                    'sub_header' => $subHeaderStr ?: '(empty)',
                    'week' => $matchedWeek['week'],
                ]);
            } elseif ($subHeaderStr !== '' && $subHeaderStr !== '0') {
                Log::warning('[Sanitizer::parseWeekHeadersWithTypes] Sub-header found but no matching week block', [
                    'column_index' => $colIndex,
                    'sub_header' => $subHeaderStr,
                ]);
            }
        }

        Log::info('[Sanitizer::parseWeekHeadersWithTypes] Parsing completed', [
            'week_headers_count' => count($weekHeaders),
            'year_column_index' => $yearColumnIndex,
        ]);

        return [
            'weekHeaders' => $weekHeaders,
            'yearColumnIndex' => $yearColumnIndex,
        ];
    }

    /**
     * Generate a code from partner name
     * Example: "Nueva Segovia Consortium of Cooperatives" -> "NSCC"
     * It only use if partner name is not already in the database
     */
    public static function generateCodeFromName(string $name): string
    {
        $raw = trim($name);

        if (preg_match('/\(([A-Za-z]{2,32})\)/', $raw, $matches)) {
            $acronym = strtoupper($matches[1]);

            return substr($acronym, 0, 12);
        }

        $raw = preg_replace('/\([^)]*\)/', '', $raw);
        $name = preg_replace('/[^A-Za-z ]/', '', $raw);

        $words = Str::of($name)->explode(' ');
        $stopWords = ['of', 'the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'with'];

        $meaningfulWords = $words->filter(function ($word) use ($stopWords) {
            return ! in_array(strtolower(trim($word)), $stopWords) && trim($word) !== '';
        })->values();

        $prefix = '';
        if ($meaningfulWords->count() >= 3) {
            foreach ($meaningfulWords as $word) {
                $prefix .= Str::of($word)->upper()->substr(0, 1);
            }
        } else {
            $prefix = Str::of($name)->upper()->replace(' ', '')->substr(0, 3);
        }

        return strtoupper(substr($prefix, 0, 12));
    }

    /**
     * Clean partner name for matching by removing variations
     * Handles case differences, extra letters, parentheses, and special characters
     */
    public static function cleanPartnerNameForMatching(string $name): string
    {
        // Convert to lowercase
        $name = strtolower(trim($name));

        // Remove all content within parentheses and the parentheses themselves
        $name = preg_replace('/\([^)]*\)/', '', $name);

        // Remove special characters, keeping only letters, numbers, and spaces
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);

        // Remove extra spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Remove common words that don't affect identity
        $stopWords = ['the', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for', 'with'];
        $words = explode(' ', $name);
        $words = array_filter($words, function ($word) use ($stopWords) {
            return ! in_array(trim($word), $stopWords) && trim($word) !== '';
        });

        return implode(' ', $words);
    }

    /**
     * Check if two partner names are similar enough to be considered the same
     * Handles various matching strategies including exact, contains, and fuzzy matching
     */
    public static function areNamesSimilar(string $name1, string $name2): bool
    {
        // Exact match
        if ($name1 === $name2) {
            return true;
        }

        // Check if one name contains the other
        if (strpos($name1, $name2) !== false || strpos($name2, $name1) !== false) {
            return true;
        }

        // Check similarity using Levenshtein distance for short names
        if (strlen($name1) <= 20 && strlen($name2) <= 20) {
            $distance = levenshtein($name1, $name2);
            $maxLength = max(strlen($name1), strlen($name2));

            // If the distance is less than 30% of the longer string, consider them similar
            if ($maxLength > 0 && $distance / $maxLength < 0.3) {
                return true;
            }
        }

        // Check word similarity for longer names
        $words1 = explode(' ', $name1);
        $words2 = explode(' ', $name2);

        // If they have the same number of words and most words match
        if (count($words1) === count($words2) && count($words1) > 0) {
            $matchingWords = 0;
            foreach ($words1 as $word1) {
                foreach ($words2 as $word2) {
                    if ($word1 === $word2 ||
                        (strlen($word1) > 3 && strlen($word2) > 3 && levenshtein($word1, $word2) <= 1)) {
                        $matchingWords++;
                        break;
                    }
                }
            }

            // If at least 70% of words match, consider them similar
            if ($matchingWords / count($words1) >= 0.7) {
                return true;
            }
        }

        return false;
    }

    public static function parseFilters(array $filters): array
    {
        return collect($filters)
            ->map(fn ($value) => self::parseFilter($value))
            ->all();
    }

    /**
     * Parse filters for directory reports, preserving 'all' as a sentinel value
     * for location filters (region, province, city, district) so they can be used
     * to control column visibility without filtering the data.
     */
    public static function parseDirectoryFilters(array $filters): array
    {
        $locationKeys = ['region', 'province', 'city', 'district', 'division', 'partner', 'farmer'];

        return collect($filters)
            ->map(function ($value, $key) use ($locationKeys) {
                // For location filters, preserve 'all' as a sentinel value
                if (in_array($key, $locationKeys, true)) {
                    return self::parseLocationFilter($value);
                }

                // For other filters, use standard parsing
                return self::parseFilter($value);
            })
            ->all();
    }

    /**
     * Parse location filter value, preserving 'all' as a sentinel value.
     */
    public static function parseLocationFilter(mixed $value): mixed
    {
        if (in_array($value, [null, '', '0'], true)) {
            return null;
        }

        // Keep 'all' as a string value for location filters
        if ($value === 'all') {
            return 'all';
        }

        if (is_string($value) && str_contains($value, ',')) {
            $items = array_map('trim', explode(',', $value));

            return array_values(array_filter(array_map(fn ($item) => self::normalizeFilterValue($item), $items)));
        }

        if (is_array($value)) {
            // Keep 'all' as a string value for location filters
            if (in_array('all', $value, true)) {
                return 'all';
            }

            return array_values(array_filter(array_map(fn ($item) => self::normalizeFilterValue($item), $value)));
        }

        return self::normalizeFilterValue($value);
    }

    public static function parseFilter(mixed $value): mixed
    {
        if (in_array($value, [null, '', '0', 'all'], true)) {
            return null;
        }

        if (is_string($value) && str_contains($value, ',')) {
            $items = array_map('trim', explode(',', $value));

            return array_values(array_filter(array_map(fn ($item) => self::normalizeFilterValue($item), $items)));
        }

        if (is_array($value)) {
            if (in_array('all', $value, true)) {
                return null;
            }

            return array_values(array_filter(array_map(fn ($item) => self::normalizeFilterValue($item), $value)));
        }

        return self::normalizeFilterValue($value);
    }

    public static function normalizeFilterValue(mixed $value): mixed
    {
        if (in_array($value, [null, '', '0'], true)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Parse month name from header and return date range for that month.
     * Handles month names like "Jan", "Feb", "March", "September", etc.
     */
    public static function parseMonthRangeFromHeader(string $header, ?int $defaultYear = null): ?array
    {
        $normalized = strtolower(trim($header));
        if ($normalized === '') {
            return null;
        }

        // Map month names/abbreviations to month numbers
        $monthMap = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        if (! isset($monthMap[$normalized])) {
            return null;
        }

        $month = $monthMap[$normalized];
        $year = $defaultYear ?? (int) now()->format('Y');

        try {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d', "{$year}-{$month}-01");
            $end = $start->modify('last day of this month');

            return [
                'start' => $start,
                'end' => $end,
            ];
        } catch (\Exception $e) {
            Log::warning("Failed to parse month range from header: {$header}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

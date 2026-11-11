<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Date
{
    const DAILY = 'daily';

    const WEEKLY = 'weekly';

    const MONTHLY = 'monthly';

    const YEARLY = 'yearly';

    const ALL = 'all';

    const QUARTERLY = 'quarterly';

    public static function getValidFilters(): array
    {
        return [
            self::DAILY,
            self::WEEKLY,
            self::MONTHLY,
            self::YEARLY,
            self::ALL,
            self::QUARTERLY,
        ];
    }

    public static function getQuarter(int $month): string
    {
        return 'Q'.ceil($month / 3);
    }

    public static function getQuarterLabel(int $month): string
    {
        $quarter = self::getQuarter($month);

        return match ($quarter) {
            'Q1' => 'Jan - Mar',
            'Q2' => 'Apr - Jun',
            'Q3' => 'Jul - Sep',
            'Q4' => 'Oct - Dec',
            default => '',
        };
    }

    public static function currentYear(): int
    {
        return (int) date('Y');
    }

    public static function selectableYears(
        int $rangeBack = 7,
        string $allLabel = 'All Years'
    ): Collection {
        return collect([['id' => 'all', 'label' => $allLabel]])
            ->merge(
                collect(range(self::currentYear() - $rangeBack, self::currentYear()))
                    ->map(fn ($year) => [
                        'id' => (string) $year,
                        'label' => (string) $year,
                    ])
            );
    }

    public static function selectableYearRanges(
        int $rangeBack = 7,
        string $allLabel = 'All Year Ranges'
    ): Collection {
        return collect([['id' => 'all', 'label' => $allLabel]])
            ->merge(
                collect(range(self::currentYear() - $rangeBack, self::currentYear()))
                    ->map(fn ($year) => [
                        'id' => "{$year}-".($year + 1),
                        'label' => "{$year}-".($year + 1),
                    ])
            );
    }

    public static function yearRange(int $rangeBack = 7): array
    {
        return range(self::currentYear() - $rangeBack, self::currentYear());
    }

    public static function distinctProductionYears(): Collection
    {
        return DB::table('milk_productions')
            ->select('year')->distinct()->whereNotNull('year')
            ->union(DB::table('meat_productions')->select('year')->distinct()->whereNotNull('year'))
            ->union(DB::table('manure_productions')->select('year')->distinct()->whereNotNull('year'))
            ->union(DB::table('milk_sales')->select('year')->distinct()->whereNotNull('year'))
            ->union(DB::table('meat_sales')->select('year')->distinct()->whereNotNull('year'))
            ->union(DB::table('manure_sales')->select('year')->distinct()->whereNotNull('year'))
            ->pluck('year')
            ->push(self::currentYear())
            ->unique()
            ->sortDesc()
            ->values();
    }

    public static function distinctMilkFeedingYears(): Collection
    {
        return DB::table('milk_feeding_programs')
            ->select('year')->distinct()->whereNotNull('year')
            ->pluck('year')
            ->push(self::currentYear())
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * Shift a resolved date range (year int or [start, end] pair) back by one year.
     */
    public static function previousPeriod(mixed $dateRange): mixed
    {
        if (is_int($dateRange)) {
            return $dateRange - 1;
        }

        if (is_array($dateRange) && count($dateRange) === 2) {
            return [
                date('Y-m-01', strtotime('-1 year', strtotime($dateRange[0]))),
                date('Y-m-t', strtotime('-1 year', strtotime($dateRange[1]))),
            ];
        }

        return $dateRange;
    }

    public static function formatDate(mixed $date, string $format = 'M j, Y'): string
    {
        if (! $date) {
            return 'Not set';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }

        try {
            return \Illuminate\Support\Facades\Date::parse($date)->format($format);
        } catch (\Exception) {
            return (string) $date;
        }
    }

    /**
     * Resolve date range from filters with support for year/month selectors
     *
     * @param  array  $filters  The filter array from the request
     * @param  string  $prefix  The prefix for the filter keys (e.g., 'production', 'milkFeeding')
     * @return array|int Returns date range array ['start', 'end'] or year as int
     */
    public static function resolveDateRange(array $filters, string $prefix): array|int
    {
        $yearKey = $prefix.'Year';
        $monthKey = $prefix.'Month';
        $rangeKey = $prefix.'DateRange';

        // Check for year/month filters
        if (isset($filters[$yearKey]) || isset($filters[$monthKey])) {
            $year = isset($filters[$yearKey]) ? (int) $filters[$yearKey] : now()->year;

            if (! isset($filters[$monthKey]) || $filters[$monthKey] === '' || $filters[$monthKey] === null || strtolower((string) $filters[$monthKey]) === 'all') {
                return $year;
            }

            $month = (int) $filters[$monthKey];
            $startDate = sprintf('%d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));

            return [$startDate, $endDate];
        }

        // Fall back to date range filter or default
        $year = now()->year;
        $month = now()->month;
        $startDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        return isset($filters[$rangeKey]) ? explode(',', $filters[$rangeKey]) : (isset($filters['dateRange']) ? explode(',', $filters['dateRange']) : [$startDate, $endDate]);
    }
}

<?php

namespace App\Queries\Reports;

use App\Enums\MilkProductionDataTypeEnum;
use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class MilkProductionDashboardScorecardQuery
{
    public function execute(
        int $year,
        int $month,
        MilkProductionDataTypeEnum $reportType,
        array $filters = [],
    ): array {
        $month = max(1, min(12, $month));
        $year = max(2000, min(3000, $year));

        $isSales = $reportType === MilkProductionDataTypeEnum::SALES;
        $defaultProgram = ProgramEnum::IH->value;

        // Column aliases differ between the two tables
        $centerIdColumn = $isSales ? 'milk_sales.center_id' : 'milk_productions.center_id';
        $partnerIdColumn = $isSales ? 'milk_sales.partner_id' : 'milk_productions.partner_id';
        $monthColumn = $isSales ? 'milk_sales.month' : 'milk_productions.month';
        $dateColumn = $isSales ? 'milk_sales.transaction_date' : 'milk_productions.transaction_date';
        $volumeColumn = $isSales ? 'milk_sales.total_volume_sold' : 'milk_productions.total_volume_produced';

        // Resolved filter values
        $centerId = isset($filters['center_id']) ? (int) $filters['center_id'] : null;
        $partnerId = isset($filters['partner_id']) ? (int) $filters['partner_id'] : null;
        $program = isset($filters['program']) ? (string) $filters['program'] : null;

        // ── Monday-based week-of-month expression ─────────────────────────────
        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $firstMondayExpr = "DATE_SUB('{$firstDay}', INTERVAL (DAYOFWEEK('{$firstDay}') - 2 + 7) % 7 DAY)";
        $weekStartExpr = "DATE_SUB({$dateColumn}, INTERVAL (DAYOFWEEK({$dateColumn}) - 2 + 7) % 7 DAY)";
        $weekOfMonthExpr = "1 + FLOOR(DATEDIFF({$weekStartExpr}, {$firstMondayExpr}) / 7)";

        // ── Base query builder ─────────────────────────────────────────────────
        // For the sales path we LEFT JOIN milk_productions so we can read program.
        // For the production path milk_productions IS the base table.
        $base = $isSales
            ? DB::table('milk_sales')
                ->join('centers', 'milk_sales.center_id', '=', 'centers.id')
                ->leftJoin('partners', 'milk_sales.partner_id', '=', 'partners.id')
                ->leftJoin('milk_productions', 'milk_sales.milk_production_id', '=', 'milk_productions.id')
                ->where('milk_sales.year', $year)
            : DB::table('milk_productions')
                ->join('centers', 'milk_productions.center_id', '=', 'centers.id')
                ->leftJoin('partners', 'milk_productions.partner_id', '=', 'partners.id')
                ->where('milk_productions.year', $year);

        if ($centerId) {
            $base->where($centerIdColumn, $centerId);
        }
        if ($partnerId) {
            $base->where($partnerIdColumn, $partnerId);
        }
        if ($program) {
            // Use COALESCE so records with NULL program (no linked milk_production)
            // are treated as the default program rather than silently dropped.
            $base->whereRaw(
                'COALESCE(milk_productions.program, ?) = ?',
                [$defaultProgram, $program]
            );
        }

        // ── Week layout (PHP-side, no DB call) ────────────────────────────────
        $weeksInMonth = $this->mondayBasedWeeksInMonth($year, $month);

        // ── DB weeks actually present in data for this month ──────────────────
        $dbWeeks = (clone $base)
            ->where($monthColumn, $month)
            ->whereNotNull($dateColumn)
            ->select(DB::raw('DISTINCT('.$weekOfMonthExpr.') as week'))
            ->orderBy('week')
            ->pluck('week')
            ->map(fn ($w) => (int) $w)
            ->values()
            ->all();

        $weeks = array_values(array_unique(array_merge(array_keys($weeksInMonth), $dbWeeks)));
        sort($weeks, SORT_NUMERIC);
        $weekRanges = array_intersect_key($weeksInMonth, array_flip($weeks));

        // ── Single aggregated query replacing four separate round-trips ───────
        //
        // Returns per-center:
        //   monthly_total       – SUM of volume where month = $month
        //   ytd_total           – SUM of volume where month BETWEEN 1 AND $month
        //   prev_cum_total      – SUM of volume where month BETWEEN 1 AND $month-1
        //   week_N_volume       – SUM of volume for each week N inside the month
        //
        // Weekly volumes are pivoted via dynamic CASE expressions so we stay in
        // one query regardless of how many weeks a month has.
        $weekCases = [];
        foreach ($weeks as $w) {
            $weekCases[] = DB::raw(
                "COALESCE(SUM(CASE WHEN {$monthColumn} = {$month} AND ({$weekOfMonthExpr}) = {$w} AND {$dateColumn} IS NOT NULL THEN {$volumeColumn} ELSE 0 END), 0) as week_{$w}_volume"
            );
        }

        $partnerCases = [];
        foreach ($weeks as $w) {
            $partnerCases[] = DB::raw(
                "COUNT(DISTINCT CASE WHEN {$monthColumn} = {$month} AND ({$weekOfMonthExpr}) = {$w} AND {$dateColumn} IS NOT NULL THEN {$partnerIdColumn} END) as week_{$w}_partners"
            );
        }

        $aggregated = (clone $base)
            ->select(array_merge(
                [
                    DB::raw($centerIdColumn.' as center_id'),
                    'centers.code as center_code',
                    DB::raw('COALESCE(SUM(CASE WHEN '.$monthColumn.' = '.$month.' THEN '.$volumeColumn.' ELSE 0 END), 0) as monthly_total'),
                    DB::raw('COALESCE(SUM(CASE WHEN '.$monthColumn.' BETWEEN 1 AND '.$month.' THEN '.$volumeColumn.' ELSE 0 END), 0) as ytd_total'),
                    DB::raw('COALESCE(SUM(CASE WHEN '.$monthColumn.' BETWEEN 1 AND '.($month - 1).' THEN '.$volumeColumn.' ELSE 0 END), 0) as prev_cum_total'),
                ],
                $weekCases,
                $partnerCases
            ))
            ->groupBy([
                DB::raw($centerIdColumn),
                'centers.code',
            ])
            ->get()
            ->keyBy('center_id');

        // ── Annual goals (separate, small lookup table) ────────────────────────
        $goalColumn = $isSales ? 'milk_sales_goal' : 'milk_production_goal';
        $annualGoals = DB::table($isSales ? 'center_sales_goals' : 'center_production_goals')
            ->where('year', $year)
            ->where('program', ProgramEnum::IH->value)
            ->pluck($goalColumn, 'center_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // ── Assemble result rows ───────────────────────────────────────────────
        $remainingMonths = max(1, 13 - $month);
        $rows = [];

        foreach ($aggregated as $centerId => $data) {
            $annualTarget = (float) ($annualGoals[$centerId] ?? 0);
            $prevCum = (float) $data->prev_cum_total;
            $monthlyTarget = $annualTarget > 0
                ? ($annualTarget - $prevCum) / $remainingMonths
                : 0.0;
            $monthlyTotal = (float) $data->monthly_total;
            $monthlyRating = $monthlyTarget > 0 ? ($monthlyTotal / $monthlyTarget) * 100 : null;
            $annualAccomp = (float) $data->ytd_total;
            $annualPercent = $annualTarget > 0 ? ($annualAccomp / $annualTarget) * 100 : null;

            $weekVolumes = [];
            foreach ($weeks as $w) {
                $weekVolumes[$w] = (float) ($data->{"week_{$w}_volume"} ?? 0);
            }

            $rows[] = [
                'center_id' => (int) $centerId,
                'center_code' => (string) $data->center_code,
                'outlet_category' => null,
                'previous_month_cumulative_accomplishment' => $prevCum,
                'cumulative_monthly_target' => $monthlyTarget,
                'monthly_total' => $monthlyTotal,
                'weeks' => $weekVolumes,
                'monthly_rating' => $monthlyRating,
                'annual_target' => $annualTarget,
                'annual_accomplishment' => $annualAccomp,
                'annual_percent' => $annualPercent,
            ];
        }

        // ── Sort by monthly rating descending ─────────────────────────────────
        usort($rows, fn ($a, $b) => ($b['monthly_rating'] ?? -INF) <=> ($a['monthly_rating'] ?? -INF)
        );

        // ── Compute dense rank by distinct rating ──────────────────────────────
        $distinctRatings = [];
        foreach ($rows as $row) {
            if ($row['monthly_rating'] === null) {
                continue;
            }
            $distinctRatings[(string) $row['monthly_rating']] = $row['monthly_rating'];
        }
        $distinctRatings = array_values($distinctRatings);
        rsort($distinctRatings, SORT_NUMERIC);
        $rankByRating = [];
        foreach ($distinctRatings as $i => $rating) {
            $rankByRating[(string) $rating] = $i + 1;
        }

        foreach ($rows as &$row) {
            $row['lead_rank'] = $row['monthly_rating'] === null
                ? null
                : ($rankByRating[(string) $row['monthly_rating']] ?? null);
            $row['monthly_rating_label'] = $this->ratingLabel($row['monthly_rating']);
            $row['annual_percent_label'] = $this->ratingLabel($row['annual_percent']);
        }
        unset($row);

        return [
            'report_type' => $reportType->value,
            'year' => $year,
            'month' => $month,
            'month_label' => Date::createFromDate($year, $month, 1)->format('F'),
            'weeks' => $weeks,
            'week_ranges' => $weekRanges,
            'rows' => $rows,
            'available_years' => $this->getAvailableYears($reportType),
        ];
    }

    /**
     * Use the indexed `year` column directly — avoids YEAR(transaction_date)
     * which wraps the column in a function and defeats the index.
     */
    private function getAvailableYears(MilkProductionDataTypeEnum $reportType): array
    {
        $table = $reportType === MilkProductionDataTypeEnum::SALES
            ? 'milk_sales'
            : 'milk_productions';

        $years = DB::table($table)
            ->select('year')
            ->distinct()
            ->whereNotNull('year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        $currentYear = (int) date('Y');
        if (! in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    /**
     * Monday-based weeks that touch the given month.
     *
     * @return array<int, array{start: string, end: string, label: string}>
     */
    private function mondayBasedWeeksInMonth(int $year, int $month): array
    {
        $first = Date::createFromDate($year, $month, 1);
        $last = $first->copy()->endOfMonth();

        // Carbon dayOfWeek: 0=Sunday … 6=Saturday. Roll back to the Monday of this week.
        $daysToMonday = ($first->dayOfWeek - 1 + 7) % 7;
        $firstMonday = $first->copy()->subDays($daysToMonday);

        $out = [];
        $weekNo = 1;
        $monday = $firstMonday->copy();

        while ($monday->lte($last)) {
            $start = $monday->copy()->max($first);
            $end = $monday->copy()->addDays(6)->min($last);

            if ($start->lte($last) && $end->gte($first)) {
                $out[$weekNo] = [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'label' => $start->format('M j').'-'.$end->format('j'),
                ];
                $weekNo++;
            }

            $monday->addDays(7);
        }

        return $out;
    }

    private function ratingLabel(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value >= 95) {
            return '95-100%';
        }
        if ($value >= 50) {
            return '50-94%';
        }

        return '0-49%';
    }
}

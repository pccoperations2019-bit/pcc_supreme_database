<?php

namespace App\Exports\BuffaloDerivedProducts\Support;

use Illuminate\Support\Collection;

final class BdpMatrixBuilder
{
    /**
     * @return array{years: array<int, int>, by_year: array<int, float>, by_year_type: array<int, array<string, float>>, by_year_month: array<int, array<int, float>>, by_year_month_type: array<int, array<int, array<string, float>>>, type_totals: array<string, float>, grand_total: float}
     */
    public function build(Collection $rows): array
    {
        $matrix = [
            'years' => [],
            'by_year' => [],
            'by_year_type' => [],
            'by_year_month' => [],
            'by_year_month_type' => [],
            'type_totals' => [],
            'grand_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $year = (int) $row->year;
            $month = $row->month !== null ? (int) $row->month : null;
            $type = empty($row->partner_id) ? 'center' : 'partner';
            $value = (float) $row->value;

            $matrix['years'][$year] = $year;
            $matrix['by_year'][$year] = ($matrix['by_year'][$year] ?? 0.0) + $value;
            $matrix['by_year_type'][$year][$type] = ($matrix['by_year_type'][$year][$type] ?? 0.0) + $value;
            $matrix['type_totals'][$type] = ($matrix['type_totals'][$type] ?? 0.0) + $value;

            if ($month !== null && $month >= 1 && $month <= 12) {
                $matrix['by_year_month'][$year][$month] = ($matrix['by_year_month'][$year][$month] ?? 0.0) + $value;
                $matrix['by_year_month_type'][$year][$month][$type] = ($matrix['by_year_month_type'][$year][$month][$type] ?? 0.0) + $value;
            }

            $matrix['grand_total'] += $value;
        }

        $matrix['years'] = array_values($matrix['years']);
        sort($matrix['years']);

        return $matrix;
    }
}

<?php

namespace App\Exports\BuffaloDerivedProducts\Strategies;

use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Contracts\BdpLayoutStrategy;

final class DateMonthlyLayoutStrategy implements BdpLayoutStrategy
{
    public function build(array $matrix, BuffaloDerivedProductsFilters $filters): array
    {
        return match ($filters->templateVariant ?? 'by-month') {
            'by-month' => $this->buildByMonth($matrix),
            default => $this->buildByMonth($matrix),
        };
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<int, string>>
     */
    private function buildByMonth(array $matrix): array
    {
        $monthLabels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        $rows = [array_merge(['YEAR'], $monthLabels, ['TOTAL'])];

        foreach ($matrix['years'] as $year) {
            $row = [(string) $year];
            $yearTotal = 0.0;

            foreach (range(1, 12) as $month) {
                $value = $matrix['by_year_month'][$year][$month] ?? 0.0;
                $yearTotal += $value;
                $row[] = $this->cell($value);
            }

            $row[] = $this->format($yearTotal);
            $rows[] = $row;
        }

        $grandTotalRow = ['Grand Total'];
        foreach (range(1, 12) as $month) {
            $sum = 0.0;
            foreach ($matrix['years'] as $year) {
                $sum += $matrix['by_year_month'][$year][$month] ?? 0.0;
            }
            $grandTotalRow[] = $this->format($sum);
        }
        $grandTotalRow[] = $this->format($matrix['grand_total']);
        $rows[] = $grandTotalRow;

        return $rows;
    }

    private function format(float $value): string
    {
        return number_format($value, 2);
    }

    private function cell(float $value): string
    {
        return $value > 0 ? number_format($value, 2) : '';
    }
}

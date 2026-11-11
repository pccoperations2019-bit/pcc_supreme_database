<?php

namespace App\Exports\BuffaloDerivedProducts\Strategies;

use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Contracts\BdpLayoutStrategy;

final class YearlyLayoutStrategy implements BdpLayoutStrategy
{
    public function build(array $matrix, BuffaloDerivedProductsFilters $filters): array
    {
        return $this->buildRows($matrix);
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<int, string>>
     */
    private function buildRows(array $matrix): array
    {
        $rows = [['YEAR', 'VALUE']];

        foreach ($matrix['years'] as $year) {
            $rows[] = [$year, $this->format($matrix['by_year'][$year] ?? 0.0)];
        }

        $rows[] = ['Grand Total', $this->format($matrix['grand_total'])];

        return $rows;
    }

    private function format(float $value): string
    {
        return number_format($value, 2);
    }
}

<?php

namespace App\Exports\BuffaloDerivedProducts\Contracts;

use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;

interface BdpLayoutStrategy
{
    /**
     * Build the export rows from the normalized matrix data.
     *
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<int, string>>
     */
    public function build(array $matrix, BuffaloDerivedProductsFilters $filters): array;
}

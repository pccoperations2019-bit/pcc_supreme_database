<?php

namespace App\Exports\Directory\Contracts;

use App\Exports\Directory\DirectoryFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Contract for entity-specific directory export strategies.
 */
interface DirectoryEntityStrategy
{
    /**
     * Get the entity type identifier.
     */
    public function entityType(): string;

    /**
     * Build the query for fetching entity data.
     */
    public function query(DirectoryFilters $filters): Builder;

    /**
     * Get list headers for the given depth.
     *
     * @param  string|null  $variant  Template variant (e.g. 'all-data', 'status')
     * @return array<int, string>
     */
    public function listHeaders(int $depth, ?string $variant = null): array;

    /**
     * Map entity data to list rows.
     *
     * @param  Collection<int, object>  $data
     * @return array<int, array<int, mixed>>
     */
    public function mapListRows(Collection $data, int $depth, ?string $variant = null): array;

    /**
     * Get figures headers for the given depth.
     *
     * @return array<int, string>
     */
    public function figuresHeaders(int $depth, ?string $variant = null): array;

    /**
     * Get dimension values for figures aggregation.
     *
     * @return array<int, mixed>
     */
    public function figureDimensions(object $entity, int $depth, ?string $variant = null): array;

    /**
     * Get the count label for figures.
     */
    public function figuresCountLabel(): string;
}

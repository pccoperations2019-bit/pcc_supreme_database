<?php

namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class NameSort implements Sort
{
    public function __construct(private readonly ?string $columnExpression = null) {}

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $direction = $descending ? 'desc' : 'asc';

        $column = $this->columnExpression
            ?? $query->getQuery()->grammar->wrap($property);

        $query->orderByRaw("LOWER({$column}) {$direction}");
    }
}

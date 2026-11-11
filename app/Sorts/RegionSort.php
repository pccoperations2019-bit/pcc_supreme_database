<?php

namespace App\Sorts;

use App\Enums\AddressRegionEnum;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\Grammar;
use Spatie\QueryBuilder\Sorts\Sort;

class RegionSort implements Sort
{
    public function __construct(private readonly ?string $columnExpression = null) {}

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $order = $descending ? 'desc' : 'asc';

        $rankings = $this->rankings();

        if ($rankings === []) {
            $query->orderBy($property, $order);

            return;
        }

        $model = $query->getModel();
        $grammar = $query->getQuery()->grammar;

        [$expression, $bindings] = $this->rankExpression($model, $grammar, $property, $rankings);

        $query->orderByRaw("{$expression} {$order}", $bindings);

        // Regions tie constantly; without a unique tiebreaker the paginator
        // can repeat or skip rows between pages.
        $query->orderBy($model->qualifyColumn($model->getKeyName()), $order);
    }

    /**
     * Normalized region spelling => sort rank, de-duplicated case-insensitively.
     *
     * @return array<string, int>
     */
    private function rankings(): array
    {
        $matches = AddressRegionEnum::matches();
        $rankings = [];

        foreach (AddressRegionEnum::sortOrder() as $regionCode => $rank) {
            $aliases = array_merge($matches[$regionCode] ?? [], [$regionCode]);

            foreach ($aliases as $alias) {
                if (! is_string($alias) || trim($alias) === '') {
                    continue;
                }

                // A spelling can appear in several aliases lists; first rank wins.
                $rankings[strtoupper(trim($alias))] ??= $rank;
            }
        }

        return $rankings;
    }

    /**
     * Ranking expression used for the sort.
     *
     * @param  array<string, int>  $rankings
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function rankExpression(Model $model, Grammar $grammar, string $property, array $rankings): array
    {
        // Default `region` sort: the value lives on the polymorphic `addresses`
        // table, so the ranking runs *inside* that lookup. Ranking it outside
        // made MySQL re-run the correlated subquery once per region alias
        // (~250 times per row), which dominated every index query.
        if ($this->columnExpression === null && $property === 'region') {
            [$case, $bindings] = $this->rankCase('region', $rankings);

            // A row without an address makes the lookup return NULL, and MySQL
            // sorts NULL first; COALESCE keeps those rows last, as before.
            return ['COALESCE('.$this->addressLookup($model, $grammar, $case).', '.PHP_INT_MAX.')', $bindings];
        }

        $column = $this->columnExpression === null
            ? $grammar->wrap($property)
            : "({$this->columnExpression})";

        return $this->rankCase($column, $rankings);
    }

    /**
     * CASE expression mapping a region value to its sort rank, blank/unknown last.
     *
     * @param  array<string, int>  $rankings
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function rankCase(string $column, array $rankings): array
    {
        $cases = [];
        $bindings = [];

        foreach ($rankings as $alias => $rank) {
            $cases[] = "WHEN TRIM(UPPER({$column})) = ? THEN ?";
            $bindings[] = $alias;
            $bindings[] = $rank;
        }

        return [
            "CASE WHEN {$column} IS NULL OR TRIM({$column}) = '' THEN ".PHP_INT_MAX.' '
                .implode(' ', $cases)
                .' ELSE '.PHP_INT_MAX.' END',
            $bindings,
        ];
    }

    /**
     * Correlated lookup of the single address region owned by the current row.
     */
    private function addressLookup(Model $model, Grammar $grammar, string $select): string
    {
        $table = $grammar->wrapTable($model->getTable());
        $key = $grammar->wrap($model->getKeyName());

        $type = $model instanceof Profile
            ? "{$table}.".$grammar->wrap('profile_type')
            : "'".$model->getMorphClass()."'";

        return "(SELECT {$select} FROM addresses WHERE addressable_id = {$table}.{$key} AND addressable_type = {$type} LIMIT 1)";
    }
}

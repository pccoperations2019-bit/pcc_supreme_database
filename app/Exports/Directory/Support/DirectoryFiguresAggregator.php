<?php

namespace App\Exports\Directory\Support;

use Illuminate\Support\Collection;

/**
 * Groups already-fetched directory rows without delimiter-based key collisions.
 */
final class DirectoryFiguresAggregator
{
    /**
     * @param  callable(mixed, int): array<int, mixed>  $dimensions
     * @return array<int, array<int, mixed>>
     */
    public function aggregate(
        Collection $data,
        int $depth,
        callable $dimensions,
        string $totalLabel = 'Grand Total',
    ): array {
        $groups = [];

        foreach ($data as $item) {
            $values = array_values($dimensions($item, $depth));
            $key = serialize($values);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'values' => $values,
                    'count' => 0,
                ];
            }

            $groups[$key]['count']++;
        }

        $figures = array_map(
            static fn (array $group): array => [...$group['values'], $group['count']],
            array_values($groups),
        );

        // BUG FIX: Ensure Grand Total row has exactly the same width as data rows
        // Data rows have depth dimension values + 1 count value = depth + 1 cells
        // Grand Total: (depth - 1 blanks) + total label + count = depth + 1 cells
        $totalValues = array_fill(0, max(0, $depth - 1), '');
        $figures[] = [...$totalValues, $totalLabel, $data->count()];

        return $figures;
    }
}

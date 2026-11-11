<?php

namespace App\Services;

use App\Support\Centers;
use BackedEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PivotBuilderService
{
    public function __construct(
        private PivotFieldRegistryService $registry
    ) {}

    public function build(array $request): array
    {
        $sourceKey = $request['source'];
        $cfg = config("pivot_sources.{$sourceKey}");

        abort_if(! $cfg, 404, "Unknown source: {$sourceKey}");

        $rows = $this->parseFieldConfigs($request['rows'] ?? []);
        $columns = $this->parseFieldConfigs($request['columns'] ?? []);
        $valueFields = collect($request['values'] ?? [])->pluck('field')->all();
        $filterFields = collect($request['filters'] ?? [])->pluck('field')->all();

        $this->registry->assertFieldsValid(
            $sourceKey,
            array_unique(array_merge(
                array_column($rows, 'field'),
                array_column($columns, 'field'),
                $valueFields,
                $filterFields
            ))
        );

        $query = $this->baseQuery($cfg);
        $this->applyFilters($query, $cfg, $request['filters'] ?? []);

        return $this->buildFigures($query, $cfg, $rows, $columns, $request['values'] ?? []);
    }

    public function baseQuery(array $cfg): Builder
    {
        $query = DB::table($cfg['table']);

        if (! empty($cfg['joins'])) {
            foreach ($cfg['joins'] as $join) {
                $joinType = $join['type'] === 'left' ? 'leftJoin' : 'join';

                $table = isset($join['raw'])
                    ? DB::raw("({$join['raw']}) as {$join['alias']}")
                    : (isset($join['alias']) ? "{$join['table']} as {$join['alias']}" : $join['table']);

                $query->{$joinType}($table, function ($joinQuery) use ($join) {
                    $joinQuery->on($join['first'], '=', $join['second']);

                    foreach ($join['extra'] ?? [] as $column => $value) {
                        $joinQuery->where($column, '=', $value);
                    }
                });

                if (isset($join['where'])) {
                    $whereClause = str_replace('\\\\', '\\', $join['where']);
                    $query->whereRaw($whereClause);
                }
            }
        }

        $this->applyHiddenCenterFilter($query, $cfg);

        return $query;
    }

    public function qualify(array $cfg, string $field): string
    {
        $meta = $cfg['fields'][$field] ?? [];
        $table = $meta['via'] ?? $cfg['table'];
        $column = $meta['column'] ?? $field;

        return "{$table}.{$column}";
    }

    public function formatValue(array $cfg, string $field, mixed $value): mixed
    {
        $type = $cfg['fields'][$field]['type'] ?? 'string';

        if ($type === 'boolean') {
            return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true) || $value === true || $value === 1 ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return $value;
        }

        $enum = $cfg['fields'][$field]['enum'] ?? null;
        if (! $enum || ! enum_exists($enum)) {
            return $value;
        }

        $candidate = trim((string) $value);
        $case = is_subclass_of($enum, BackedEnum::class) ? $enum::tryFrom($candidate) : null;

        if (! $case && method_exists($enum, 'tryFroMatches')) {
            $case = $enum::tryFroMatches($candidate);
        }

        if (! $case && method_exists($enum, 'fromLabel')) {
            $case = $enum::fromLabel($candidate);
        }

        return $case ? $case->label() : $value;
    }

    private function displayValue(array $cfg, string $field, mixed $raw): string
    {
        $type = $cfg['fields'][$field]['type'] ?? 'string';
        if ($type === 'boolean') {
            return (string) $this->formatValue($cfg, $field, $raw);
        }

        if ($raw === null || trim((string) $raw) === '' || strtolower((string) $raw) === 'null') {
            return 'NA';
        }

        return (string) $this->formatValue($cfg, $field, $raw);
    }

    private function parseFieldConfigs(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? [
                'field' => $item['field'] ?? null,
                'order' => strtolower((string) ($item['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ] : ['field' => $item, 'order' => 'asc'])
            ->filter(fn ($i) => ! empty($i['field']))
            ->values()
            ->all();
    }

    private function applyHiddenCenterFilter(Builder $query, array $cfg): void
    {
        $hiddenCodes = Centers::hiddenCenters();
        if ($hiddenCodes === []) {
            return;
        }

        $hiddenList = collect($hiddenCodes)
            ->flatMap(fn ($c) => [strtolower($c), strtoupper($c)])
            ->unique()
            ->values()
            ->all();

        if ($cfg['table'] === 'centers') {
            $query->whereNotIn('centers.code', $hiddenList);
        } elseif (collect($cfg['joins'] ?? [])->contains('table', 'centers')) {
            $query->where(function ($q) use ($hiddenList) {
                $q->whereNotIn('centers.code', $hiddenList)->orWhereNull('centers.code');
            });
        }
    }

    private function applyFilters(Builder $query, array $cfg, array $filters): void
    {
        foreach ($filters as $f) {
            $qualified = $this->qualify($cfg, $f['field']);

            $query->where(function ($q) use ($qualified, $f) {
                if (! empty($f['values'])) {
                    $q->whereIn($qualified, $f['values']);
                }
                if ($f['includeBlanks'] ?? false) {
                    $q->orWhereNull($qualified)->orWhere($qualified, '');
                }
            });
        }
    }

    private const MAX_PIVOT_GROUPS = 1000;

    private const MAX_PIVOT_CELLS = 10000;

    private function buildFigures(Builder $query, array $cfg, array $rows, array $columns, array $values): array
    {
        $rowFields = array_column($rows, 'field');
        $colFields = array_column($columns, 'field');

        $valueKeys = collect($values)
            ->map(fn ($value) => "{$value['field']}_{$value['agg']}")
            ->all();

        $rowExpr = array_map(fn ($field) => $this->qualify($cfg, $field), $rowFields);
        $colExpr = array_map(fn ($field) => $this->qualify($cfg, $field), $colFields);

        $valueSelects = collect($values)
            ->map(function ($value) use ($cfg) {
                $column = $this->qualify($cfg, $value['field']);
                $alias = "{$value['field']}_{$value['agg']}";

                return match ($value['agg']) {
                    'count' => DB::raw("COUNT(*) as {$alias}"),
                    'sum' => DB::raw("SUM({$column}) as {$alias}"),
                    'avg' => DB::raw("AVG({$column}) as {$alias}"),
                    default => abort(422, 'Invalid aggregation'),
                };
            })
            ->all();

        $groupBy = array_map(fn ($expr) => DB::raw($expr), array_merge($rowExpr, $colExpr));

        if ($rowExpr === [] && $colExpr === [] && empty($valueSelects)) {
            return ['type' => 'figures', 'rows' => [], 'columns' => [], 'data' => []];
        }

        $selectExpressions = [];
        foreach ($rowFields as $index => $field) {
            $selectExpressions[] = DB::raw("{$rowExpr[$index]} as `{$field}`");
        }
        foreach ($colFields as $index => $field) {
            $selectExpressions[] = DB::raw("{$colExpr[$index]} as `{$field}`");
        }

        foreach ($rows as $index => $r) {
            $query->orderBy(DB::raw($rowExpr[$index]), $r['order']);
        }
        foreach ($columns as $index => $c) {
            $query->orderBy(DB::raw($colExpr[$index]), $c['order']);
        }

        // Guard: high-cardinality pivot (e.g. farmers.code with 8000+ records) would destroy the browser.
        // Count groups first cheaply if rows/columns contain high-cardinality fields.
        $raw = $query
            ->select(array_merge($selectExpressions, $valueSelects))
            ->groupBy($groupBy)
            ->get();

        $totalGroups = $raw->count();
        $wasTruncated = false;
        if ($totalGroups > self::MAX_PIVOT_GROUPS) {
            $raw = $raw->take(self::MAX_PIVOT_GROUPS);
            $wasTruncated = true;
        }

        // Also guard total cells (rows * columns) to prevent 8000 x N matrix
        $estimatedCells = $raw->count() * max(1, count($colFields) ? $raw->map(fn ($r) => collect($colFields)->map(fn ($f) => $r->{$f})->implode('|'))->unique()->count() : 1);
        if ($estimatedCells > self::MAX_PIVOT_CELLS && ! $wasTruncated) {
            $raw = $raw->take((int) (self::MAX_PIVOT_CELLS / max(1, count($colFields) ? 10 : 1)));
            $wasTruncated = true;
        }

        $columnKeys = $colFields === []
            ? ['']
            : $raw
                ->map(fn ($record) => collect($colFields)
                    ->map(fn ($field) => $this->displayValue($cfg, $field, $record->{$field} ?? null))
                    ->implode('|'))
                ->unique()
                ->values()
                ->all();

        $data = [];
        $flatRows = [];

        foreach ($raw as $record) {
            $rowKey = collect($rowFields)
                ->map(fn ($field) => $this->displayValue($cfg, $field, $record->{$field} ?? null))
                ->implode('|');

            $colKey = $colFields === []
                ? ''
                : collect($colFields)
                    ->map(fn ($field) => $this->displayValue($cfg, $field, $record->{$field} ?? null))
                    ->implode('|');

            foreach ($valueKeys as $valueKey) {
                $data[$rowKey][$colKey][$valueKey] = $record->{$valueKey};
            }

            $flatRow = [];
            foreach ($rowFields as $field) {
                $flatRow[$field] = $this->displayValue($cfg, $field, $record->{$field} ?? null);
            }

            $flatRows[] = $flatRow;
        }

        return [
            'type' => 'figures',
            'rows' => $flatRows,
            'columns' => $columnKeys,
            'value_fields' => $valueKeys,
            'data' => $data,
            'meta' => [
                'total_groups' => $totalGroups,
                'returned_groups' => $raw->count(),
                'truncated' => $wasTruncated,
                'max_groups' => self::MAX_PIVOT_GROUPS,
            ],
        ];
    }
}

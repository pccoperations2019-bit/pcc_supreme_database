<?php

namespace App\Exports\BuffaloDerivedProducts\Strategies;

use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Contracts\BdpLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Support\BdpLocationResolver;
use App\Exports\BuffaloDerivedProducts\Support\BdpQueryBuilder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class AreaLayoutStrategy implements BdpLayoutStrategy
{
    public function __construct(
        private BdpQueryBuilder $queryBuilder,
        private BdpLocationResolver $locationResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<int, string>>
     */
    public function build(array $matrix, BuffaloDerivedProductsFilters $filters): array
    {
        return $this->buildArea($filters);
    }

    /**
     * By Area layouts: rows follow the Center -> Region -> Province -> Municipality -> District
     * hierarchy up to the template's area_depth, with Total / By Type / By Year breakdowns.
     *
     * @return array<int, array<int, string>>
     */
    private function buildArea(BuffaloDerivedProductsFilters $filters): array
    {
        // This method receives the pre-built matrix; for area layouts we need
        // to re-fetch data since the matrix format differs from the area format.
        $source = config("reports.bdp.sources.{$filters->product}.{$filters->type}");
        if (! $source || ! isset($source['table'], $source['metric'])) {
            return [['VALUE', 'No data available']];
        }

        $template = config("template.templates.{$filters->template}");
        $depth = max(1, min(5, (int) ($template['axis']['area_depth'] ?? 1)));
        $breakdown = $template['axis']['area_breakdown'] ?? 'none';

        $records = $this->queryBuilder->fetchAreaRows($source['table'], $source['metric']);
        if ($records->isEmpty()) {
            return [['VALUE', 'No data available']];
        }

        $centerIds = $records->pluck('center_id')->filter()->unique()->values()->all();
        $codes = DB::table('centers')->whereIn('id', $centerIds)->pluck('code', 'id')->all();
        $addresses = $this->locationResolver->resolve($centerIds);

        $groups = [];
        $years = [];
        $typeTotals = [];
        $yearTotals = [];
        $programYearTotals = [];
        $typeProgramYearTotals = [];
        $programs = [];
        $grandTotal = 0.0;

        foreach ($records as $record) {
            $labels = $this->areaLabels($record->center_id, $depth, $codes, $addresses);
            $key = implode("\x1f", $labels);
            $value = (float) $record->value;
            $year = (int) $record->year;
            $program = $this->normalizeProgramKey($record->program);
            $type = $this->resolveType($record);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'labels' => $labels,
                    'total' => 0.0,
                    'types' => [],
                    'years' => [],
                    'program_years' => [],
                    'type_program_years' => [],
                ];
            }

            $groups[$key]['total'] += $value;
            $groups[$key]['types'][$type] = ($groups[$key]['types'][$type] ?? 0.0) + $value;
            $groups[$key]['years'][$year] = ($groups[$key]['years'][$year] ?? 0.0) + $value;
            $groups[$key]['program_years'][$program][$year] = ($groups[$key]['program_years'][$program][$year] ?? 0.0) + $value;
            $groups[$key]['type_program_years'][$type][$program][$year] = ($groups[$key]['type_program_years'][$type][$program][$year] ?? 0.0) + $value;

            $years[$year] = $year;
            $typeTotals[$type] = ($typeTotals[$type] ?? 0.0) + $value;
            $yearTotals[$year] = ($yearTotals[$year] ?? 0.0) + $value;
            $programYearTotals[$program][$year] = ($programYearTotals[$program][$year] ?? 0.0) + $value;
            $typeProgramYearTotals[$type][$program][$year] = ($typeProgramYearTotals[$type][$program][$year] ?? 0.0) + $value;
            $programs[$program] = $program;
            $grandTotal += $value;
        }

        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b) => $a['labels'] <=> $b['labels']);
        $years = array_values($years);
        sort($years);
        $programs = $this->orderedPrograms(array_values($programs));
        $types = $this->orderedTypes(array_keys($typeTotals));

        $labelColumns = $this->areaLabelColumns($depth);
        $headers = $this->buildAreaHeaders($labelColumns, $programs, $years, $breakdown, $typeProgramYearTotals, $types);

        $dataRows = [];
        foreach ($groups as $group) {
            $row = $group['labels'];

            if ($breakdown === 'type') {
                foreach ($types as $type) {
                    $row[] = $this->cell($group['types'][$type] ?? 0.0);
                }
                $row[] = $this->format($group['total']);
            } elseif ($breakdown === 'year') {
                foreach ($years as $year) {
                    $row[] = $this->cell($group['years'][$year] ?? 0.0);
                }
                $row[] = $this->format($group['total']);
            } elseif ($breakdown === 'program_year') {
                foreach ($programs as $program) {
                    foreach ($years as $year) {
                        $row[] = $this->cell($group['program_years'][$program][$year] ?? 0.0);
                    }
                }
                $row[] = $this->format($group['total']);
            } elseif ($breakdown === 'type_program_year') {
                foreach ($this->typeProgramColumns($typeProgramYearTotals) as $column) {
                    foreach ($years as $year) {
                        $row[] = $this->cell($group['type_program_years'][$column['type']][$column['program']][$year] ?? 0.0);
                    }
                }
                $row[] = $this->format($group['total']);
            } else {
                $row[] = $this->format($group['total']);
            }

            $dataRows[] = $row;
        }

        $rows = [...$headers, ...$this->mergeRepeatedLabels($dataRows, count($labelColumns))];

        $totalRow = ['Grand Total', ...array_fill(0, count($labelColumns) - 1, '')];
        if ($breakdown === 'type') {
            foreach ($types as $type) {
                $totalRow[] = $this->format($typeTotals[$type] ?? 0.0);
            }
        } elseif ($breakdown === 'year') {
            foreach ($years as $year) {
                $totalRow[] = $this->format($yearTotals[$year] ?? 0.0);
            }
        } elseif ($breakdown === 'program_year') {
            foreach ($programs as $program) {
                foreach ($years as $year) {
                    $totalRow[] = $this->format($programYearTotals[$program][$year] ?? 0.0);
                }
            }
        } elseif ($breakdown === 'type_program_year') {
            foreach ($this->typeProgramColumns($typeProgramYearTotals) as $column) {
                foreach ($years as $year) {
                    $totalRow[] = $this->format($typeProgramYearTotals[$column['type']][$column['program']][$year] ?? 0.0);
                }
            }
        }
        $totalRow[] = $this->format($grandTotal);
        $rows[] = $totalRow;

        return $rows;
    }

    private function areaLabelColumns(int $depth): array
    {
        return array_slice(['Center', 'Region', 'Province', 'Municipality', 'District'], 0, $depth);
    }

    /**
     * @return array<int, string>
     */
    private function areaLabels($centerId, int $depth, array $codes, array $addresses): array
    {
        $labels = [strtoupper((string) ($codes[$centerId] ?? '')) ?: 'Unknown'];
        $address = $addresses[$centerId] ?? null;

        foreach (array_slice(['region', 'province', 'city', 'district'], 0, $depth - 1) as $field) {
            $labels[] = (string) ($address->{$field} ?? '');
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $labelColumns
     * @param  array<int, string>  $programs
     * @param  array<int, int>  $years
     * @param  array<string, array<string, array<int, float>>>  $typeProgramYearTotals
     * @param  array<int, string>  $types
     * @return array<int, array<int, string>>
     */
    private function buildAreaHeaders(
        array $labelColumns,
        array $programs,
        array $years,
        string $breakdown,
        array $typeProgramYearTotals,
        array $types,
    ): array {
        $headerMerges = [];
        $headerRows = 1;

        if ($breakdown === 'program_year') {
            $headerRows = 2;

            $topRow = $labelColumns;
            $secondRow = array_fill(0, count($labelColumns), '');
            $startColumn = count($labelColumns) + 1;
            $yearCount = count($years);

            foreach ($programs as $program) {
                $topRow[] = $this->programLabel($program);
                for ($i = 1; $i < $yearCount; $i++) {
                    $topRow[] = '';
                }

                foreach ($years as $year) {
                    $secondRow[] = (string) $year;
                }

                $headerMerges[] = sprintf(
                    '%s1:%s1',
                    Coordinate::stringFromColumnIndex($startColumn),
                    Coordinate::stringFromColumnIndex($startColumn + $yearCount - 1)
                );
                $startColumn += $yearCount;
            }

            $topRow[] = '';
            $secondRow[] = 'Grand Total';

            return [$topRow, $secondRow];
        }

        if ($breakdown === 'type') {
            return [array_merge($labelColumns, array_map(
                fn (string $type) => $this->typeLabel($type),
                $types
            ), ['Grand Total'])];
        }

        if ($breakdown === 'year') {
            return [array_merge($labelColumns, array_map(
                fn (int $year) => (string) $year,
                $years
            ), ['Grand Total'])];
        }

        if ($breakdown === 'type_program_year') {
            $headerRows = 3;

            $topRow = $labelColumns;
            $middleRow = array_fill(0, count($labelColumns), '');
            $bottomRow = array_fill(0, count($labelColumns), '');

            $yearCount = count($years);
            $startColumn = count($labelColumns) + 1;
            $columns = $this->typeProgramColumns($typeProgramYearTotals);

            foreach ($columns as $column) {
                $type = $column['type'];
                $program = $column['program'];
                $topRow[] = $this->typeLabel($type);
                for ($i = 1; $i < $yearCount; $i++) {
                    $topRow[] = '';
                }
                $headerMerges[] = sprintf(
                    '%s1:%s1',
                    Coordinate::stringFromColumnIndex($startColumn),
                    Coordinate::stringFromColumnIndex($startColumn + $yearCount - 1)
                );

                $middleRow[] = $this->programLabel($program);
                for ($i = 1; $i < $yearCount; $i++) {
                    $middleRow[] = '';
                }
                $headerMerges[] = sprintf(
                    '%s2:%s2',
                    Coordinate::stringFromColumnIndex($startColumn),
                    Coordinate::stringFromColumnIndex($startColumn + $yearCount - 1)
                );
                foreach ($years as $year) {
                    $bottomRow[] = (string) $year;
                }
                $startColumn += $yearCount;
            }

            $topRow[] = '';
            $middleRow[] = '';
            $bottomRow[] = 'Grand Total';

            return [$topRow, $middleRow, $bottomRow];
        }

        return [array_merge($labelColumns, ['SUM of Total'])];
    }

    /**
     * @param  array<int, string>  $dataRows
     * @return array<int, array<int, string>>
     */
    private function mergeRepeatedLabels(array $dataRows, int $labelCount): array
    {
        $previous = null;

        foreach ($dataRows as &$row) {
            $labels = array_slice($row, 0, $labelCount);

            foreach ($labels as $index => $label) {
                if ($previous === null || $label !== ($previous[$index] ?? null)) {
                    break;
                }
                $row[$index] = '';
            }

            $previous = $labels;
        }
        unset($row);

        return $dataRows;
    }

    private function resolveType(object $row): string
    {
        return empty($row->partner_id) ? 'center' : 'partner';
    }

    private function normalizeProgramKey(mixed $program): string
    {
        $program = strtolower(trim((string) $program));

        return $program !== '' ? $program : 'ih';
    }

    private function programLabel(string $program): string
    {
        return match ($program) {
            'ih' => 'Institutional Herd',
            'cbed' => 'CBED',
            'cbin' => 'CBIN',
            default => strtoupper($program),
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'center' => 'Center',
            'partner' => 'Partner',
            default => ucfirst($type),
        };
    }

    /**
     * @param  array<string, array<string, array<int, float>>>  $typeProgramYearTotals
     * @return array<int, array{type: string, program: string}>
     */
    private function typeProgramColumns(array $typeProgramYearTotals): array
    {
        $columns = [];

        foreach ($this->orderedTypes(array_keys($typeProgramYearTotals)) as $type) {
            foreach ($this->orderedPrograms(array_keys($typeProgramYearTotals[$type])) as $program) {
                $columns[] = ['type' => $type, 'program' => $program];
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $programs
     * @return array<int, string>
     */
    private function orderedPrograms(array $programs): array
    {
        $preferred = ['ih', 'cbed', 'cbin'];
        $ordered = [];

        foreach ($preferred as $program) {
            if (in_array($program, $programs, true)) {
                $ordered[] = $program;
            }
        }

        foreach ($programs as $program) {
            if (! in_array($program, $ordered, true)) {
                $ordered[] = $program;
            }
        }

        return $ordered;
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    private function orderedTypes(array $types): array
    {
        $preferred = ['center', 'partner'];
        $ordered = [];

        foreach ($preferred as $type) {
            if (in_array($type, $types, true)) {
                $ordered[] = $type;
            }
        }

        foreach ($types as $type) {
            if (! in_array($type, $ordered, true)) {
                $ordered[] = $type;
            }
        }

        return $ordered;
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

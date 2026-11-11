<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PivotExportService
{
    public function exportGeneric(
        array $result,
        array $request
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();

        $this->exportFigures($sheet, $result, $request);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'pivot-export.xlsx'
        );
    }

    private function exportFigures($sheet, array $result, array $request): void
    {
        $columns = $result['columns'] ?? [];
        $data = $result['data'] ?? [];
        $rows = $result['rows'] ?? [];
        $rowFields = $request['rows'] ?? [];
        $showTotals = (bool) ($request['show_totals'] ?? false);

        $fieldLabels = config(
            'pivot_sources.'.($request['source'] ?? '').'.fields',
            []
        );

        if (empty($rows)) {
            $sheet->setCellValue('A1', 'No data available');

            return;
        }

        $valueFields = $request['values'] ?? [];
        $valueKeys = $result['value_fields'] ?? array_map(
            fn ($value) => "{$value['field']}_{$value['agg']}",
            $valueFields
        );
        $valueLabels = array_map(
            fn ($value) => $this->aggregationLabel($value['agg'] ?? 'count')
                .' of '.($fieldLabels[$value['field']]['label'] ?? $value['field']),
            $valueFields
        );

        $valueCount = max(count($valueLabels), 1);
        $hasColumnGroups = $columns !== [''];
        $columnKeys = $hasColumnGroups ? $columns : [''];

        // A second header row is only worth the vertical space when several value
        // fields have to be distinguished; with a single one the group name alone
        // is enough, so the sheet keeps its compact single-row header.
        $useSubHeader = $hasColumnGroups && count($valueLabels) > 1;
        $headerRows = $useSubHeader ? 2 : 1;

        $colIndex = 1;

        foreach ($rowFields as $field) {
            $cell = Coordinate::stringFromColumnIndex($colIndex);
            $label = $fieldLabels[$field]['label'] ?? $field;
            $sheet->setCellValue("{$cell}1", $label);
            if ($useSubHeader) {
                $sheet->mergeCells("{$cell}1:{$cell}2");
            }
            $colIndex++;
        }

        if ($useSubHeader && $hasColumnGroups) {
            foreach ($columns as $column) {
                $start = Coordinate::stringFromColumnIndex($colIndex);
                $end = Coordinate::stringFromColumnIndex($colIndex + $valueCount - 1);
                $sheet->setCellValue("{$start}1", $column);
                $sheet->mergeCells("{$start}1:{$end}1");

                foreach ($valueLabels as $offset => $label) {
                    $cell = Coordinate::stringFromColumnIndex($colIndex + $offset);
                    $sheet->setCellValue("{$cell}2", $label);
                }
                $colIndex += $valueCount;
            }
        } elseif ($hasColumnGroups) {
            foreach ($columns as $column) {
                $cell = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$cell}1", $column);
                $colIndex++;
            }
        } else {
            // Without a column grouping the '' placeholder bucket is not a real
            // column, so the value labels take its place rather than sitting
            // behind an empty header cell. They span both header rows, as in the
            // grid, so no blank cell is left above them.
            foreach ($valueLabels as $offset => $label) {
                $cell = Coordinate::stringFromColumnIndex($colIndex + $offset);
                $sheet->setCellValue("{$cell}1", $label);
                if ($useSubHeader) {
                    $sheet->mergeCells("{$cell}1:{$cell}2");
                }
            }
        }

        $rowNum = $headerRows + 1;
        $rowKeys = [];

        foreach ($rows as $row) {
            $colIndex = 1;

            foreach ($rowFields as $field) {
                $cell = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$cell}{$rowNum}", $row[$field] ?? '');
                $colIndex++;
            }

            $rowKey = collect($rowFields)
                ->map(fn ($field) => $row[$field] ?? 'NA')
                ->implode('|');
            $rowKeys[$rowKey] = true;

            foreach ($columnKeys as $column) {
                $values = $data[$rowKey][$column] ?? [];
                foreach ($valueKeys as $offset => $valueKey) {
                    $cell = Coordinate::stringFromColumnIndex($colIndex + $offset);
                    $sheet->setCellValue("{$cell}{$rowNum}", $values[$valueKey] ?? '');
                }
                $colIndex += $valueCount;
            }

            $rowNum++;
        }

        if ($showTotals) {
            $labelCell = Coordinate::stringFromColumnIndex(1);
            $sheet->setCellValue("{$labelCell}{$rowNum}", 'Total');
            $colIndex = 1 + count($rowFields);
            $allRowKeys = array_keys($rowKeys);

            // One total per (column group x value field), sitting directly under
            // the columns it summarises. No extra total column is appended, so
            // the footer stays aligned with the body.
            foreach ($columnKeys as $column) {
                foreach ($valueKeys as $offset => $valueKey) {
                    $cell = Coordinate::stringFromColumnIndex($colIndex + $offset);
                    $agg = $valueFields[$offset]['agg'] ?? 'count';
                    $sheet->setCellValue(
                        "{$cell}{$rowNum}",
                        $this->total(
                            $this->collectNumbers($allRowKeys, [$column], $data, $valueKey),
                            $agg
                        )
                    );
                }
                $colIndex += $valueCount;
            }
        }
    }

    /**
     * Pull the numeric cells for one value field across the given row/column
     * intersections. Missing and non-numeric cells are skipped rather than
     * counted as zero, so a sparse pivot does not understate its total.
     *
     * @param  array<int, string>  $rowKeys
     * @param  array<int, string>  $columnKeys
     */
    private function collectNumbers(array $rowKeys, array $columnKeys, array $data, string $valueKey): array
    {
        $numbers = [];

        foreach ($rowKeys as $rowKey) {
            foreach ($columnKeys as $column) {
                $value = $data[$rowKey][$column][$valueKey] ?? null;
                if (is_numeric($value)) {
                    $numbers[] = (float) $value;
                }
            }
        }

        return $numbers;
    }

    /**
     * Mirror of the grid's totals: avg averages the contributing cells instead
     * of adding them, since adding averages is meaningless.
     */
    private function total(array $numbers, string $agg): string|int|float
    {
        if ($numbers === []) {
            return '';
        }

        $total = array_sum($numbers);

        if ($agg === 'avg') {
            return $total / count($numbers);
        }

        return fmod($total, 1.0) === 0.0 ? (int) $total : $total;
    }

    private function aggregationLabel(string $agg): string
    {
        return match ($agg) {
            'sum' => 'Sum',
            'avg' => 'Avg',
            default => 'Count',
        };
    }
}

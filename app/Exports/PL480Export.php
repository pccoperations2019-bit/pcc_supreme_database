<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PL480Export implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $provinces,
        protected array $years,
        protected array $inputData,
        protected array $computedData,
        protected array $selectedWithoutYears,
        protected array $selectedWithYears,
    ) {}

    public function sheets(): array
    {
        return [
            new PL480DashboardSheet(
                $this->provinces,
                $this->years,
                $this->inputData,
                $this->computedData,
                $this->selectedWithoutYears,
                $this->selectedWithYears,
            ),
        ];
    }
}

/**
 * Single-sheet export mirroring the dashboard/PDF layout:
 * input-data table first, computed-data table below it.
 */
class PL480DashboardSheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    protected array $withoutYears = [];

    protected array $withYears = [];

    protected int $columnCount = 1;

    protected int $secondHeaderRow = 1;

    protected int $secondTotalRow = 1;

    public function __construct(
        protected array $provinces,
        protected array $years,
        protected array $inputData,
        protected array $computedData,
        protected array $selectedWithoutYears,
        protected array $selectedWithYears,
    ) {}

    public function title(): string
    {
        return 'PL480 Milk Production';
    }

    public function array(): array
    {
        $this->withoutYears = array_values($this->selectedWithoutYears);
        $this->withYears = array_values($this->selectedWithYears);
        $this->columnCount = 1 + count($this->withoutYears) + count($this->withYears);

        $grid = [];

        // ── First table: input data ──
        $grid[] = $this->headerRow('WITH PL480 PROJECT (INPUT DATA)');
        $grid[] = $this->yearsRow();
        foreach ($this->provinces as $province) {
            $row = [$province];
            foreach ($this->withoutYears as $year) {
                $row[] = (float) ($this->computedData[$province][$year] ?? 0);
            }
            foreach ($this->withYears as $year) {
                // Blank when zero, mirroring the dashboard's empty input fields.
                $value = (float) ($this->inputData[$province][$year] ?? 0);
                $row[] = $value == 0 ? null : $value;
            }
            $grid[] = $row;
        }
        $grid[] = $this->totalRow(fn (string $province, int $year) => (float) ($this->inputData[$province][$year] ?? 0));

        // Blank separator row between the two tables.
        $grid[] = array_fill(0, $this->columnCount, null);

        // ── Second table: computed data ──
        $this->secondHeaderRow = count($grid) + 1;
        $grid[] = $this->headerRow('WITH PL480 PROJECT (COMPUTE MILK PRODUCTION DATA BASED FROM MILK 4DX REPORT)');
        $grid[] = $this->yearsRow();
        foreach ($this->provinces as $province) {
            $row = [$province];
            foreach (array_merge($this->withoutYears, $this->withYears) as $year) {
                $row[] = (float) ($this->computedData[$province][$year] ?? 0);
            }
            $grid[] = $row;
        }
        $this->secondTotalRow = count($grid) + 1;
        $grid[] = $this->totalRow(fn (string $province, int $year) => (float) ($this->computedData[$province][$year] ?? 0));

        return $grid;
    }

    private function headerRow(string $withLabel): array
    {
        $row = ['Province', 'ACTUAL MILK PRODUCTION W/OUT PL480 INTERVENTION'];
        $row = array_pad($row, 1 + count($this->withoutYears), '');
        $row[] = $withLabel;

        return array_pad($row, $this->columnCount, '');
    }

    private function yearsRow(): array
    {
        return array_merge([''], $this->withoutYears, $this->withYears);
    }

    private function totalRow(callable $withYearValue): array
    {
        $row = ['TOTAL'];

        foreach ($this->withoutYears as $year) {
            $total = 0.0;
            foreach ($this->provinces as $province) {
                $total += (float) ($this->computedData[$province][$year] ?? 0);
            }
            $row[] = $total;
        }

        foreach ($this->withYears as $year) {
            $total = 0.0;
            foreach ($this->provinces as $province) {
                $total += $withYearValue($province, (int) $year);
            }
            $row[] = $total;
        }

        return $row;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 24];
        for ($i = 2; $i <= max($this->columnCount, 2); $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 20;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->mergeGroupHeaders($event->sheet, 1);
                $this->mergeGroupHeaders($event->sheet, $this->secondHeaderRow);
            },
        ];
    }

    private function mergeGroupHeaders($sheet, int $headerRow): void
    {
        $withoutCount = count($this->withoutYears);
        $withCount = count($this->withYears);

        if ($withoutCount > 1) {
            $sheet->mergeCells(
                'B'.$headerRow.':'.Coordinate::stringFromColumnIndex(1 + $withoutCount).$headerRow
            );
        }

        if ($withCount > 1) {
            $start = Coordinate::stringFromColumnIndex(2 + $withoutCount);
            $end = Coordinate::stringFromColumnIndex(1 + $withoutCount + $withCount);
            $sheet->mergeCells("{$start}{$headerRow}:{$end}{$headerRow}");
        }
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = Coordinate::stringFromColumnIndex(max($this->columnCount, 1));
        $lastRow = $this->secondTotalRow;
        $firstTotalRow = 3 + count($this->provinces);

        $headerFill = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'f0f0f0'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        $totalFill = [
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'e0e0e0'],
            ],
        ];

        return [
            "A1:{$lastColumn}2" => $headerFill,
            "A{$this->secondHeaderRow}:{$lastColumn}".($this->secondHeaderRow + 1) => $headerFill,
            "A{$firstTotalRow}:{$lastColumn}{$firstTotalRow}" => $totalFill,
            "A{$lastRow}:{$lastColumn}{$lastRow}" => $totalFill,
            "A1:{$lastColumn}{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'name' => 'Arial',
                    'size' => 11,
                ],
            ],
            "B1:{$lastColumn}{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}

<?php

namespace App\Exports;

use App\Exports\Concerns\FormatSheetStyle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MilkUtilizationExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    use FormatSheetStyle {
        styles as private sharedStyles;
    }

    public function __construct(
        private readonly array $rows,
    ) {}

    public function title(): string
    {
        return 'Milk Utilization';
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = $this->sharedStyles($sheet);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow >= 2 && $highestColumn !== 'A') {
            $styles["B2:{$highestColumn}{$highestRow}"] = [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
            ];
        }

        return $styles;
    }
}

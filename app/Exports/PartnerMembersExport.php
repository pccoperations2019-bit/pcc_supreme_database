<?php

namespace App\Exports;

use App\Models\Farmer;
use BackedEnum;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartnerMembersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected int $partnerId
    ) {}

    public function title(): string
    {
        return 'Partner Members';
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Middle Name',
            'Last Name',
            'Nickname',
            'Extension Name',
            'Farmer Code',
            'Status',
            'Gender',
            'Age',
            'Birthday',
            'Civil Status',
            'Contact Number',
            'Email Address',
            'Has Personal Cellphone',
            'Street',
            'Barangay',
            'City',
            'Province',
            'Region',
            'District',
            'Zip Code',
            'Country',
            'Purok/Zone',
            'Spouse Name',
            'Household Size',
            'Position',
            'Partner Name',
            'Center Name',
            'Projects',
        ];
    }

    public function query()
    {
        return Farmer::query()
            ->where('partner_id', $this->partnerId)
            ->with(['address', 'partner:id,name', 'center:id,name', 'projects:id,name'])
            ->select('farmers.*')
            ->orderBy('farmers.last_name')
            ->orderBy('farmers.first_name');
    }

    public function map($farmer): array
    {
        $enumValue = static fn ($value) => $value instanceof BackedEnum ? $value->value : $value;

        return [
            $farmer->first_name ?? 'N/A',
            $farmer->middle_name ?? 'N/A',
            $farmer->last_name ?? 'N/A',
            $farmer->nickname ?? 'N/A',
            $farmer->extension_name ?? 'N/A',
            $farmer->code ?? 'N/A',
            $enumValue($farmer->status) ?? 'N/A',
            $farmer->gender ?? 'N/A',
            $farmer->age ?? 'N/A',
            $farmer->birthday?->format('Y-m-d') ?? 'N/A',
            $enumValue($farmer->civil_status) ?? 'N/A',
            $farmer->contact_number ?? 'N/A',
            $farmer->email_address ?? 'N/A',
            is_null($farmer->has_personal_cellphone)
                ? 'N/A'
                : ($farmer->has_personal_cellphone ? 'Yes' : 'No'),
            $farmer->address?->street ?? 'N/A',
            $farmer->address?->barangay ?? 'N/A',
            $farmer->address?->city ?? 'N/A',
            $farmer->address?->province ?? 'N/A',
            $farmer->address?->region ?? 'N/A',
            $farmer->address?->district ?? 'N/A',
            $farmer->address?->zip_code ?? $farmer->zip_code ?? 'N/A',
            $farmer->address?->country ?? 'N/A',
            $farmer->purok_zone ?? 'N/A',
            $farmer->spouse_name ?? 'N/A',
            $farmer->household_size ?? 'N/A',
            $farmer->position ?? 'N/A',
            $farmer->partner?->name ?? 'N/A',
            $farmer->center?->name ?? 'N/A',
            $farmer->projects->pluck('name')->implode(', ') ?: 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRows = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        for ($i = 1; $i <= $totalRows; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(42);
        }

        $sheet->getRowDimension(1)->setRowHeight(52);

        for ($i = 1; $i <= $totalRows; $i++) {
            $sheet->getStyle("A{$i}")->getAlignment()->setIndent(2);
        }

        $sheet->freezePane('A2');

        return [
            "A1:{$lastColumn}{$totalRows}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'font' => [
                    'name' => 'Arial',
                    'size' => 14,
                ],
            ],

            "A1:{$lastColumn}1" => [
                'font' => [
                    'bold' => true,
                    'name' => 'Arial',
                    'size' => 14,
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
            ],
        ];
    }
}

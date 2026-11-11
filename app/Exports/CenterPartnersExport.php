<?php

namespace App\Exports;

use App\Enums\PartnerCategoryEnum;
use App\Enums\PartnerPartnershipTypeEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Partner;
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

class CenterPartnersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected int $centerId
    ) {}

    public function title(): string
    {
        return 'Center Partners';
    }

    public function headings(): array
    {
        return [
            'Partner Name',
            'Partner Code',
            'Status',
            'Category',
            'Partnership Type',
            'Program',
            'Contact Number',
            'Email Address',
            'Website',
            'Street',
            'Barangay',
            'City',
            'Province',
            'Region',
            'District',
            'Zip Code',
            'Country',
            'Date of Establishment',
            'Contact Person',
            'Position',
            'Telephone Number',
            'Contact Person Phone',
            'Contact Person Email',
            'Total Composition',
            'Male Count',
            'Female Count',
            'Average Monthly Income',
            'Total Farmers',
        ];
    }

    public function query()
    {
        return Partner::query()
            ->where('center_id', $this->centerId)
            ->with(['address'])
            ->select('partners.*')
            ->orderBy('partners.name');
    }

    public function map($partner): array
    {
        $metadata = $partner->metadata ?? [];
        $enumValue = static fn ($value) => $value instanceof BackedEnum ? $value->value : $value;
        $status = $enumValue($partner->status);
        $category = $enumValue($partner->category);
        $partnershipType = $enumValue($partner->partnership_type);
        $program = $enumValue($partner->program);

        return [
            $partner->name,
            $partner->code ?? 'N/A',
            ProfileStatusEnum::labels()[$status] ?? $status ?? 'N/A',
            PartnerCategoryEnum::labels()[$category] ?? $category ?? 'N/A',
            PartnerPartnershipTypeEnum::labels()[$partnershipType] ?? $partnershipType ?? 'N/A',
            ProgramEnum::labels()[$program] ?? $program ?? 'N/A',
            $partner->mobile_number ?? 'N/A',
            $partner->contact_email ?? 'N/A',
            $partner->website ?? 'N/A',
            $partner->address?->street ?? 'N/A',
            $partner->address?->barangay ?? 'N/A',
            $partner->address?->city ?? 'N/A',
            $partner->address?->province ?? 'N/A',
            $partner->address?->region ?? 'N/A',
            $partner->address?->district ?? 'N/A',
            $partner->address?->zip_code ?? 'N/A',
            $partner->address?->country ?? 'N/A',
            $metadata['date_established'] ?? $metadata['date_of_establishment'] ?? 'N/A',
            $partner->contact_name ?? 'N/A',
            $partner->position ?? $metadata['position'] ?? 'N/A',
            $partner->telephone_number ?? $metadata['telephone_number'] ?? 'N/A',
            $metadata['contact_person_contact_no'] ?? 'N/A',
            $metadata['contact_person_email'] ?? 'N/A',
            $partner->total_composition ?? 0,
            $partner->male_count ?? 0,
            $partner->female_count ?? 0,
            $partner->avg_monthly_income ?? 0,
            $partner->total_farmers ?? 0,
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

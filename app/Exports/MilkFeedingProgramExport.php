<?php

namespace App\Exports;

use App\Enums\AddressRegionEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Models\MilkFeedingProgram;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MilkFeedingProgramExport implements FromQuery, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected string|array|null $fundedBy = null,
        protected string|array|null $region = null,
        protected string|array|null $division = null,
        protected string|array|null $city = null,
        protected string|array|null $milkType = null,
        protected string|array|null $modeOfProcurement = null,
        protected int|array|null $supplier = null,
        protected string|array|null $province = null,
    ) {
        // Keep year as array for consistency with multiple sheet export
        if (! is_array($this->year) && $this->year !== null) {
            $this->year = [$this->year];
        }
    }

    public function title(): string
    {
        $title = 'Milk Feeding Programs';
        if ($this->year) {
            if (is_array($this->year)) {
                $title .= ' '.implode(', ', $this->year);
            } else {
                $title .= ' '.$this->year;
            }
        }

        return $title;
    }

    public function headings(): array
    {
        return [
            'Year',
            'Funded By',
            'Region',
            'Center',
            'Division',
            'Province',
            'Municipality',
            'Milk Packs',
            'Beneficiaries',
            'Milk Type',
            'Price per Milk Pack',
            'Supplier',
            'Gross Income of Suppliers',
            'Service Fee/Admin Cost',
            'Contract Amount',
            'Mode of Procurement',
        ];
    }

    public function query()
    {
        return MilkFeedingProgram::query()
            ->leftJoin('centers', 'milk_feeding_programs.center_id', '=', 'centers.id')
            ->leftJoin('partners', 'milk_feeding_programs.partner_id', '=', 'partners.id')
            ->leftJoin('addresses', function ($join) {
                $join->on('milk_feeding_programs.id', '=', 'addresses.addressable_id')
                    ->where('addresses.addressable_type', '=', 'milk_feeding_program');
            })
            ->select([
                'milk_feeding_programs.*',
                'centers.name as center_name',
                'partners.name as partner_name',
                'addresses.region as address_region',
                'addresses.province as address_province',
                'addresses.city as address_city',
            ])
            ->when($this->year, function ($query) {
                is_array($this->year)
                    ? $query->whereIn('milk_feeding_programs.year', $this->year)
                    : $query->where('milk_feeding_programs.year', $this->year);
            })
            ->when($this->centerId, function ($query) {
                is_array($this->centerId)
                    ? $query->whereIn('milk_feeding_programs.center_id', $this->centerId)
                    : $query->where('milk_feeding_programs.center_id', $this->centerId);
            })
            ->when($this->partnerId, function ($query) {
                is_array($this->partnerId)
                    ? $query->whereIn('milk_feeding_programs.partner_id', $this->partnerId)
                    : $query->where('milk_feeding_programs.partner_id', $this->partnerId);
            })
            ->when($this->fundedBy, function ($query) {
                if (is_array($this->fundedBy)) {
                    $fundedByValues = array_map(function ($fb) {
                        $enum = MilkFeedingProgramFunderEnum::fromLabel($fb);

                        return $enum instanceof MilkFeedingProgramFunderEnum ? $enum->value : $fb;
                    }, $this->fundedBy);
                    $query->whereIn('milk_feeding_programs.funded_by', $fundedByValues);
                } else {
                    $enum = MilkFeedingProgramFunderEnum::fromLabel($this->fundedBy);
                    $query->where('milk_feeding_programs.funded_by', $enum instanceof MilkFeedingProgramFunderEnum ? $enum->value : $this->fundedBy);
                }
            })
            ->when($this->region, function ($query) {
                if (is_array($this->region)) {
                    $regionValues = array_map(function ($reg) {
                        $enum = AddressRegionEnum::fromLabel($reg);

                        return $enum instanceof AddressRegionEnum ? $enum->value : $reg;
                    }, $this->region);
                    $query->whereIn('addresses.region', $regionValues);
                } else {
                    $enum = AddressRegionEnum::fromLabel($this->region);
                    $query->where('addresses.region', $enum instanceof AddressRegionEnum ? $enum->value : $this->region);
                }
            })
            ->when($this->milkType, function ($query) {
                if (is_array($this->milkType)) {
                    $milkTypeValues = array_map(function ($mt) {
                        $enum = MilkTypeEnum::fromLabel($mt);

                        return $enum instanceof MilkTypeEnum ? $enum->value : $mt;
                    }, $this->milkType);
                    $query->whereIn('milk_feeding_programs.milk_type', $milkTypeValues);
                } else {
                    $enum = MilkTypeEnum::fromLabel($this->milkType);
                    $query->where('milk_feeding_programs.milk_type', $enum instanceof MilkTypeEnum ? $enum->value : $this->milkType);
                }
            })
            ->when($this->modeOfProcurement, function ($query) {
                if (is_array($this->modeOfProcurement)) {
                    $procurementValues = array_map(function ($proc) {
                        $enum = ModeOfProcurementEnum::fromLabel($proc);

                        return $enum instanceof ModeOfProcurementEnum ? $enum->value : $proc;
                    }, $this->modeOfProcurement);
                    $query->whereIn('milk_feeding_programs.mode_of_procurement', $procurementValues);
                } else {
                    $enum = ModeOfProcurementEnum::fromLabel($this->modeOfProcurement);
                    $query->where('milk_feeding_programs.mode_of_procurement', $enum instanceof ModeOfProcurementEnum ? $enum->value : $this->modeOfProcurement);
                }
            })
            ->when($this->supplier, function ($query) {
                $supplierIds = array_map('intval', (array) $this->supplier);
                $query->whereIn('milk_feeding_programs.partner_id', $supplierIds);
            })
            ->when($this->province, function ($query) {
                if (is_array($this->province)) {
                    $provinces = array_map('trim', $this->province);
                    $query->whereIn('addresses.province', $provinces);
                } else {
                    $query->where('addresses.province', trim($this->province));
                }
            })
            ->orderBy('milk_feeding_programs.year')
            ->orderBy('center_name');
    }

    public function map($milkFeedingProgram): array
    {
        $fundedByValue = $milkFeedingProgram->funded_by instanceof \BackedEnum
            ? $milkFeedingProgram->funded_by->value
            : (is_string($milkFeedingProgram->funded_by) ? $milkFeedingProgram->funded_by : null);
        $fundedByLabel = $fundedByValue
            ? (MilkFeedingProgramFunderEnum::labels()[$fundedByValue] ?? $fundedByValue)
            : '';

        $regionValue = $milkFeedingProgram->address_region instanceof \BackedEnum
            ? $milkFeedingProgram->address_region->value
            : (is_string($milkFeedingProgram->address_region) ? $milkFeedingProgram->address_region : null);
        $regionLabel = $regionValue
            ? (AddressRegionEnum::labels()[$regionValue] ?? $regionValue)
            : 'N/A';

        $milkTypeValue = $milkFeedingProgram->milk_type instanceof \BackedEnum
            ? $milkFeedingProgram->milk_type->value
            : (is_string($milkFeedingProgram->milk_type) ? $milkFeedingProgram->milk_type : null);
        $milkTypeLabel = $milkTypeValue
            ? (MilkTypeEnum::labels()[$milkTypeValue] ?? $milkTypeValue)
            : '';

        $modeOfProcurementValue = $milkFeedingProgram->mode_of_procurement instanceof \BackedEnum
            ? $milkFeedingProgram->mode_of_procurement->value
            : (is_string($milkFeedingProgram->mode_of_procurement) ? $milkFeedingProgram->mode_of_procurement : null);
        $modeOfProcurementLabel = $modeOfProcurementValue
            ? (ModeOfProcurementEnum::labels()[$modeOfProcurementValue] ?? $modeOfProcurementValue)
            : '';

        return [
            $milkFeedingProgram->year,
            $fundedByLabel,
            $regionLabel,
            $milkFeedingProgram->center_name ?? '',
            $milkFeedingProgram->division ?? '',
            $milkFeedingProgram->address_province ?? 'N/A',
            $milkFeedingProgram->address_city ?? 'N/A',
            $milkFeedingProgram->milk_packs ?? 0,
            $milkFeedingProgram->number_of_beneficiaries ?? 0,
            $milkTypeLabel,
            $milkFeedingProgram->price_per_milk_pack ?? 0,
            $milkFeedingProgram->partner_name ?? '',
            $milkFeedingProgram->gross_income ?? 0,
            $milkFeedingProgram->service_fee ?? 0,
            $milkFeedingProgram->total_contract_amount ?? 0,
            $modeOfProcurementLabel,
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

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Year
            'B' => 22,  // Funded By
            'C' => 28,  // Region
            'D' => 28,  // Center
            'E' => 28,  // Division
            'F' => 22,  // Province
            'G' => 22,  // Municipality
            'H' => 18,  // Milk Packs
            'I' => 18,  // Beneficiaries
            'J' => 22,  // Milk Type
            'K' => 22,  // Price per Milk Pack
            'L' => 28,  // Supplier
            'M' => 28,  // Gross Income of Suppliers
            'N' => 24,  // Service Fee/Admin Cost
            'O' => 24,  // Contract Amount
            'P' => 28,  // Mode of Procurement
        ];
    }
}

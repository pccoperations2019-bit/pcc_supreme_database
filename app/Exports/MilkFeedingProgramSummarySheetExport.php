<?php

namespace App\Exports;

use App\Enums\AddressRegionEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Models\MilkFeedingProgram;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MilkFeedingProgramSummarySheetExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected string|array|null $fundedBy = null,
        protected string|array|null $region = null,
        protected ?string $milkType = null,
        protected string|array|null $modeOfProcurement = null,
        protected int|array|null $supplier = null,
        protected string|array|null $province = null,
        protected ?array $yearRange = null,
    ) {}

    public function title(): string
    {
        return 'Program Summary';
    }

    public function headings(): array
    {
        return [
            'Year',
            'Funded By',
            'Region',
            'Total Centers',
            'Total Milk Packs',
            'Total Beneficiaries',
            'Total Contract Amount',
            'Average Price per Pack',
            'Total Service Fee',
            'Total Gross Income',
        ];
    }

    public function query()
    {
        return MilkFeedingProgram::query()
            ->leftJoin('centers', 'milk_feeding_programs.center_id', '=', 'centers.id')
            ->leftJoin('partners', 'milk_feeding_programs.partner_id', '=', 'partners.id')
            ->leftJoin('addresses', function ($join) {
                $join->on('milk_feeding_programs.id', '=', 'addresses.addressable_id')
                    ->where('addresses.addressable_type', '=', 'App\\Models\\MilkFeedingProgram');
            })
            ->select([
                'milk_feeding_programs.year',
                'milk_feeding_programs.funded_by',
                'addresses.region as address_region',
                'addresses.province as address_province',
                'addresses.city as address_city',
                DB::raw('COUNT(DISTINCT milk_feeding_programs.center_id) as total_centers'),
                DB::raw('SUM(milk_feeding_programs.milk_packs) as total_milk_packs'),
                DB::raw('SUM(milk_feeding_programs.number_of_beneficiaries) as total_beneficiaries'),
                DB::raw('SUM(milk_feeding_programs.total_contract_amount) as total_contract_amount'),
                DB::raw('AVG(milk_feeding_programs.price_per_milk_pack) as avg_price_per_pack'),
                DB::raw('SUM(milk_feeding_programs.service_fee) as total_service_fee'),
                DB::raw('SUM(milk_feeding_programs.gross_income) as total_gross_income'),
            ])
            ->when($this->year, function ($query) {
                if ($this->yearRange) {
                    $query->whereBetween('milk_feeding_programs.year', $this->yearRange);
                } else {
                    is_array($this->year)
                        ? $query->whereIn('milk_feeding_programs.year', $this->year)
                        : $query->where('milk_feeding_programs.year', $this->year);
                }
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
                $enum = MilkTypeEnum::fromLabel($this->milkType);
                $query->where('milk_feeding_programs.milk_type', $enum instanceof MilkTypeEnum ? $enum->value : $this->milkType);
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
                    $query->where(function ($q) {
                        foreach ($this->province as $prov) {
                            $q->orWhereRaw('LOWER(TRIM(addresses.province)) = LOWER(TRIM(?))', [trim($prov)]);
                        }
                    });
                } else {
                    $query->whereRaw('LOWER(TRIM(addresses.province)) = LOWER(TRIM(?))', [trim($this->province)]);
                }
            })
            ->groupBy(['milk_feeding_programs.year', 'milk_feeding_programs.funded_by', 'addresses.region'])
            ->orderBy('milk_feeding_programs.year')
            ->orderBy('milk_feeding_programs.funded_by');
    }

    public function map($record): array
    {
        $fundedByValue = $record->funded_by;
        $fundedByLabel = $fundedByValue
            ? (MilkFeedingProgramFunderEnum::labels()[$fundedByValue] ?? $fundedByValue)
            : '';

        $regionValue = $record->address_region;
        $regionLabel = $regionValue
            ? (AddressRegionEnum::labels()[$regionValue] ?? $regionValue)
            : 'N/A';

        return [
            $record->year,
            $fundedByLabel,
            $regionLabel,
            $record->total_centers,
            number_format($record->total_milk_packs, 0),
            number_format($record->total_beneficiaries, 0),
            number_format($record->total_contract_amount, 2),
            number_format($record->avg_price_per_pack, 2),
            number_format($record->total_service_fee, 2),
            number_format($record->total_gross_income, 2),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Apply borders to all cells
            'A1:J'.$sheet->getHighestRow() => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
            // Make header row bold
            'A1:J1' => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }
}

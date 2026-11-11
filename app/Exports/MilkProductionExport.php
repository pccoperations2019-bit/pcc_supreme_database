<?php

namespace App\Exports;

use App\Enums\AddressRegionEnum;
use App\Enums\MilkingTimeEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\MilkProduction;
use Illuminate\Support\Facades\DB;
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

class MilkProductionExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected ?int $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected ?int $farmerId = null,
        protected int|array|null $month = null,
        protected ?int $week = null,
        protected ?string $program = null,
        protected ?string $startDate = null,
        protected ?string $endDate = null,
        protected ?string $status = null,
        protected ?string $milkingTime = null,
        protected ?string $sourceOfMilk = null,
        protected string|array|null $region = null,
        protected string|array|null $province = null,
        protected string|array|null $city = null,
    ) {
        // Handle case where year might be array from multi-select
        if (is_array($this->year)) {
            // For multiple years, use the first year for filtering
            // This ensures backward compatibility
            $this->year = $this->year[0] ?? null;
        }
    }

    public function title(): string
    {
        $title = 'Milk Production';
        if ($this->year) {
            $title .= ' '.$this->year;
        }

        return $title;
    }

    public function headings(): array
    {
        return [
            'Center Code',
            'Partner Code',
            'Name of Coop/Association',
            'Program',
            'Region',
            'Province',
            'Municipality',
            'Herd Code',
            'Name of farmer',
            'ID Number',
            'Date Start',
            'Date End',
            'yyyy',
            'mm',
            'wk',
            'AM',
            'PM',
            'Total',
            'No. of cows',
            'Price per kg (Raw Milk)',
            'Milk fed to calves, kg',
            'Family Milk Consumption,kg',
            'Milk spoiled, kg',
            'Milk Traded',
            'Sales of Value Added Milk (PhP)',
            'Total Milk Prod\'n Purchased, kg',
        ];
    }

    public function query()
    {
        return MilkProduction::query()
            ->select([
                'milk_productions.*',
                'centers.code as center_code',
                'partners.code as partner_code',
                'partners.name as partner_name',
                'herds.code as herd_code',
                DB::raw("CONCAT(farmers.first_name, ' ', farmers.last_name) as farmer_name"),
                DB::raw('(SELECT farm_gate_price FROM milk_sales WHERE milk_production_id = milk_productions.id ORDER BY id ASC LIMIT 1) as farm_gate_price'),
                DB::raw('(SELECT value_added_products_sales FROM milk_sales WHERE milk_production_id = milk_productions.id ORDER BY id ASC LIMIT 1) as value_added_products_sales'),
                DB::raw('(SELECT COALESCE(SUM(total_volume_traded), 0) FROM milk_tradeds WHERE milk_production_id = milk_productions.id) as total_volume_traded'),
                DB::raw('(SELECT COALESCE(SUM(fed_to_calves), 0) FROM milk_usages WHERE milk_production_id = milk_productions.id) as total_fed_to_calves'),
                DB::raw('(SELECT COALESCE(SUM(family_consumption), 0) FROM milk_usages WHERE milk_production_id = milk_productions.id) as total_family_consumption'),
                DB::raw('(SELECT COALESCE(SUM(milk_spoiled_volume), 0) FROM milk_wastages WHERE milk_production_id = milk_productions.id) as total_milk_spoiled_volume'),
                'partner_addresses.region as partner_region',
                'partner_addresses.province as partner_province',
                'partner_addresses.city as partner_city',
            ])
            ->leftJoin('centers', 'milk_productions.center_id', '=', 'centers.id')
            ->leftJoin('partners', 'milk_productions.partner_id', '=', 'partners.id')
            ->leftJoin('farmers', 'milk_productions.farmer_id', '=', 'farmers.id')
            ->leftJoin('herds', 'milk_productions.herd_id', '=', 'herds.id')
            ->leftJoin('addresses as partner_addresses', function ($join) {
                $join->on('partner_addresses.addressable_id', '=', 'partners.id')
                    ->where('partner_addresses.addressable_type', '=', 'partner');
            })
            ->when($this->year, fn ($query) => $query->where('milk_productions.year', $this->year))
            ->when($this->month, function ($query) {
                $month = $this->month;
                if (is_array($month)) {
                    $query->whereIn('milk_productions.month', $month);
                } else {
                    $query->where('milk_productions.month', $month);
                }
            })
            ->when($this->week, fn ($query) => $query->where('milk_productions.week', MilkProductionWeekEnum::tryFrom($this->week)?->value ?? $this->week))
            ->when($this->centerId, function ($query) {
                $centerId = $this->centerId;
                if (is_array($centerId)) {
                    $query->whereIn('milk_productions.center_id', $centerId);
                } else {
                    $query->where('milk_productions.center_id', $centerId);
                }
            })
            ->when($this->partnerId, function ($query) {
                $partnerId = $this->partnerId;
                if (is_array($partnerId)) {
                    $query->whereIn('milk_productions.partner_id', $partnerId);
                } else {
                    $query->where('milk_productions.partner_id', $partnerId);
                }
            })
            ->when($this->farmerId, fn ($query) => $query->where('milk_productions.farmer_id', $this->farmerId))
            ->when($this->program, fn ($query) => $query->where('milk_productions.program', ProgramEnum::tryFrom($this->program)?->value ?? $this->program))
            ->when($this->status, fn ($query) => $query->where('milk_productions.status', ProductionStatusEnum::tryFrom($this->status)?->value ?? $this->status))
            ->when($this->milkingTime, fn ($query) => $query->where('milk_productions.milking_times', MilkingTimeEnum::tryFrom($this->milkingTime)?->value ?? $this->milkingTime))
            ->when($this->region, function ($query) {
                $region = $this->region;
                if (is_array($region)) {
                    $query->whereIn('partner_addresses.region', $region);
                } else {
                    $query->where('partner_addresses.region', $region);
                }
            })
            ->when($this->province, function ($query) {
                $province = $this->province;
                if (is_array($province)) {
                    $query->where(function ($inner) use ($province) {
                        foreach ($province as $value) {
                            $inner->orWhereRaw('LOWER(TRIM(partner_addresses.province)) = LOWER(TRIM(?))', [$value]);
                        }
                    });
                } else {
                    $query->whereRaw('LOWER(TRIM(partner_addresses.province)) = LOWER(TRIM(?))', [$province]);
                }
            })
            ->when($this->city, function ($query) {
                $city = $this->city;
                if (is_array($city)) {
                    $query->where(function ($inner) use ($city) {
                        foreach ($city as $value) {
                            $inner->orWhereRaw('LOWER(TRIM(partner_addresses.city)) = LOWER(TRIM(?))', [$value]);
                        }
                    });
                } else {
                    $query->whereRaw('LOWER(TRIM(partner_addresses.city)) = LOWER(TRIM(?))', [$city]);
                }
            })
            ->orderBy('milk_productions.id')
            ->distinct();
    }

    public function map($milkProduction): array
    {
        $regionValue = $milkProduction->partner_region;
        $regionLabel = $regionValue
            ? (AddressRegionEnum::labels()[$regionValue] ?? $regionValue)
            : '';

        return [
            $milkProduction->center_code,
            $milkProduction->partner_code,
            $milkProduction->partner_name,
            ProgramEnum::tryFrom($milkProduction->program)?->label() ?? $milkProduction->program,
            $regionLabel,
            $milkProduction->partner_province,
            $milkProduction->partner_city,
            $milkProduction->herd_code,
            $milkProduction->farmer_name,
            $milkProduction->id_number,
            $milkProduction->period_start?->format('Y-m-d'),
            $milkProduction->period_end?->format('Y-m-d'),
            $milkProduction->year,
            $milkProduction->month,
            $milkProduction->week?->value,
            $milkProduction->am_volume,
            $milkProduction->pm_volume,
            $milkProduction->total_volume_produced,
            $milkProduction->number_of_lactating,
            $milkProduction->farm_gate_price,
            $milkProduction->total_fed_to_calves,
            $milkProduction->total_family_consumption,
            $milkProduction->total_milk_spoiled_volume,
            $milkProduction->total_volume_traded,
            $milkProduction->value_added_products_sales,
            $milkProduction->volume_from_farmers,
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

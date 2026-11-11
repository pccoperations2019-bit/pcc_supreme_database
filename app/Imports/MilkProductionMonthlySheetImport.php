<?php

namespace App\Imports;

use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\MilkingTimeEnum;
use App\Enums\MonthEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Partner;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class MilkProductionMonthlySheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow
{
    private UpsertMilkProductionAction $upsertMilkProductionAction;

    private UpsertMilkSaleAction $upsertMilkSaleAction;

    private UpsertPartnerAction $upsertPartnerAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $defaultPartnerIdByCenterId = [];

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    public function __construct(?UpsertMilkProductionAction $upsertMilkProductionAction = null, ?UpsertMilkSaleAction $upsertMilkSaleAction = null, ?UpsertPartnerAction $upsertPartnerAction = null)
    {
        $this->upsertMilkProductionAction = $upsertMilkProductionAction ?? resolve(UpsertMilkProductionAction::class);
        $this->upsertMilkSaleAction = $upsertMilkSaleAction ?? resolve(UpsertMilkSaleAction::class);
        $this->upsertPartnerAction = $upsertPartnerAction ?? resolve(UpsertPartnerAction::class);
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setDefaultYear(?int $year): void {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheetName = $event->sheet->getTitle();
            },
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row): void
    {
        $rowData = $row->toArray(calculateFormulas: true);

        // Extract common fields for monthly data
        $centerName = $this->getRowValue($rowData, ['center', 'Center', 'center name', 'center_name', 'center code', 'center_code']);
        $cooperativeName = $this->getRowValue($rowData, ['cooperative', 'Cooperative', 'coop']);
        $type = $this->getRowValue($rowData, ['type', 'Type']);
        $item = $this->getRowValue($rowData, ['item', 'Item']);
        $rowYear = $this->getRowValue($rowData, ['year', 'Year', 'YEAR']);

        $rowYear = $rowYear !== null && $rowYear !== '' ? (int) $rowYear : null;

        $centerName = $centerName !== null ? trim((string) $centerName) : null;
        $cooperativeName = $cooperativeName !== null ? trim((string) $cooperativeName) : null;
        $type = $type !== null ? trim((string) $type) : null;
        $item = $item !== null ? trim((string) $item) : null;

        $centerName = $centerName === '' ? null : $centerName;
        $cooperativeName = $cooperativeName === '' ? null : $cooperativeName;
        $type = $type === '' ? null : $type;
        $item = $item === '' ? null : $item;

        // Skip if essential data is missing
        if (! $centerName || ! $rowYear || ! $item) {
            return;
        }

        // Only process PRODUCTION and SALES items
        $itemUpper = strtoupper(trim($item));
        if (! in_array($itemUpper, ['PRODUCTION', 'SALES'])) {
            return;
        }

        // Use cooperative as partner name if available, otherwise use center
        // For IH program, center is the partner, so don't create separate partner
        if ($this->program === ProgramEnum::IH) {
            $partnerName = $centerName;
        } else {
            // If no cooperative, don't create a partner - set to null
            $partnerName = $cooperativeName ?: null;
        }

        // If no partner name, set partnerId to null directly
        if (! $partnerName) {
            [$centerId, $partnerId] = [$this->resolveCenter($centerName, $row->getIndex()), null];
        } else {
            [$centerId, $partnerId] = $this->resolveCenterPartner($centerName, $partnerName, $row->getIndex());
        }

        // Process each month column using MonthEnum
        foreach (MonthEnum::cases() as $monthEnum) {
            $monthColumn = strtoupper(substr($monthEnum->name, 0, 3));
            $monthNumber = $monthEnum->value;

            $totalVolume = $this->getRowValue($rowData, [$monthColumn, $monthEnum->name]);

            if ($totalVolume === null || $totalVolume === '') {
                continue;
            }

            $totalVolume = Sanitizer::parseNumeric($totalVolume);
            if ($totalVolume == 0) {
                continue;
            }

            $transactionDate = Date::create($rowYear, $monthNumber, 1)->endOfMonth()->startOfDay();
            $periodStart = $transactionDate->copy()->startOfMonth();
            $periodEnd = $transactionDate->copy()->endOfMonth();

            $year = (int) $transactionDate->format('Y');
            $month = (int) $transactionDate->format('m');
            $quarter = (int) ceil($month / 3);
            $week = null;

            try {
                if ($itemUpper === 'PRODUCTION') {

                    $this->upsertMilkProductionAction->execute(
                        productionCode: null,
                        centerId: $centerId,
                        partnerId: $partnerId,
                        farmerId: null,
                        transactionDate: $transactionDate,
                        durationStart: $periodStart,
                        durationEnd: $periodEnd,
                        totalVolume: $totalVolume,
                        volumeFromFarmers: null,
                        milkingTimes: MilkingTimeEnum::DEFAULT,
                        numberOfLactating: null,
                        avgMilkPerAnimalPerDay: null,
                        program: $this->program,
                        status: ProductionStatusEnum::DEFAULT,
                        notes: "Type: {$type}, Item: {$item}",
                        year: $year,
                        month: $month,
                        quarter: $quarter,
                        week: $week,
                        idNumber: null,
                        collectionDate: $transactionDate,
                        herdId: null,
                        amVolume: null,
                        pmVolume: null,
                        sourceOfMilk: $type
                    );
                } elseif ($itemUpper === 'SALES') {
                    $this->upsertMilkSaleAction->execute(
                        milkProductionId: null,
                        centerId: $centerId,
                        partnerId: $partnerId,
                        farmerId: null,
                        transactionDate: $transactionDate,
                        year: $year,
                        month: $month,
                        quarter: $quarter,
                        week: $week,
                        periodStart: $periodStart->format('Y-m-d'),
                        periodEnd: $periodEnd->format('Y-m-d'),
                        totalVolumeSold: $totalVolume,
                        farmGatePrice: 0,
                        rawMilkTradedSales: 0,
                        valueAddedProductsSales: $totalVolume,
                        dairyBoxSales: 0,
                        milkSales: 0,
                        milkFeedingSales: 0,
                        salesOfValueAdded: $totalVolume,
                        valueOfTotalMilkProduction: 0,
                        program: $this->program->value
                    );
                }
            } catch (\Exception $e) {
                Log::error('MilkProductionMonthlySheetImport: Error processing row', [
                    'row' => $row->getIndex(),
                    'month' => $monthColumn,
                    'item' => $itemUpper,
                    'error' => $e->getMessage(),
                    'data' => $rowData,
                ]);
            }
        }
    }

    private function getRowValue(array $rowData, array $keys, $default = null)
    {
        $rowDataLower = array_change_key_case($rowData, CASE_LOWER);

        foreach ($keys as $key) {
            $keyLower = strtolower($key);
            if (isset($rowDataLower[$keyLower]) && $rowDataLower[$keyLower] !== null && $rowDataLower[$keyLower] !== '') {
                return $rowDataLower[$keyLower];
            }
        }

        return $default;
    }

    private function resolveCenter(?string $centerName, int $rowIndex): ?int
    {
        if (! $this->centerIdsByCode && ! $this->centerIdsByName) {
            $centers = DB::table('centers')->select('id', 'code', 'name')->get();
            foreach ($centers as $center) {
                if (! empty($center->code)) {
                    $this->centerIdsByCode[strtoupper(trim((string) $center->code))] = (int) $center->id;
                }
                if (! empty($center->name)) {
                    $this->centerIdsByName[strtolower(trim((string) $center->name))] = (int) $center->id;
                }
            }
        }

        $centerId = null;
        if ($centerName) {
            $centerId = $this->centerIdsByCode[strtoupper(trim($centerName))]
                ?? $this->centerIdsByName[strtolower(trim($centerName))]
                ?? null;
        }

        if (! $centerId) {
            Log::warning('MilkProductionMonthlySheetImport: Center not resolved', [
                'row' => $rowIndex,
                'center_value' => $centerName,
            ]);
        }

        return $centerId;
    }

    private function resolveCenterPartner(?string $centerName, ?string $partnerName, int $rowIndex): array
    {
        if (! $this->centerIdsByCode && ! $this->centerIdsByName) {
            $centers = DB::table('centers')->select('id', 'code', 'name')->get();
            foreach ($centers as $center) {
                if (! empty($center->code)) {
                    $this->centerIdsByCode[strtoupper(trim((string) $center->code))] = (int) $center->id;
                }
                if (! empty($center->name)) {
                    $this->centerIdsByName[strtolower(trim((string) $center->name))] = (int) $center->id;
                }
            }
        }

        $centerId = null;
        if ($centerName) {
            $centerId = $this->centerIdsByCode[strtoupper(trim($centerName))]
                ?? $this->centerIdsByName[strtolower(trim($centerName))]
                ?? null;
        }

        $partner = null;
        if ($partnerName && $centerId) {

            if ($this->program === ProgramEnum::IH &&
                strtolower(trim($partnerName)) === strtolower(trim($centerName))) {

                $partnerId = null;

                return [$centerId, $partnerId];
            }

            $partner = Partner::where('center_id', $centerId)
                ->whereRaw('LOWER(name) = ?', [strtolower($partnerName)])
                ->first();

            if (! $partner) {
                $partner = Partner::where('center_id', $centerId)
                    ->where('name', 'LIKE', "%{$partnerName}%")
                    ->first();
            }

            if (! $partner) {
                $partner = $this->upsertPartnerAction->execute(
                    code: null,
                    centerId: $centerId,
                    name: $partnerName,
                    category: null,
                    partnershipType: null,
                    website: null,
                    logo: null,
                    description: null,
                    contactName: null,
                    contactPosition: null,
                    contactEmail: null,
                    contactPhone: null,
                    telephoneNumber: null,
                    status: null
                );
            }
        }

        if (! $partnerName && $centerId) {
            if (! array_key_exists($centerId, $this->defaultPartnerIdByCenterId)) {
                $partners = Partner::query()
                    ->where('center_id', $centerId)
                    ->select('id')
                    ->limit(2)
                    ->get();

                $this->defaultPartnerIdByCenterId[$centerId] = $partners->count() === 1 ? (int) $partners->first()->id : null;
            }

            if ($this->defaultPartnerIdByCenterId[$centerId]) {
                $partner = Partner::find($this->defaultPartnerIdByCenterId[$centerId]);
            }
        }

        if (! $centerId && $partnerName) {
            $partner = Partner::whereRaw('LOWER(name) = ?', [strtolower($partnerName)])->first();
            $centerId = $partner?->center_id;
        }

        if (! $centerId) {
            Log::warning('MilkProductionMonthlySheetImport: Center not resolved', [
                'row' => $rowIndex,
                'center_value' => $centerName,
                'partner_value' => $partnerName,
            ]);
        }

        return [$centerId, $partner?->id];
    }
}

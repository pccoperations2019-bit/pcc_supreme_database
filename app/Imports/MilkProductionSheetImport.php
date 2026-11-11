<?php

namespace App\Imports;

use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\CenterEnum;
use App\Enums\MilkingTimeEnum;
use App\Enums\MonthEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\Partner;
use App\Support\Sanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class MilkProductionSheetImport implements OnEachRow, ShouldQueue, SkipsEmptyRows, WithBatchInserts, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow
{
    use Queueable;

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $partnerIdsByCenterAndName = [];

    private ?string $sheetName = null;

    public function __construct(
        private readonly UpsertMilkProductionAction $upsertMilkProductionAction,
        private readonly UpsertMilkSaleAction $upsertMilkSaleAction,
        private readonly UpsertPartnerAction $upsertPartnerAction,
    ) {}

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->sheetName = $event->sheet->getTitle();
            },
        ];
    }

    public function onRow(Row $row): void
    {
        $sheetContext = $this->parseSheetContext($this->sheetName);
        if (! $sheetContext) {
            return;
        }

        $rowData = $row->toArray(calculateFormulas: true);

        $rawCenter = $this->normalizeString($this->getRowValue($rowData, ['center', 'center_name', 'center code', 'center_code']));
        $cooperative = $this->normalizeString($this->getRowValue($rowData, ['cooperative', 'coop']));
        $rawType = $this->normalizeString($this->getRowValue($rowData, ['type']));
        $rawItem = $this->normalizeString($this->getRowValue($rowData, ['item']));

        $program = $this->resolveProgram($rawType) ?? $this->program;
        $item = $this->resolveItem($rawItem, $sheetContext['item']);
        $year = $sheetContext['year'] ?? $this->parseYear($this->getRowValue($rowData, ['year']));

        if (! $year || ! $item) {
            Log::warning('MilkProductionImport: Row skipped due to missing year or item', [
                'sheet' => $this->sheetName,
                'row' => $row->getIndex(),
                'item' => $rawItem,
                'year' => $year,
            ]);

            return;
        }

        $centerId = $this->resolveCenterId($rawCenter);
        $partnerId = $this->resolvePartnerId($centerId, $cooperative, $program);

        if (! $centerId && $rawCenter) {
            Log::warning('MilkProductionImport: Center not resolved, continuing with nullable center_id', [
                'sheet' => $this->sheetName,
                'row' => $row->getIndex(),
                'center' => $rawCenter,
            ]);
        }

        $notes = $this->buildNotes(
            sheetName: $this->sheetName,
            rawType: $rawType,
            rawCenter: $rawCenter,
            cooperative: $cooperative
        );

        foreach (MonthEnum::cases() as $monthEnum) {
            $monthLabel = MonthEnum::labels()[$monthEnum->value] ?? $monthEnum->name;

            $rawVolume = $this->getRowValue($rowData, [
                strtolower(substr($monthEnum->name, 0, 3)),
                strtolower($monthLabel),
            ]);

            if ($rawVolume === null || $rawVolume === '') {
                continue;
            }

            $volume = Sanitizer::parseNumeric($rawVolume);
            if ($volume === 0.0) {
                continue;
            }

            $transactionDate = now()->setDate($year, $monthEnum->value, 1)->endOfMonth();
            $periodStart = $transactionDate->copy()->startOfMonth();
            $periodEnd = $transactionDate->copy()->endOfMonth();
            $quarter = (int) ceil($monthEnum->value / 3);

            if ($item === 'PRODUCTION') {
                $this->storeProduction(
                    centerId: $centerId,
                    partnerId: $partnerId,
                    program: $program,
                    transactionDate: $transactionDate,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    volume: $volume,
                    year: $year,
                    month: $monthEnum->value,
                    quarter: $quarter,
                    notes: $notes,
                    sourceOfMilk: $rawType
                );

                continue;
            }

            $this->storeSale(
                centerId: $centerId,
                partnerId: $partnerId,
                program: $program,
                transactionDate: $transactionDate,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                volume: $volume,
                year: $year,
                month: $monthEnum->value,
                quarter: $quarter
            );
        }
    }

    private function parseSheetContext(?string $sheetName): ?array
    {
        if (! $sheetName) {
            return null;
        }

        $trimmed = trim($sheetName);

        if (preg_match('/^milk\s+production$/i', $trimmed)) {
            return ['year' => null, 'item' => 'PRODUCTION'];
        }

        if (preg_match('/^milk\s+sales$/i', $trimmed)) {
            return ['year' => null, 'item' => 'SALES'];
        }

        if (preg_match('/^data\s+(?<year>\d{4})\s+milk\s+(?<item>production|sales)$/i', $trimmed, $matches)) {
            return [
                'year' => (int) $matches['year'],
                'item' => strtoupper($matches['item']),
            ];
        }

        return null;
    }

    private function resolveItem(?string $rawItem, string $defaultItem): ?string
    {
        $candidate = strtoupper($rawItem ?: $defaultItem);

        return in_array($candidate, ['PRODUCTION', 'SALES'], true) ? $candidate : null;
    }

    private function resolveProgram(?string $rawType): ?ProgramEnum
    {
        if (! $rawType) {
            return null;
        }

        $value = $this->normalizeLookupValue($rawType);

        return match ($value) {
            'cbed' => ProgramEnum::CBED,
            'ih', 'institutional', 'instutional', 'institution' => ProgramEnum::IH,
            default => null,
        };
    }

    private function resolveCenterId(?string $centerName): ?int
    {
        if (! $centerName) {
            return null;
        }

        $this->loadCenters();

        $normalized = $this->normalizeLookupValue($centerName);
        $matchedCode = $this->centerMatchMap()[$normalized] ?? null;

        if ($matchedCode) {
            return $this->centerIdsByCode[$matchedCode] ?? null;
        }

        return $this->centerIdsByCode[$normalized]
            ?? $this->centerIdsByName[$normalized]
            ?? null;
    }

    private function resolvePartnerId(?int $centerId, ?string $cooperative, ProgramEnum $program): ?int
    {
        if (! $centerId || ! $cooperative || $program === ProgramEnum::IH) {
            return null;
        }

        $cacheKey = "{$centerId}:".$this->normalizeLookupValue($cooperative);
        if (isset($this->partnerIdsByCenterAndName[$cacheKey])) {
            return $this->partnerIdsByCenterAndName[$cacheKey];
        }

        $partner = Partner::query()
            ->where('center_id', $centerId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($cooperative))])
            ->first();

        if (! $partner) {
            $partner = Partner::query()
                ->where('center_id', $centerId)
                ->where('name', 'like', '%'.trim($cooperative).'%')
                ->first();
        }

        if (! $partner) {
            $partner = $this->upsertPartnerAction->execute(
                code: null,
                centerId: $centerId,
                name: trim($cooperative),
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

        return $this->partnerIdsByCenterAndName[$cacheKey] = $partner->id;
    }

    private function storeProduction(
        ?int $centerId,
        ?int $partnerId,
        ProgramEnum $program,
        \DateTimeInterface $transactionDate,
        \DateTimeInterface $periodStart,
        \DateTimeInterface $periodEnd,
        ?float $volume,
        int $year,
        int $month,
        int $quarter,
        string $notes,
        ?string $sourceOfMilk
    ): MilkProduction {
        if ($centerId === null && $partnerId === null) {
            return MilkProduction::create([
                'center_id' => null,
                'partner_id' => null,
                'farmer_id' => null,
                'transaction_date' => $transactionDate,
                'collection_date' => $transactionDate,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'total_volume_produced' => $volume,
                'milking_times' => MilkingTimeEnum::DEFAULT->value,
                'source_of_milk' => $sourceOfMilk,
                'program' => $program->value,
                'status' => ProductionStatusEnum::DEFAULT->value,
                'notes' => $notes,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ]);
        }

        return $this->upsertMilkProductionAction->execute(
            productionCode: null,
            centerId: $centerId,
            partnerId: $partnerId,
            farmerId: null,
            transactionDate: $transactionDate,
            durationStart: $periodStart,
            durationEnd: $periodEnd,
            totalVolume: $volume,
            volumeFromFarmers: null,
            milkingTimes: MilkingTimeEnum::DEFAULT,
            numberOfLactating: null,
            avgMilkPerAnimalPerDay: null,
            program: $program,
            status: ProductionStatusEnum::DEFAULT,
            notes: $notes,
            year: $year,
            month: $month,
            quarter: $quarter,
            week: null,
            idNumber: null,
            collectionDate: $transactionDate,
            herdId: null,
            amVolume: null,
            pmVolume: null,
            sourceOfMilk: $sourceOfMilk
        );
    }

    private function storeSale(
        ?int $centerId,
        ?int $partnerId,
        ProgramEnum $program,
        \DateTimeInterface $transactionDate,
        \DateTimeInterface $periodStart,
        \DateTimeInterface $periodEnd,
        float $volume,
        int $year,
        int $month,
        int $quarter
    ): MilkSale {
        if ($centerId === null && $partnerId === null) {
            return MilkSale::create([
                'milk_production_id' => null,
                'center_id' => null,
                'partner_id' => null,
                'farmer_id' => null,
                'transaction_date' => $transactionDate,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'total_volume_sold' => $volume,
                'value_added_products_sales' => $volume,
                'sales_of_value_added' => $volume,
                'program' => $program->value,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ]);
        }

        return $this->upsertMilkSaleAction->execute(
            milkProductionId: null,
            centerId: $centerId,
            partnerId: $partnerId,
            farmerId: null,
            transactionDate: $transactionDate,
            year: $year,
            month: $month,
            quarter: $quarter,
            week: null,
            periodStart: $periodStart->format('Y-m-d'),
            periodEnd: $periodEnd->format('Y-m-d'),
            totalVolumeSold: $volume,
            farmGatePrice: 0,
            rawMilkTradedSales: 0,
            valueAddedProductsSales: $volume,
            dairyBoxSales: 0,
            milkSales: 0,
            milkFeedingSales: 0,
            salesOfValueAdded: $volume,
            valueOfTotalMilkProduction: 0,
            program: $program->value
        );
    }

    private function loadCenters(): void
    {
        if ($this->centerIdsByCode || $this->centerIdsByName) {
            return;
        }

        $centers = DB::table('centers')
            ->select('id', 'code', 'name')
            ->get();

        foreach ($centers as $center) {
            if (! empty($center->code)) {
                $this->centerIdsByCode[$this->normalizeLookupValue((string) $center->code)] = (int) $center->id;
            }

            if (! empty($center->name)) {
                $this->centerIdsByName[$this->normalizeLookupValue((string) $center->name)] = (int) $center->id;
            }
        }
    }

    private function centerMatchMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];

        foreach (CenterEnum::cases() as $centerEnum) {
            $code = $this->normalizeLookupValue($centerEnum->code());

            foreach ([
                $centerEnum->value,
                $centerEnum->code(),
                $centerEnum->label(),
                $centerEnum->fullName(),
                str_replace('PCC at ', '', $centerEnum->label()),
                str_replace('PCC at ', '', $centerEnum->fullName()),
            ] as $variant) {
                $map[$this->normalizeLookupValue($variant)] = $code;
            }
        }

        foreach ([
            'gene pool' => CenterEnum::GP,
            'genepool' => CenterEnum::GP,
            'national headquarters' => CenterEnum::NHQ,
            'mindanao state university' => CenterEnum::MSU,
            'mariano marcos state university' => CenterEnum::MMSU,
            'don mariano marcos memorial state university' => CenterEnum::DMMMSU,
            'cagayan state university' => CenterEnum::CSU,
            'central luzon state university' => CenterEnum::CLSU,
            'university of the philippines los banos' => CenterEnum::UPLB,
            'west visayas state university' => CenterEnum::WVSU,
            'la carlota stock farm' => CenterEnum::LCSF,
            'ubay stock farm' => CenterEnum::USF,
            'visayas state university' => CenterEnum::VSU,
            'mindanao livestock production center' => CenterEnum::MLPC,
            'central mindanao university' => CenterEnum::CMU,
            'university of southern mindanao' => CenterEnum::USM,
        ] as $variant => $centerEnum) {
            $map[$this->normalizeLookupValue($variant)] = $this->normalizeLookupValue($centerEnum->code());
        }

        return $map;
    }

    private function buildNotes(string $sheetName, ?string $rawType, ?string $rawCenter, ?string $cooperative): string
    {
        return collect([
            "Sheet: {$sheetName}",
            $rawType ? "Type: {$rawType}" : null,
            $rawCenter ? "Center: {$rawCenter}" : null,
            $cooperative ? "Cooperative: {$cooperative}" : null,
        ])->filter()->implode(' | ');
    }

    private function getRowValue(array $rowData, array $keys, mixed $default = null): mixed
    {
        $normalizedData = [];

        foreach ($rowData as $key => $value) {
            $normalizedData[$this->normalizeLookupValue((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeLookupValue($key);

            if (array_key_exists($normalizedKey, $normalizedData) && $normalizedData[$normalizedKey] !== '') {
                return $normalizedData[$normalizedKey];
            }
        }

        return $default;
    }

    private function parseYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) Sanitizer::parseNumeric($value);

        return $year > 0 ? $year : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeLookupValue(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = strtolower(trim($value));
        $value = str_replace(['ñ', 'Ñ'], 'n', $value);

        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    }
}

<?php

namespace App\Imports;

use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\CenterEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\ProgramEnum;
use App\Models\Partner;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class MilkSales4DXSheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithEvents
{
    private UpsertMilkSaleAction $upsertMilkSaleAction;

    private UpsertPartnerAction $upsertPartnerAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $defaultPartnerIdByCenterId = [];

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    private ?int $defaultYear = null;

    private array $rawHeaders = [];

    private ?int $centerColIndex = null;

    private ?int $nameColIndex = null;

    private ?int $yearColIndex = null;

    private ?int $typeColIndex = null;

    private const IDENTITY_HEADERS = [
        'center', 'name', 'type', 'partner', 'cooperative',
        'center name', 'center_name', 'center code', 'center_code',
        'partner name', 'partner_name', 'partner code', 'partner_code',
        'item', 'total', 'year',
    ];

    public function __construct(
        ?UpsertMilkSaleAction $upsertMilkSaleAction = null,
        ?UpsertPartnerAction $upsertPartnerAction = null
    ) {
        $this->upsertMilkSaleAction = $upsertMilkSaleAction ?? resolve(UpsertMilkSaleAction::class);
        $this->upsertPartnerAction = $upsertPartnerAction ?? resolve(UpsertPartnerAction::class);
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $title = (string) $event->getSheet()->getTitle();

                if (preg_match('/(\d{4})/', $title, $matches)) {
                    $this->defaultYear = (int) $matches[1];
                }

                $this->rawHeaders = [];
                $this->centerColIndex = null;
                $this->nameColIndex = null;
                $this->yearColIndex = null;
                $this->typeColIndex = null;

                $worksheet = $event->getSheet()->getDelegate();
                $highestCol = $worksheet->getHighestColumn();
                $firstRow = $worksheet->rangeToArray(
                    'A1:'.$highestCol.'1',
                    null, true, true, false
                )[0];

                foreach ($firstRow as $colIndex => $headerValue) {
                    $this->rawHeaders[$colIndex] = $headerValue;
                    $normalized = strtolower(trim((string) $headerValue));

                    if ($normalized === 'center') {
                        $this->centerColIndex = $colIndex;
                    } elseif (in_array($normalized, ['name', 'partner', 'cooperative'], true)) {
                        $this->nameColIndex = $colIndex;
                    } elseif ($normalized === 'year') {
                        $this->yearColIndex = $colIndex;
                    } elseif ($normalized === 'type') {
                        $this->typeColIndex = $colIndex;
                    }
                }
            },
        ];
    }

    public function onRow(Row $row): void
    {

        if ($row->getIndex() === 1) {
            return;
        }

        $cells = $row->toArray(null, true, true);
        $rowData = array_values($cells); // 0-based numeric index

        $centerName = $this->centerColIndex !== null ? ($rowData[$this->centerColIndex] ?? null) : null;
        $partnerName = $this->nameColIndex !== null ? ($rowData[$this->nameColIndex] ?? null) : null;
        $rowYear = $this->yearColIndex !== null ? ($rowData[$this->yearColIndex] ?? null) : null;
        $rawType = $this->typeColIndex !== null ? ($rowData[$this->typeColIndex] ?? null) : null;

        $rowYear = $rowYear !== null && $rowYear !== '' ? (int) $rowYear : null;
        $centerName = $centerName !== null ? trim((string) $centerName) : null;
        $partnerName = $partnerName !== null ? trim((string) $partnerName) : null;
        $rawType = $rawType !== null ? trim((string) $rawType) : null;
        $centerName = $centerName === '' ? null : $centerName;
        $partnerName = $partnerName === '' ? null : $partnerName;
        $rawType = $rawType === '' ? null : $rawType;

        if ($centerName && str_starts_with($centerName, '*')) {
            return;
        }

        if (! $centerName && ! $partnerName) {
            return;
        }

        [$centerId, $partnerId] = $this->resolveCenterPartner($centerName, $partnerName, $row->getIndex());

        if (! $centerId) {
            return;
        }

        $program = $this->resolveProgram($rawType) ?? $this->program;

        foreach ($this->rawHeaders as $colIndex => $rawHeader) {
            if ($rawHeader === null || $rawHeader === '') {
                continue;
            }

            $normalizedHeader = strtolower(trim((string) $rawHeader));
            if (in_array($normalizedHeader, self::IDENTITY_HEADERS, true)) {
                continue;
            }

            $value = $rowData[$colIndex] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $resolvedYear = $rowYear ?? $this->defaultYear;

            $weekFromHeader = MilkProductionWeekEnum::tryFroMatches((string) $rawHeader);
            $range = Sanitizer::parseWeekRangeFromHeader((string) $rawHeader, $resolvedYear);

            if (! $range && $resolvedYear !== null && $weekFromHeader) {
                $start = (new \DateTimeImmutable)->setISODate($resolvedYear, $weekFromHeader->value, 1);
                $end = (new \DateTimeImmutable)->setISODate($resolvedYear, $weekFromHeader->value, 7);
                $range = ['start' => $start, 'end' => $end, 'week' => $weekFromHeader->value];
            }

            if (! $range) {
                continue;
            }

            $salesValue = Sanitizer::parseNumeric($value);

            if ($salesValue < 0) {
                continue;
            }

            $transactionDate = (isset($range['week']) && $range['week'])
                ? (new \DateTimeImmutable)->setISODate((int) ($resolvedYear ?? now()->format('Y')), (int) $range['week'], 4)
                : $range['start'];

            $year = (int) $transactionDate->format('Y');
            $month = (int) $transactionDate->format('m');
            $quarter = (int) ceil($month / 3);
            $week = MilkProductionWeekEnum::fromDate($transactionDate);

            try {
                $this->upsertMilkSaleAction->execute(
                    milkProductionId: null,
                    centerId: $centerId,
                    partnerId: $partnerId,
                    farmerId: null,
                    transactionDate: $transactionDate,
                    year: $year,
                    month: $month,
                    quarter: $quarter,
                    week: $week?->value,
                    periodStart: $range['start']?->format('Y-m-d'),
                    periodEnd: $range['end']?->format('Y-m-d'),
                    totalVolumeSold: $salesValue,
                    farmGatePrice: 0.0,
                    rawMilkTradedSales: 0.0,
                    valueAddedProductsSales: $salesValue,
                    dairyBoxSales: 0.0,
                    milkSales: $salesValue,
                    milkFeedingSales: 0.0,
                    salesOfValueAdded: $salesValue,
                    valueOfTotalMilkProduction: $salesValue,
                    program: $program->value
                );
            } catch (\Exception $e) {
                Log::error('MilkSales4DXSheetImport: Error processing row', [
                    'row' => $row->getIndex(),
                    'header' => $rawHeader,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

            if (! $centerId) {
                $centerEnum = CenterEnum::tryFroMatches($centerName);
                if ($centerEnum instanceof CenterEnum) {
                    $centerId = $this->centerIdsByCode[$centerEnum->code()] ?? null;
                }
            }
        }

        $partner = null;
        if ($partnerName && $centerId) {
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

                $this->defaultPartnerIdByCenterId[$centerId] = $partners->count() === 1
                    ? (int) $partners->first()->id
                    : null;
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
            Log::warning('MilkSales4DXSheetImport: Center not resolved', [
                'row' => $rowIndex,
                'center_value' => $centerName,
                'partner_value' => $partnerName,
            ]);
        }

        return [$centerId, $partner?->id];
    }

    private function resolveProgram(?string $rawType): ?ProgramEnum
    {
        if (! $rawType) {
            return null;
        }

        $value = strtolower(trim($rawType));

        return match ($value) {
            'cbed' => ProgramEnum::CBED,
            'ih', 'institutional', 'instutional', 'institution' => ProgramEnum::IH,
            default => null,
        };
    }
}

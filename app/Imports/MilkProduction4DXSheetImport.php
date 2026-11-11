<?php

namespace App\Imports;

use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\CenterEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\ProductionStatusEnum;
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

class MilkProduction4DXSheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithEvents
{
    private UpsertMilkProductionAction $upsertMilkProductionAction;

    private UpsertPartnerAction $upsertPartnerAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $defaultPartnerIdByCenterId = [];

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    private ?int $defaultYear = null;

    // Raw headers keyed by 0-based column index, populated in BeforeSheet
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
        ?UpsertMilkProductionAction $upsertMilkProductionAction = null,
        ?UpsertPartnerAction $upsertPartnerAction = null
    ) {
        $this->upsertMilkProductionAction = $upsertMilkProductionAction ?? resolve(UpsertMilkProductionAction::class);
        $this->upsertPartnerAction = $upsertPartnerAction ?? resolve(UpsertPartnerAction::class);
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setDefaultYear(?int $year): void
    {
        $this->defaultYear = $year;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $title = (string) $event->getSheet()->getTitle();

                if (preg_match('/(\d{4})/', $title, $matches)) {
                    $this->defaultYear = (int) $matches[1];
                }

                // Reset per-sheet state
                $this->rawHeaders = [];
                $this->centerColIndex = null;
                $this->nameColIndex = null;
                $this->yearColIndex = null;
                $this->typeColIndex = null;

                // Read raw header row directly from the worksheet (preserves original casing)
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
        // Skip the header row itself
        if ($row->getIndex() === 1) {
            return;
        }

        // Read cells keyed by column letter (A, B, C...), then re-index to 0-based int
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

        // Skip footer/legend rows
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

            // Pass the ORIGINAL raw header to both parsers — not the normalized version
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

            $totalVolume = Sanitizer::parseNumeric($value);

            if ($totalVolume < 0) {
                continue;
            }

            $transactionDate = (isset($range['week']) && $range['week'])
                ? (new \DateTimeImmutable)->setISODate((int) ($resolvedYear ?? now()->format('Y')), (int) $range['week'], 4)
                : $range['start'];

            $year = (int) $transactionDate->format('Y');
            $month = (int) $transactionDate->format('m');
            $quarter = (int) ceil($month / 3);
            $week = $weekFromHeader ?? MilkProductionWeekEnum::fromDate($transactionDate);

            try {
                $this->upsertMilkProductionAction->execute(
                    productionCode: null,
                    centerId: $centerId,
                    partnerId: $partnerId,
                    farmerId: null,
                    transactionDate: $transactionDate,
                    durationStart: $range['start'] ?? null,
                    durationEnd: $range['end'] ?? null,
                    totalVolume: $totalVolume,
                    volumeFromFarmers: null,
                    program: $program,
                    status: ProductionStatusEnum::DEFAULT,
                    notes: null,
                    year: $year,
                    month: $month,
                    quarter: $quarter,
                    week: $week,
                    idNumber: null,
                    collectionDate: $transactionDate
                );
            } catch (\Exception $e) {
                Log::error('MilkProduction4DXSheetImport: Error processing row', [
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
            Log::warning('MilkProduction4DXSheetImport: Center not resolved', [
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

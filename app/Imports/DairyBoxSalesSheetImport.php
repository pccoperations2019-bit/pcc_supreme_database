<?php

namespace App\Imports;

use App\Actions\DairyBox\UpsertDairyBoxSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Models\DairyBox;
use App\Models\Partner;
use App\Support\Sanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class DairyBoxSalesSheetImport implements OnEachRow, ShouldQueue, SkipsEmptyRows, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
{
    use Queueable;

    private UpsertDairyBoxSaleAction $upsertDairyBoxSaleAction;

    private UpsertPartnerAction $upsertPartnerAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $partnerIdsByName = [];

    private array $dairyBoxIdsByCode = [];

    private array $defaultPartnerIdByCenterId = [];

    private ?int $defaultYear = null;

    private array $rawHeaders = [];

    private ?int $dairyBoxColIndex = null;

    private ?int $centerColIndex = null;

    private ?int $nameColIndex = null;

    private ?int $yearColIndex = null;

    private const IDENTITY_HEADERS = [
        'dairy box id', 'cooperative', 'business name', 'center', 'region', 'code',
        'target annual sales', 'cummulative actual sales', 'percentage accomplishment',
        'year', 'total', 'item',
    ];

    public function __construct(
        ?UpsertDairyBoxSaleAction $upsertDairyBoxSaleAction = null,
        ?UpsertPartnerAction $upsertPartnerAction = null
    ) {
        $this->upsertDairyBoxSaleAction = $upsertDairyBoxSaleAction ?? app(UpsertDairyBoxSaleAction::class);
        $this->upsertPartnerAction = $upsertPartnerAction ?? app(UpsertPartnerAction::class);
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->defaultYear = null;

                $title = (string) $event->getSheet()->getTitle();

                if (preg_match('/(\d{4})/', $title, $matches)) {
                    $this->defaultYear = (int) $matches[1];
                }

                $this->rawHeaders = [];
                $this->dairyBoxColIndex = null;
                $this->centerColIndex = null;
                $this->nameColIndex = null;
                $this->yearColIndex = null;

                $worksheet = $event->getSheet()->getDelegate();
                $highestCol = $worksheet->getHighestColumn();
                $firstRow = $worksheet->rangeToArray(
                    'A1:'.$highestCol.'1',
                    null, true, true, false
                )[0];

                foreach ($firstRow as $colIndex => $headerValue) {
                    $this->rawHeaders[$colIndex] = $headerValue;
                    $normalized = strtolower(trim((string) $headerValue));

                    if ($normalized === 'dairy box id') {
                        $this->dairyBoxColIndex = $colIndex;
                    } elseif ($normalized === 'center') {
                        $this->centerColIndex = $colIndex;
                    } elseif (in_array($normalized, ['name', 'partner', 'cooperative'], true)) {
                        $this->nameColIndex = $colIndex;
                    } elseif ($normalized === 'year') {
                        $this->yearColIndex = $colIndex;
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

        $cells = $row->toArray(null, false, true);
        $rowData = array_values($cells);

        $dairyBoxCode = $this->dairyBoxColIndex !== null ? ($rowData[$this->dairyBoxColIndex] ?? null) : null;
        $centerName = $this->centerColIndex !== null ? ($rowData[$this->centerColIndex] ?? null) : null;
        $partnerName = $this->nameColIndex !== null ? ($rowData[$this->nameColIndex] ?? null) : null;
        $rowYear = $this->yearColIndex !== null ? ($rowData[$this->yearColIndex] ?? null) : null;

        $dairyBoxCode = $dairyBoxCode !== null ? trim((string) $dairyBoxCode) : null;
        $centerName = $centerName !== null ? trim((string) $centerName) : null;
        $partnerName = $partnerName !== null ? trim((string) $partnerName) : null;
        $rowYear = $rowYear !== null && $rowYear !== '' ? (int) $rowYear : null;

        if (in_array($dairyBoxCode, [null, '', '0'], true)) {
            Log::debug('Skipping: empty dairy box id', ['dairyBoxCode' => $dairyBoxCode]);

            return;
        }

        if (str_starts_with($dairyBoxCode, '=')) {
            Log::debug('Skipping: formula reference', ['dairyBoxCode' => $dairyBoxCode]);

            return;
        }

        $dairyBoxId = $this->resolveDairyBoxId($dairyBoxCode);
        if ($dairyBoxId === null) {
            Log::warning('Dairy box not found', ['dairyBoxCode' => $dairyBoxCode]);

            return;
        }

        [$centerId, $partnerId] = $this->resolveCenterPartner($centerName, $partnerName, $row->getIndex());

        if (! $centerId) {
            return;
        }

        $resolvedYear = $rowYear ?? $this->defaultYear;
        $recordsCreated = 0;

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

            $range = Sanitizer::parseMonthRangeFromHeader((string) $rawHeader, $resolvedYear);
            if (! $range) {
                continue;
            }

            try {
                $salesValue = Sanitizer::parseNumeric($value);
                $this->upsertDairyBoxSaleAction->execute(
                    dairyBoxId: $dairyBoxId,
                    centerId: $centerId,
                    partnerId: $partnerId,
                    periodStart: new \DateTime($range['start']->format('Y-m-d')),
                    periodEnd: new \DateTime($range['end']->format('Y-m-d')),
                    totalSales: is_numeric($salesValue) ? $salesValue : null,
                    year: $resolvedYear
                );
                $recordsCreated++;
                Log::debug('DairyBoxSale stored', [
                    'dairy_box_id' => $dairyBoxId,
                    'month' => $rawHeader,
                    'period_start' => $range['start']->format('Y-m-d'),
                    'period_end' => $range['end']->format('Y-m-d'),
                    'sales' => $salesValue,
                ]);
            } catch (\Exception $e) {
                Log::error('Upsert failed', [
                    'error' => $e->getMessage(),
                    'dairyBoxId' => $dairyBoxId,
                    'month' => $rawHeader,
                ]);
            }
        }

        Log::info('DairyBoxSales row processed', [
            'dairy_box_id' => $dairyBoxId,
            'records_created' => $recordsCreated,
        ]);
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
            $partner = Partner::query()->where('center_id', $centerId)
                ->whereRaw('LOWER(name) = ?', [strtolower($partnerName)])
                ->first();

            if (! $partner) {
                $partner = Partner::query()->where('center_id', $centerId)
                    ->where('name', 'LIKE', "%{$partnerName}%")
                    ->first();
            }

            if (! $partner) {
                $partner = Partner::whereRaw('LOWER(name) = ?', [strtolower($partnerName)], 'and')->first();
                if ($partner && $partner->center_id !== $centerId) {
                    $partner->center_id = $centerId;
                    $partner->save();
                }
            }

            if (! $partner) {
                $partner = Partner::query()->where('name', 'LIKE', "%{$partnerName}%")->first();
                if ($partner && $partner->center_id !== $centerId) {
                    $partner->center_id = $centerId;
                    $partner->save();
                }
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
                    status: null,
                    isDairybox: true
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
                $partner = Partner::query()->find($this->defaultPartnerIdByCenterId[$centerId]);
            }
        }

        if (! $centerId && $partnerName) {
            $partner = Partner::whereRaw('LOWER(name) = ?', [strtolower($partnerName)], 'and')->first();
            $centerId = $partner?->center_id;
        }

        if (! $centerId) {
            Log::warning('DairyBoxSalesSheetImport: Center not resolved', [
                'row' => $rowIndex,
                'center_value' => $centerName,
                'partner_value' => $partnerName,
            ]);
        }

        return [$centerId, $partner?->id];
    }

    private function resolveDairyBoxId(string $dairyBoxCode): ?int
    {
        if (isset($this->dairyBoxIdsByCode[$dairyBoxCode])) {
            return $this->dairyBoxIdsByCode[$dairyBoxCode];
        }

        if ($this->dairyBoxIdsByCode === []) {
            $dairyBoxes = DairyBox::select('id', 'dairy_box_id')->get();
            foreach ($dairyBoxes as $box) {
                if ($box->dairy_box_id) {
                    $this->dairyBoxIdsByCode[$box->dairy_box_id] = (int) $box->id;
                }
            }
        }

        return $this->dairyBoxIdsByCode[$dairyBoxCode] ?? null;
    }
}

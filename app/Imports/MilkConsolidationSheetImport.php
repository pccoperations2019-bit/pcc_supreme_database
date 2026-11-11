<?php

namespace App\Imports;

use App\Actions\MilkConsolidation\UpsertMilkConsolidationAction;
use App\Models\Farmer;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class MilkConsolidationSheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithHeadingRow
{
    private int $centerId;

    private int $documentId;

    private ?int $userId = null;

    private static array $partnerCache = [];

    private static array $farmerCache = [];

    // Track missing partners for summary
    private static array $missingPartners = [];

    public function setCenterId(int $centerId): void
    {
        $this->centerId = $centerId;
    }

    public function setDocumentId(int $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function onRow(Row $row)
    {
        try {
            $data = $row->toCollection(calculateFormulas: true);

            $partnerName = $data->get('partner_name');
            $partnerCode = $data->get('partner_code');
            $partnerId = $this->resolvePartner($partnerName, $partnerCode);

            if (! $partnerId && ($partnerName || $partnerCode)) {
                $partnerKey = strtolower(trim(($partnerName ?? '').'|'.($partnerCode ?? '')));

                // Track unique missing partners
                if (! isset(self::$missingPartners[$partnerKey])) {
                    self::$missingPartners[$partnerKey] = [
                        'name' => $partnerName,
                        'code' => $partnerCode,
                        'rows' => [],
                    ];
                }
                self::$missingPartners[$partnerKey]['rows'][] = $row->getIndex();
            }

            // Log partner_code and partner_id for every row
            Log::info("Row {$row->getIndex()}: Partner lookup", [
                'partner_code' => $partnerCode,
                'partner_id' => $partnerId,
            ]);

            $farmerId = $this->resolveFarmer(
                $data->get('farmer_name'),
                $data->get('farm_code')
            );

            $milkConsolidation = resolve(UpsertMilkConsolidationAction::class)->execute(
                centerId: $this->centerId,
                partnerId: $partnerId,
                farmerId: $farmerId,
                supplierName: $data->get('supplier_name'),
                periodStart: $this->convertExcelDate($data->get('period_start')),
                periodEnd: $this->convertExcelDate($data->get('period_end')),
                totalRawMilk: Number::parseFloat($data->get('total_raw_milk_kg', 0) ?? 0),
                totalFromCenter: Number::parseFloat($data->get('total_from_center_kg', 0) ?? 0),
                totalFromPartners: Number::parseFloat($data->get('total_from_partners_kg', 0) ?? 0),
                totalFromFarmers: Number::parseFloat($data->get('total_from_farmers_kg', 0) ?? 0),
                totalFromSuppliers: Number::parseFloat($data->get('total_from_suppliers_kg', 0) ?? 0),
                averageBuyingPrice: ($avgBuyingPrice = Number::parseFloat($data->get('average_buying_price_kg', 0) ?? 0)) ? $avgBuyingPrice : null,
                totalCost: ($totalCost = Number::parseFloat($data->get('total_cost', 0) ?? 0)) ? $totalCost : null,
                averageSellingPrice: ($avgSellingPrice = Number::parseFloat($data->get('average_selling_price_kg', 0) ?? 0)) ? $avgSellingPrice : null,
                revenueGenerated: ($revenue = Number::parseFloat($data->get('revenue_generated', 0) ?? 0)) ? $revenue : null,
                processingCost: ($processingCost = Number::parseFloat($data->get('processing_cost', 0) ?? 0)) ? $processingCost : null,
                metadata: [
                    'source_file' => 'excel_upload',
                    'source_row' => $row->getIndex(),
                    'imported_at' => now()->toIso8601String(),
                    'document_id' => $this->documentId,
                ],
                createdBy: $this->userId,
            );

            if (! $milkConsolidation || ! $milkConsolidation->exists || ! $milkConsolidation->id) {
                throw new \Exception('Failed to create/update milk consolidation record');
            }
        } catch (\Exception $e) {
            // Silently fail - errors can be logged elsewhere if needed
        }
    }

    private function resolvePartner(?string $name, ?string $code): ?int
    {
        if (in_array($name, [null, '', '0'], true) && in_array($code, [null, '', '0'], true)) {
            return null;
        }

        if (! isset(self::$partnerCache[$this->centerId])) {
            $partners = Partner::query()->where('center_id', $this->centerId)->get();
            self::$partnerCache[$this->centerId] = [
                'lookup' => $partners->mapWithKeys(function ($partner) {
                    return [
                        strtolower(trim($partner->name ?? '')) => $partner->id,
                        strtolower(trim($partner->code ?? '')) => $partner->id,
                    ];
                })->toArray(),
                'partners' => $partners->map(function ($partner) {
                    return [
                        'id' => $partner->id,
                        'name' => $partner->name,
                        'code' => $partner->code,
                        'name_normalized' => strtolower(trim($partner->name ?? '')),
                        'code_normalized' => strtolower(trim($partner->code ?? '')),
                    ];
                })->toArray(),
            ];
        }

        $cache = self::$partnerCache[$this->centerId];
        $nameKey = strtolower(trim($name ?? ''));
        $codeKey = strtolower(trim($code ?? ''));

        if ($nameKey !== '' && $nameKey !== '0' && isset($cache['lookup'][$nameKey])) {
            return $cache['lookup'][$nameKey];
        }

        if ($codeKey !== '' && $codeKey !== '0' && isset($cache['lookup'][$codeKey])) {
            return $cache['lookup'][$codeKey];
        }

        return null;
    }

    private function resolveFarmer(?string $name, ?string $code): ?int
    {
        if (in_array($name, [null, '', '0'], true) && in_array($code, [null, '', '0'], true)) {
            return null;
        }

        if (! isset(self::$farmerCache[$this->centerId])) {
            self::$farmerCache[$this->centerId] = Farmer::query()->where('center_id', $this->centerId)
                ->get()
                ->mapWithKeys(function ($farmer) {
                    $fullName = trim(($farmer->first_name ?? '').' '.($farmer->last_name ?? ''));

                    return [
                        strtolower(trim($fullName)) => $farmer->id,
                        strtolower(trim($farmer->code ?? '')) => $farmer->id,
                    ];
                })
                ->toArray();
        }

        $cache = self::$farmerCache[$this->centerId];
        $nameKey = strtolower(trim($name ?? ''));
        $codeKey = strtolower(trim($code ?? ''));

        if ($nameKey !== '' && $nameKey !== '0' && isset($cache[$nameKey])) {
            return $cache[$nameKey];
        }

        if ($codeKey !== '' && $codeKey !== '0' && isset($cache[$codeKey])) {
            return $cache[$codeKey];
        }

        return null;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headingRow(): int
    {
        return 2;
    }

    /**
     * @todo put this in trait name ExcelDateConverter
     */
    private function convertExcelDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value));
        }

        try {
            return Date::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}

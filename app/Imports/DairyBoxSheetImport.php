<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\DairyBox\UpsertDairyBoxAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\AddressCategoryEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\DairyBoxEnum;
use App\Enums\EmptyValueEnum;
use App\Enums\LandOwnershipEnum;
use App\Models\Partner;
use App\Support\Sanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class DairyBoxSheetImport implements OnEachRow, ShouldQueue, SkipsEmptyRows, WithBatchInserts, WithCalculatedFormulas, WithChunkReading, WithHeadingRow
{
    use Queueable;

    public function __construct(
        private readonly UpsertDairyBoxAction $upsertDairyBoxAction,
        private readonly UpsertAddressAction $upsertAddressAction,
        private readonly UpsertPartnerAction $upsertPartnerAction,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 50;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if (empty($value)) {
            return true;
        }

        $emptyValues = array_values(EmptyValueEnum::labels());

        return in_array($value, $emptyValues, true);
    }

    private function isValidLatitude(mixed $value): bool
    {
        if (empty($value) || $this->isEmptyValue($value) || ! is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;

        if (strpos((string) $value, '.') === false && strlen((string) $value) >= 10) {
            Log::warning("Latitude value looks like phone number, rejecting: {$value}");

            return false;
        }

        $isValid = $numericValue >= -90 && $numericValue <= 90;

        if (! $isValid) {
            Log::warning("Invalid latitude value detected: {$value}");
        }

        return $isValid;
    }

    private function isValidLongitude(mixed $value): bool
    {
        if (empty($value) || $this->isEmptyValue($value) || ! is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;

        if (strpos((string) $value, '.') === false && strlen((string) $value) >= 10) {
            Log::warning("Longitude value looks like phone number, rejecting: {$value}");

            return false;
        }

        $isValid = $numericValue >= -180 && $numericValue <= 180;

        if (! $isValid) {
            Log::warning("Invalid longitude value detected: {$value}");
        }

        return $isValid;
    }

    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = Str::of((string) $key)->trim()->snake()->lower()->toString();
            if ($normalizedKey === '') {
                continue;
            }
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    private function extractFieldValues(array $row): array
    {
        $fields = [
            'kadiwa_integration',
            'milk_feeding_engagement',
            'bir_registered',
            'fda_lto',
            'cso_accreditation',
            'philgeps',
            'ffedis',
            'fund_source_outlet',
            'fund_source_equipment',
            'fund_source_processing_plant',
            'land_ownership',
            'start_of_construction',
            'date_of_completion',
            'contractor',
            'date_inaugurated',
        ];

        $values = [];

        foreach ($fields as $field) {
            $value = $row[$field] ?? null;

            if ($value === null) {
                // Try original Excel headers with spaces
                $originalHeaders = [
                    'kadiwa_integration' => 'KADIWA Integration',
                    'milk_feeding_engagement' => 'Milk Feeding Engagement',
                    'bir_registered' => 'BIR Registered',
                    'fda_lto' => 'FDA LTO',
                    'cso_accreditation' => 'CSO Accreditation',
                    'philgeps' => 'PhilGeps',
                    'ffedis' => 'FFEDIS',
                    'fund_source_outlet' => 'Fund Source: Outlet',
                    'fund_source_equipment' => 'Fund Source: Equipment',
                    'fund_source_processing_plant' => 'Fund Source: Processing Plant',
                    'land_ownership' => 'Land Ownership',
                    'start_of_construction' => 'Start of Construction',
                    'date_of_completion' => 'Date of Completion',
                    'contractor' => 'Contractor',
                    'date_inaugurated' => 'Date Inaugurated',
                ];

                $value = $row[$originalHeaders[$field]] ?? null;
            }

            $values[$field] = ($value === null || $this->isEmptyValue($value)) ? null : $value;
        }

        // Canonicalise land ownership against the enum the form offers, so a sheet
        // saying "Local Government Unit" stores 'lgu' and shows as that choice.
        // Anything the list does not carry is left as typed: the column accepts
        // free text, and the form presents an unmatched value under "Other".
        if ($values['land_ownership'] !== null) {
            $normalized = LandOwnershipEnum::tryFroMatches($values['land_ownership'])
                ?? LandOwnershipEnum::fromLabel($values['land_ownership'])
                ?? LandOwnershipEnum::tryFrom($values['land_ownership']);
            if ($normalized !== null) {
                $values['land_ownership'] = $normalized->value;
            }
        }

        // Normalize compliance status values using ComplianceStatusEnum
        $complianceFields = [
            'kadiwa_integration',
            'milk_feeding_engagement',
            'bir_registered',
            'fda_lto',
            'cso_accreditation',
            'philgeps',
            'ffedis',
        ];

        // Parse date fields
        $dateFields = [
            'start_of_construction',
            'date_of_completion',
            'date_inaugurated',
        ];
        foreach ($dateFields as $field) {
            if ($values[$field] !== null) {
                $values[$field] = $this->parseDate($values[$field]);
            }
        }
        foreach ($complianceFields as $field) {
            if ($values[$field] !== null) {
                $normalized = ComplianceStatusEnum::tryFroMatches($values[$field])
                    ?? ComplianceStatusEnum::fromLabel($values[$field])
                    ?? ComplianceStatusEnum::tryFrom($values[$field]);
                if ($normalized !== null) {
                    $values[$field] = $normalized->value;
                }
            }
        }

        return $values;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        $row->getIndex();
        $row = $row->toArray(calculateFormulas: true);
        $normalizedRow = $this->normalizeRowKeys($row);

        $dairyBoxId = Sanitizer::parseString($row['dairy_box_id'] ?? $normalizedRow['dairy_box_id'] ?? $row['Dairy Box ID'] ?? null);
        $businessName = Sanitizer::parseString($row['business_name'] ?? $normalizedRow['business_name'] ?? $row['Business Name'] ?? null);

        if ($this->isEmptyValue($dairyBoxId)) {
            Log::warning('Skipping row: Dairy Box ID is empty');

            return;
        }

        $centerName = Sanitizer::parseString($row['center'] ?? $normalizedRow['center'] ?? $row['Center'] ?? null);
        $partnerName = Sanitizer::parseString($row['cooperative'] ?? $normalizedRow['cooperative'] ?? $row['Cooperative'] ?? null);
        $partnerCode = Sanitizer::parseString($row['code'] ?? $normalizedRow['code'] ?? null);

        [$centerId, $partnerId] = $this->resolveCenterPartner($centerName, $partnerName, $partnerCode);

        $fieldValues = $this->extractFieldValues($row + $normalizedRow);

        $statusValue = $row['operational_status'] ?? $normalizedRow['operational_status'] ?? $row['Operational Status'] ?? null;
        $status = $this->isEmptyValue($statusValue) ? null : $this->parseStatus($statusValue);

        $dairyBox = $this->upsertDairyBoxAction->execute(
            dairyBoxId: $dairyBoxId,
            businessName: $businessName,
            manager: $this->isEmptyValue($row['manager'] ?? $normalizedRow['manager'] ?? $row['Manager'] ?? null) ? null : $row['manager'] ?? $normalizedRow['manager'] ?? $row['Manager'] ?? null,
            dairyBoxFocal: $this->isEmptyValue($row['dairy_box_focal'] ?? $normalizedRow['dairy_box_focal'] ?? $row['Dairy Box Focal'] ?? null) ? null : $row['dairy_box_focal'] ?? $normalizedRow['dairy_box_focal'] ?? $row['Dairy Box Focal'] ?? null,
            contactNumber: $this->isEmptyValue($row['contact_number'] ?? $normalizedRow['contact_number'] ?? $row['Contact Number'] ?? null) ? null : $row['contact_number'] ?? $normalizedRow['contact_number'] ?? $row['Contact Number'] ?? null,
            centerId: $centerId,
            partnerId: $partnerId,
            status: $status,
            launchedDate: $this->parseDate($row['launched_date'] ?? $normalizedRow['launched_date'] ?? $row['Launched Date'] ?? null),
            kadiwaIntegration: $fieldValues['kadiwa_integration'],
            milkFeedingEngagement: $fieldValues['milk_feeding_engagement'],
            birRegistered: $fieldValues['bir_registered'],
            fdaLto: $fieldValues['fda_lto'],
            csoAccreditation: $fieldValues['cso_accreditation'],
            philgeps: $fieldValues['philgeps'],
            ffedis: $fieldValues['ffedis'],
            fundSourceOutlet: $fieldValues['fund_source_outlet'],
            fundSourceEquipment: $fieldValues['fund_source_equipment'],
            fundSourceProcessingPlant: $fieldValues['fund_source_processing_plant'],
            landOwnership: $fieldValues['land_ownership'],
            remarks: $this->isEmptyValue($row['remarks'] ?? $normalizedRow['remarks'] ?? null)
                ? null
                : $row['remarks'] ?? $normalizedRow['remarks'] ?? null,
            startOfConstruction: $fieldValues['start_of_construction'],
            dateOfCompletion: $fieldValues['date_of_completion'],
            contractor: $fieldValues['contractor'],
            dateInaugurated: $fieldValues['date_inaugurated'],
            metadata: null
        );

        if (! $dairyBox) {
            Log::error('Failed to create dairy box: '.$dairyBoxId);

            return;
        }

        Log::info("Successfully created/updated dairy box: {$dairyBox->business_name} (ID: {$dairyBox->id})");

        $this->upsertAddressAction->execute(
            addressableType: $dairyBox->getMorphClass(),
            addressableId: $dairyBox->id,
            street: $this->isEmptyValue($normalizedRow['street'] ?? null) ? null : $normalizedRow['street'] ?? null,
            barangay: $this->isEmptyValue($normalizedRow['barangay'] ?? null) ? null : $normalizedRow['barangay'] ?? null,
            city: $this->isEmptyValue($normalizedRow['municipality'] ?? $normalizedRow['city'] ?? null) ? null : $normalizedRow['municipality'] ?? $normalizedRow['city'] ?? null,
            province: $this->isEmptyValue($normalizedRow['province'] ?? null) ? null : $normalizedRow['province'] ?? null,
            region: $this->normalizeRegion($normalizedRow['region'] ?? null),
            district: $this->isEmptyValue($normalizedRow['district'] ?? null) ? null : $normalizedRow['district'] ?? null,
            zipCode: isset($normalizedRow['zipcode']) && is_numeric($normalizedRow['zipcode']) && Sanitizer::parseNumeric($normalizedRow['zipcode']) !== 0.0
                ? (int) Sanitizer::parseNumeric($normalizedRow['zipcode']) : (isset($normalizedRow['zip_code']) && is_numeric($normalizedRow['zip_code']) && Sanitizer::parseNumeric($normalizedRow['zip_code']) !== 0.0
                    ? (int) Sanitizer::parseNumeric($normalizedRow['zip_code']) : null),
            latitude: $this->isValidLatitude($normalizedRow['latitude'] ?? null) ? (float) ($normalizedRow['latitude'] ?? null) : null,
            longitude: $this->isValidLongitude($normalizedRow['longitude'] ?? null) ? (float) ($normalizedRow['longitude'] ?? null) : null,
            markerIcon: null,
            category: AddressCategoryEnum::OFFICE->value,
            centerId: $dairyBox->center_id
        );
    }

    private function normalizeRegion(mixed $region): ?string
    {
        if ($this->isEmptyValue($region)) {
            return null;
        }

        $region = trim((string) $region);
        $normalized = AddressRegionEnum::fromPsgcCode($region)
            ?? AddressRegionEnum::tryFroMatches($region)
            ?? AddressRegionEnum::fromLabel($region)
            ?? AddressRegionEnum::tryFrom($region);

        return $normalized?->value ?? $region;
    }

    private function resolveCenterPartner(?string $centerName, ?string $partnerName, ?string $partnerCode = null): array
    {
        static $centerIdsByCode = [];
        static $centerIdsByName = [];
        static $partnerIdsByName = [];
        static $partnerIdsByCode = [];

        if (! $centerIdsByCode && ! $centerIdsByName) {
            $centers = DB::table('centers')->select('id', 'code', 'name')->get();
            foreach ($centers as $center) {
                if (! empty($center->code)) {
                    $centerIdsByCode[strtoupper(trim((string) $center->code))] = (int) $center->id;
                }
                if (! empty($center->name)) {
                    $centerIdsByName[strtolower(trim((string) $center->name))] = (int) $center->id;
                }
            }
        }

        $centerId = null;
        if ($centerName) {
            $centerId = $centerIdsByCode[strtoupper(trim($centerName))]
                ?? $centerIdsByName[strtolower(trim($centerName))]
                ?? null;
        }

        $partnerId = null;
        if ($partnerName && $centerId) {

            $normalizedCode = $partnerCode ? strtoupper(trim($partnerCode)) : null;
            if ($normalizedCode && $normalizedCode !== 'N/A') {

                $cacheKey = "{$centerId}:{$normalizedCode}";
                if (isset($partnerIdsByCode[$cacheKey])) {
                    $partnerId = $partnerIdsByCode[$cacheKey];
                } else {

                    $partner = Partner::query()->where('code', $normalizedCode)->first();

                    if (! $partner) {

                        $partner = $this->upsertPartnerAction->execute(
                            code: $normalizedCode,
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

                    $partnerId = $partner->id;
                    $partnerIdsByCode[$cacheKey] = $partnerId;
                    $partnerIdsByName[$partnerName] = $partnerId;
                }
            } elseif (isset($partnerIdsByName[$partnerName])) {

                $partnerId = $partnerIdsByName[$partnerName];
            } else {
                $partner = Partner::query()->where('center_id', $centerId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($partnerName)])
                    ->first();

                if (! $partner) {
                    $partner = Partner::query()->where('center_id', $centerId)
                        ->where('name', 'LIKE', "%{$partnerName}%")
                        ->first();
                }

                if (! $partner) {
                    // Create partner without code
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

                $partnerId = $partner?->id;
                if ($partnerId) {
                    $partnerIdsByName[$partnerName] = $partnerId;
                }
            }
        }

        return [$centerId, $partnerId];
    }

    private function parseStatus(mixed $statusValue): ?string
    {
        if (empty($statusValue)) {
            return null;
        }

        // Try to convert using tryFromMatches first, then fromLabel, then tryFrom
        $enum = DairyBoxEnum::tryFroMatches($statusValue)
            ?? DairyBoxEnum::fromLabel($statusValue)
            ?? DairyBoxEnum::tryFrom($statusValue);

        return $enum?->value;
    }

    private function parseDate(mixed $dateInput): ?\DateTime
    {
        if (empty($dateInput)) {
            return null;
        }

        try {

            if (is_numeric($dateInput)) {
                return \DateTime::createFromFormat('U', ($dateInput - 25569) * 86400);
            }

            return new \DateTime($dateInput);
        } catch (\Exception $e) {
            return null;
        }
    }
}

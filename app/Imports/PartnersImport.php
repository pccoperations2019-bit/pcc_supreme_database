<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Document\CreateDocumentAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\AddressCategoryEnum;
use App\Enums\ConsolidationCategoryEnum;
use App\Enums\DocumentEnum;
use App\Enums\EmptyValueEnum;
use App\Enums\PartnerCategoryEnum;
use App\Enums\PartnerPartnershipTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Models\Document;
use App\Models\Partner;
use App\Models\Tag;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class PartnersImport implements OnEachRow, SkipsEmptyRows, WithBatchInserts, WithCalculatedFormulas, WithChunkReading, WithHeadingRow
{
    public function __construct(
        private int $centerId,
        private readonly UpsertPartnerAction $upsertPartnerAction,
        private readonly UpsertAddressAction $upsertAddressAction,
        private readonly CreateDocumentAction $createDocumentAction,
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

    private function parseNullableBooleanFromRow(array $row, string $key): ?bool
    {
        $value = $row[$key] ?? null;
        if ($value === null || $this->isEmptyValue($value)) {
            return null;
        }

        return Sanitizer::parseBooleanNullable($value);
    }

    private function isValidLatitude(mixed $value): bool
    {
        if (empty($value) || $this->isEmptyValue($value) || ! is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;

        // Check if it looks like a phone number (starts with country codes and has no decimal)
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

        // Check if it looks like a phone number (starts with country codes and has no decimal)
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

    private function getPartnerCodeFromRow(array $row): ?string
    {
        $candidates = [
            $row['partner_code'] ?? null,
            $row['partnercode'] ?? null,
            $row['code'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $this->isEmptyValue($candidate)) {
                continue;
            }

            $value = strtoupper(trim((string) $candidate));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function getCategoryFromRow(array $row): ?PartnerCategoryEnum
    {
        $raw = $row['category'] ?? $row['partner_type'] ?? $row['partner_businesstype'] ?? $row['PARTNER BUSINESSTYPE'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value !== '' ? PartnerCategoryEnum::tryFroMatches($value) : null;
    }

    private function getPartnershipTypeFromRow(array $row): ?PartnerPartnershipTypeEnum
    {
        $raw = $row['partnership_type'] ?? $row['partner_type'] ?? $row['PARTNERSHIP TYPE'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value !== '' ? PartnerPartnershipTypeEnum::tryFroMatches($value) : null;
    }

    private function getStatusFromRow(array $row): ?string
    {
        $raw = $row['status'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $normalized = strtolower(trim((string) $raw));
        $normalized = preg_replace('/[^a-z]+/', '', $normalized) ?? $normalized;

        return match ($normalized) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => null,
        };
    }

    private function extractExtraMetadata(array $row): ?array
    {
        $keysToCapture = [
            'documents', 'roles_in_value_chain',
            'center_code', 'center_name', 'date_of_enrollment', 'partner_type',
            'incharge', 'business_name', 'business_shortname', 'assistance_status',
            'date_established', 'date_assisted', 'partner_businesstype',
            'registrationtype', 'registration_no', 'registration_date',
            'name_of_chairpersonceoownerincharge', 'contact_no', 'name_of_manager',
            'manager_contact_no', 'manager_email', 'contact_person',
            'contact_person_contact_no', 'contact_person_email',
            'bodcount', 'bodschedule', 'total_member_count', 'active_count',
            'main_business', 'other_line_of_business', 'businesscount',
            'status_google_map', 'geo_tag_image', 'google_map_link',
            'gps_latitude', 'gps_longitude', 'business_logo',
            'ndaltocertificationstatus', 'ndaltocertno', 'ndaltocertno_validity',
            'fdaltocertificationstatus', 'fdaltocertno', 'fdaltocertno_validity',
            'philgepsstatus', 'philgepsstatus_validity', 'csocertificationstatus',
            'cso_validity', 'ffedisstatus', 'ffedis_certno',
            'assets', 'asset_php', 'asset_year', 'netincome_php', 'income_year',
            'function_in_the_value_chain', 'production', 'processing', 'marketing',
            'engagedmilkfeeding',
        ];

        $metadata = [];

        foreach ($keysToCapture as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value === null || $this->isEmptyValue($value)) {
                continue;
            }
            $metadata[$key] = $value;
        }

        foreach ($row as $key => $value) {
            if ($value === null || $this->isEmptyValue($value)) {
                continue;
            }
            $keyString = strtolower((string) $key);
            if (str_starts_with($keyString, 'certification') || str_starts_with($keyString, 'business') || str_starts_with($keyString, 'financial')) {
                $metadata[$keyString] = $value;
            }
        }

        return $metadata === [] ? null : $metadata;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        $row->getIndex();
        $row = $row->toArray(calculateFormulas: true);

        $partnerCode = $this->getPartnerCodeFromRow($row);
        $partnerName = $row['partner_name'] ?? $row['name'] ?? null;

        if ($this->isEmptyValue($partnerName)) {
            Log::warning('Skipping row: partner name is empty');

            return;
        }

        $categoryEnum = $this->getCategoryFromRow($row);
        $partnershipTypeEnum = $this->getPartnershipTypeFromRow($row);
        $status = $this->getStatusFromRow($row);
        $metadata = $this->extractExtraMetadata($row);

        $partner = $this->upsertPartnerAction->execute(
            code: $partnerCode,
            centerId: $this->centerId,
            name: $partnerName,
            category: $categoryEnum ?: PartnerCategoryEnum::COOPERATIVE,
            partnershipType: $partnershipTypeEnum,
            website: $this->isEmptyValue($row['website'] ?? $row['wesbite'] ?? null) ? null : $row['website'] ?? $row['wesbite'] ?? null,
            logo: $this->isEmptyValue($row['logo'] ?? $row['business_logo'] ?? null) ? null : $row['logo'] ?? $row['business_logo'] ?? null,
            description: $this->isEmptyValue($row['description'] ?? null) ? null : $row['description'] ?? null,
            contactName: $this->isEmptyValue($row['contact_name'] ?? $row['contact_person'] ?? $row['name_of_chairpersonceoownerincharge'] ?? null) ? null : $row['contact_name'] ?? $row['contact_person'] ?? $row['name_of_chairpersonceoownerincharge'] ?? null,
            contactPosition: $this->isEmptyValue($row['position'] ?? null) ? null : $row['position'] ?? null,
            contactEmail: $this->isEmptyValue($row['contact_email'] ?? $row['email'] ?? null) ? null : $row['contact_email'] ?? $row['email'] ?? null,
            contactPhone: $this->isEmptyValue($row['mobile_number'] ?? $row['phone'] ?? $row['contact_no'] ?? null) ? null : $row['mobile_number'] ?? $row['phone'] ?? $row['contact_no'] ?? null,
            telephoneNumber: $this->isEmptyValue($row['telephone_number'] ?? null) ? null : $row['telephone_number'] ?? null,
            status: $status,
            totalComposition: isset($row['total_composition']) && is_numeric($row['total_composition']) && ! $this->isEmptyValue($row['total_composition'])
                ? (int) $row['total_composition'] : (isset($row['total_member_count']) && is_numeric($row['total_member_count']) && ! $this->isEmptyValue($row['total_member_count'])
                ? (int) $row['total_member_count'] : null),
            maleCount: isset($row['male_count']) && is_numeric($row['male_count']) && ! $this->isEmptyValue($row['male_count'])
                ? (int) $row['male_count'] : null,
            femaleCount: isset($row['female_count']) && is_numeric($row['female_count']) && ! $this->isEmptyValue($row['female_count'])
                ? (int) $row['female_count'] : null,
            avgMonthlyIncome: isset($row['avg_monthly_income']) && is_numeric($row['avg_monthly_income']) && ! $this->isEmptyValue($row['avg_monthly_income'])
                ? (float) $row['avg_monthly_income'] : (isset($row['netincome_php']) && is_numeric($row['netincome_php']) && ! $this->isEmptyValue($row['netincome_php'])
                ? (float) $row['netincome_php'] : null),
            totalFarmers: isset($row['total_farmers']) && is_numeric($row['total_farmers']) && ! $this->isEmptyValue($row['total_farmers'])
                ? (int) $row['total_farmers'] : (isset($row['active_count']) && is_numeric($row['active_count']) && ! $this->isEmptyValue($row['active_count'])
                ? (int) $row['active_count'] : null),
            isProcessingPartner: $this->parseNullableBooleanFromRow($row, 'is_processing_partner'),
            isMarketingPartner: $this->parseNullableBooleanFromRow($row, 'is_marketing_partner'),
            isDairybox: $this->parseNullableBooleanFromRow($row, 'is_dairybox'),
            isKnpoPartner: $this->parseNullableBooleanFromRow($row, 'is_knpo_partner'),
            isFcaPartner: $this->parseNullableBooleanFromRow($row, 'is_fca_partner'),
            hasCso: $this->parseNullableBooleanFromRow($row, 'has_cso'),
            hasFdaLto: $this->parseNullableBooleanFromRow($row, 'has_fda_lto'),
            hasNdaLto: $this->parseNullableBooleanFromRow($row, 'has_nda_lto'),
            hasPhilgeps: $this->parseNullableBooleanFromRow($row, 'has_philgeps'),
            hasRsbsa: $this->parseNullableBooleanFromRow($row, 'has_rsbsa'),
            hasFfedis: $this->parseNullableBooleanFromRow($row, 'has_ffedis'),
            additionalData: $metadata ?? []
        );

        if (! $partner) {
            Log::error('Failed to create partner: '.($partnerCode ?? 'no code'));

            return;
        }

        Log::info("Successfully created/updated partner: {$partner->name} (ID: {$partner->id})");

        $this->upsertAddressAction->execute(
            addressableType: Str::of(class_basename(Partner::class))->lower(),
            addressableId: $partner->id,
            street: $this->isEmptyValue($row['street']) ? null : $row['street'],
            barangay: $this->isEmptyValue($row['barangay']) ? null : $row['barangay'],
            city: $this->isEmptyValue($row['municipality']) ? null : $row['municipality'],
            province: $this->isEmptyValue($row['province']) ? null : $row['province'],
            region: $this->isEmptyValue($row['region']) ? null : $row['region'],
            district: $this->isEmptyValue($row['district']) ? null : $row['district'],
            zipCode: isset($row['zipcode']) && is_numeric($row['zipcode']) && Sanitizer::parseNumeric($row['zipcode']) !== 0.0
                ? (int) Sanitizer::parseNumeric($row['zipcode']) : null,
            latitude: $this->isValidLatitude($row['latitude'] ?? $row['gps_latitude'] ?? null) ? (float) ($row['latitude'] ?? $row['gps_latitude'] ?? null) : null,
            longitude: $this->isValidLongitude($row['longitude'] ?? $row['gps_longitude'] ?? null) ? (float) ($row['longitude'] ?? $row['gps_longitude'] ?? null) : null,
            markerIcon: null,
            category: AddressCategoryEnum::HOME->value,
            centerId: $this->centerId
        );

        if (isset($row['documents']) && env('DEMO_MODE')) {
            $documents = explode(',', $row['documents']);
            foreach ($documents as $document) {
                $documentEnum = DocumentEnum::fromLabel($document);
                $fileName = $documentEnum->value.'-demo'.'.pdf';
                $document = $this->createDocumentAction->execute(
                    title: $document." {$partnerCode} ".'demo',
                    fileName: $fileName,
                    fileKey: 'presets/'.$fileName,
                    fileType: 'application/pdf',
                    fileSize: 1000,
                    uploadedBy: 1,
                );
                Tag::updateOrCreate(
                    [
                        'key' => Str::of(class_basename(Partner::class))->lower(),
                        'value' => $partner->id,
                        'taggable_type' => Str::of(class_basename(Document::class))->lower(),
                        'taggable_id' => $document->id,
                    ],
                    []
                );
            }
        }

        $rolesField = $row['roles_in_value_chain'] ?? $row['function_in_the_value_chain'] ?? $row['roles'] ?? $row['value_chain'] ?? $row['chain_roles'] ?? null;

        if (! $this->isEmptyValue($rolesField)) {
            $roles = array_map('trim', explode(',', $rolesField));

            foreach ($roles as $role) {
                $role = strtolower(trim($role));

                try {
                    switch ($role) {
                        case 'production':
                        case 'producer':
                            $partner->productionUnits()->create([
                                'name' => $partner->name.' - Production Unit',
                                'center_id' => $this->centerId,
                                'category' => ProductionCategoryEnum::DEFAULT,
                            ]);
                            break;

                        case 'consolidation':
                        case 'consolidator':
                        case 'consolidaton':
                            $partner->consolidationUnits()->create([
                                'name' => $partner->name.' - Consolidation Unit',
                                'center_id' => $this->centerId,
                                'category' => ConsolidationCategoryEnum::DEFAULT,
                            ]);
                            break;

                        case 'processing':
                        case 'processor':
                            $partner->processingUnits()->create([
                                'name' => $partner->name.' - Processing Unit',
                                'center_id' => $this->centerId,
                                'category' => ProcessingCategoryEnum::DEFAULT,
                            ]);
                            break;

                        case 'marketing':
                        case 'marketer':
                            $partner->marketingUnits()->create([
                                'name' => $partner->name.' - Marketing Unit',
                                'center_id' => $this->centerId,
                            ]);
                            break;

                        default:
                            Log::warning("Unknown value chain role: {$role} for partner: {$partner->name}");
                            break;
                    }
                } catch (\Exception $e) {
                    Log::error("Error creating unit for role {$role}: ".$e->getMessage());
                    Log::error('Error details: '.json_encode([
                        'partner_id' => $partner->id,
                        'center_id' => $this->centerId,
                        'role' => $role,
                        'trace' => $e->getTraceAsString(),
                    ]));
                }
            }
        }
    }
}

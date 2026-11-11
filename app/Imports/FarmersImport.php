<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Document\CreateDocumentAction;
use App\Actions\Farmer\UpsertFarmerAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\AddressCategoryEnum;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\CivilStatusEnum;
use App\Enums\DocumentEnum;
use App\Enums\EducationalAttainmentEnum;
use App\Enums\EmptyValueEnum;
use App\Enums\EntrepreneurTypeEnum;
use App\Enums\IncomeSourceEnum;
use App\Enums\ManagementSystemEnum;
use App\Enums\ProjectProgramEnum;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use App\Models\Tag;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class FarmersImport implements OnEachRow, SkipsEmptyRows, WithBatchInserts, WithCalculatedFormulas, WithChunkReading, WithHeadingRow
{
    public function __construct(
        private int $centerId,
        private readonly UpsertFarmerAction $upsertFarmerAction,
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

    private function parseListFromRow(mixed $value): ?array
    {
        if ($value === null || $this->isEmptyValue($value)) {
            return null;
        }

        $parts = preg_split('/[,;]/', (string) $value);

        return array_values(array_filter(array_map('trim', $parts ?: []), fn ($item) => $item !== ''));
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

    private function getLongitudeFromRow(array $row): mixed
    {
        return $row['gps_longitude'] ?? $row['gps_longtitude'] ?? null;
    }

    private function getFarmerCodeFromRow(array $row): ?string
    {
        $candidates = [
            $row['farmer_id'] ?? null,
            $row['farmerid'] ?? null,
            $row['farmer_code'] ?? null,
            $row['code'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $this->isEmptyValue($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function getPartnerNameFromRow(array $row): ?string
    {
        $candidates = [
            $row['partner_name'] ?? null,
            $row['partner'] ?? null,
            $row['partnername'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $this->isEmptyValue($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function getPartnerCodeFromRow(array $row): ?string
    {
        $candidates = [
            $row['partner_code'] ?? null,
            $row['partnercode'] ?? null,
            $row['partner_id'] ?? null,
            $row['partnerid'] ?? null,
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

    private function resolvePartnerId(array $row): ?int
    {
        $partnerCode = $this->getPartnerCodeFromRow($row);
        $partnerName = $this->getPartnerNameFromRow($row);

        if ($partnerCode === null && $partnerName === null) {
            return null;
        }

        // When partner code is available, delegate to UpsertPartnerAction which
        // does updateOrCreate by code and also corrects the partner name if needed
        if ($partnerCode !== null) {
            $partner = $this->upsertPartnerAction->execute(
                code: $partnerCode,
                centerId: $this->centerId,
                name: $partnerName ?? $partnerCode,
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
                additionalData: []
            );

            return $partner->id;
        }

        // Only partner name available: try fuzzy name matching first
        if ($partnerName !== null) {
            $cleanPartnerName = Sanitizer::cleanPartnerNameForMatching($partnerName);

            $existingPartners = Partner::query()->where('center_id', $this->centerId)->get();

            foreach ($existingPartners as $existingPartner) {
                $cleanExistingName = Sanitizer::cleanPartnerNameForMatching($existingPartner->name);

                if (Sanitizer::areNamesSimilar($cleanPartnerName, $cleanExistingName)) {
                    return $existingPartner->id;
                }
            }

            // Name not found by fuzzy match: create via UpsertPartnerAction
            $partner = $this->upsertPartnerAction->execute(
                code: null,
                centerId: $this->centerId,
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
                additionalData: []
            );

            return $partner->id;
        }

        return null;
    }

    private function extractExtraMetadata(array $row): ?array
    {
        $keysToCapture = [
            'roofmadeof',
            'housemadeof',
            'isaccesstoelectricity',
            'isdugwell',
            'isownwaterline',
            'issharedwaterline',
            'isowndeepwell',
            'isshareddeepwell',
            'isothers',
            'otherremarks',
            'isownrefrigerator',
            'isownwashingmachine',
            'isowntelevision',
            'isownradio',
            'isdryforage',
            'isdrysold',
            'isdryneighbors',
            'isdryothers',
            'dryotherremarks',
            'isseptictank',
            'isburnedforfuel',
            'issolidstoragecovered',
            'issolidstorage',
            'isuncoveredlagoon',
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
            if (str_starts_with($keyString, 'israiny') || str_starts_with($keyString, 'isdry')) {
                $metadata[$keyString] = $value;
            }
        }

        foreach (['forage', 'crop'] as $group) {
            $groupData = $this->extractFarmGroupFromRow($row, $group);
            if ($groupData !== null) {
                $metadata[$group] = $groupData;
            }
        }

        $shedData = $this->extractFarmGroupFromRow($row, 'animal_shed');
        if ($shedData !== null) {
            $metadata['animal_shed'] = $shedData;
        }

        return $metadata === [] ? null : $metadata;
    }

    private function extractFarmGroupFromRow(array $row, string $group): ?array
    {
        $isShed = $group === 'animal_shed';
        $prefix = $isShed ? 'shed' : $group;

        $mapping = $isShed ? [
            'available' => 'shed_available',
            'roof' => 'shed_roof',
            'posts' => 'shed_posts',
            'flooring' => 'shed_flooring',
            'ownership' => 'shed_ownership',
            'water_source' => 'shed_water_source',
            'total_area' => 'shed_total_area',
            'distance_from_house' => 'shed_distance',
        ] : [
            'total_area' => $prefix.'_total_area',
            'distance_from_house' => $prefix.'_distance',
            'forages_grown' => 'forages_grown',
            'crops_grown' => 'crops_grown',
            'ownership' => $prefix.'_ownership',
            'farm_type' => $prefix.'_farm_type',
            'water_source' => $prefix.'_water_source',
            'forages_grown_others' => 'forages_grown_others',
            'crops_grown_others' => 'crops_grown_others',
            'ownership_others' => $prefix.'_ownership_others',
        ];

        $data = [];
        foreach ($mapping as $field => $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value === null || $this->isEmptyValue($value)) {
                continue;
            }
            if ($field === 'available') {
                $data[$field] = $this->parseNullableBooleanFromRow($row, $key);
            } elseif (in_array($field, ['forages_grown', 'crops_grown', 'ownership', 'farm_type', 'water_source'], true)) {
                $parsed = $this->parseListFromRow($value);
                if ($parsed !== null) {
                    $data[$field] = $parsed;
                }
            } else {
                $data[$field] = $value;
            }
        }

        return $data === [] ? null : $data;
    }

    private function getCategoryFromRow(array $row): ?string
    {
        $raw = $row['category'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $value = strtolower(trim((string) $raw));

        return $value !== '' ? $value : null;
    }

    private function getCategorySizeFromRow(array $row): ?string
    {
        $raw = $row['category_size'] ?? $row['categorysize'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $value = strtolower(trim((string) $raw));

        return $value !== '' ? $value : null;
    }

    private function getStatusFromRow(array $row): ?string
    {
        $raw = $row['status'] ?? null;
        if ($raw === null || $this->isEmptyValue($raw)) {
            return null;
        }

        $normalized = strtolower(trim((string) $raw));
        $normalized = preg_replace('/[^a-z]+/', '', $normalized) ?? $normalized; // "In-Active" => "inactive"

        return match ($normalized) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => null,
        };
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        $row->getIndex();
        $row = $row->toArray(calculateFormulas: true);

        $farmerCode = $this->getFarmerCodeFromRow($row);

        if ($farmerCode === null) {
            return;
        }

        $partnerId = $this->resolvePartnerId($row);
        $status = $this->getStatusFromRow($row);
        $category = $this->getCategoryFromRow($row);
        $categorySize = $this->getCategorySizeFromRow($row);
        $metadata = $this->extractExtraMetadata($row);

        $dateOfBirth = Sanitizer::parseDate($row['birthday'] ?? null);
        $age = null;
        if (is_numeric($row['age'] ?? null) && ! $this->isEmptyValue($row['age'] ?? null)) {
            $age = (int) $row['age'];
        } elseif ($dateOfBirth instanceof \DateTimeInterface) {
            $age = Date::instance($dateOfBirth)->age;
        }

        $farmer = $this->upsertFarmerAction->execute(
            centerId: $this->centerId,
            farmerCode: $farmerCode,
            partnerId: $partnerId,
            dateOfInterview: Sanitizer::parseDate($row['date_of_interview'] ?? null),
            dateOfBirth: $dateOfBirth,
            photo: $this->isEmptyValue($row['photo']) ? null : $row['photo'],
            firstName: $this->isEmptyValue($row['first_name']) ? null : $row['first_name'],
            lastName: $this->isEmptyValue($row['last_name']) ? null : $row['last_name'],
            middleName: $this->isEmptyValue($row['middle_initial']) ? null : $row['middle_initial'],
            nickname: $this->isEmptyValue($row['nickname']) ? null : $row['nickname'],
            extensionName: $this->isEmptyValue($row['extension_name']) ? null : $row['extension_name'],
            contactNumber: $this->isEmptyValue($row['contact_number']) ? null : $row['contact_number'],
            gender: $row['gender'] ?? null,
            civilStatus: $this->isEmptyValue($row['civil_status'] ?? null)
                ? null
                : (CivilStatusEnum::tryFrom(trim(strtolower((string) $row['civil_status'])))
                    ?? CivilStatusEnum::tryFroMatches((string) $row['civil_status']))?->value,
            spouseName: $this->isEmptyValue($row['spouse_name'] ?? null) ? null : $row['spouse_name'],
            age: $age,
            householdSize: is_numeric($row['household_size'] ?? null) && ! $this->isEmptyValue($row['household_size'] ?? null) ? (int) $row['household_size'] : null,
            position: $this->isEmptyValue($row['position'] ?? null) ? null : $row['position'],
            yearInvolvementInBusiness: is_numeric($row['year_involvement_in_the_business'] ?? null) ? (int) $row['year_involvement_in_the_business'] : null,
            familyMemberAttendingSchoolCount: is_numeric($row['family_member_attending_school_count'] ?? null) ? (int) $row['family_member_attending_school_count'] : null,
            femaleCount: is_numeric($row['female_count'] ?? null) ? (int) $row['female_count'] : null,
            maleCount: is_numeric($row['male_count'] ?? null) ? (int) $row['male_count'] : null,
            isPwd: $this->parseNullableBooleanFromRow($row, 'ispwd'),
            is4ps: $this->parseNullableBooleanFromRow($row, 'is4ps'),
            isIps: $this->parseNullableBooleanFromRow($row, 'isips'),
            indigenousGroup: $this->isEmptyValue($row['indigenious_group'] ?? null) ? null : $row['indigenious_group'],
            educationalAttainment: $this->isEmptyValue($row['educationalattainment'] ?? null)
                ? null
                : (EducationalAttainmentEnum::tryFrom(trim(strtolower((string) $row['educationalattainment'])))
                    ?? EducationalAttainmentEnum::tryFroMatches((string) $row['educationalattainment']))?->value,
            ndaltoCertificationStatus: $this->isEmptyValue($row['ndaltocertificationstatus'] ?? null) ? null : $row['ndaltocertificationstatus'],
            ndaltoCertification: $this->isEmptyValue($row['ndaltocertification'] ?? null) ? null : $row['ndaltocertification'],
            typeOfEntrepreneur: $this->isEmptyValue($row['type_of_entrepreneur'] ?? null)
                ? null
                : (EntrepreneurTypeEnum::tryFrom(trim(strtolower((string) $row['type_of_entrepreneur'])))
                    ?? EntrepreneurTypeEnum::tryFroMatches((string) $row['type_of_entrepreneur']))?->value,
            rsbsaCertStatus: $this->isEmptyValue($row['rsbsa_cert_status'] ?? null) ? null : $row['rsbsa_cert_status'],
            rsbsaCertificateOfRegistration: $this->isEmptyValue($row['rsbsa_certificate_of_registration'] ?? null) ? null : $row['rsbsa_certificate_of_registration'],
            ifProducerPleaseSpecify: $this->isEmptyValue($row['if_producer_please_specify'] ?? null) ? null : $row['if_producer_please_specify'],
            involvedInBuffaloFarming: $this->parseNullableBooleanFromRow($row, 'involved_in_buffalo_farming'),
            howManyInFamilyAreInvolvedInBuffaloFarming: is_numeric($row['how_many_in_the_family_are_involved_in_buffalo_farming'] ?? null) ? (int) $row['how_many_in_the_family_are_involved_in_buffalo_farming'] : null,
            areYouTheHeadOfTheFamily: $this->parseNullableBooleanFromRow($row, 'are_you_the_head_of_the_family'),
            ifNoHighestEducationalAttainmentOfHeadOfFamily: $row['if_no,_what_is_the_highest_educational_attainment_of_the_head_of_your_family'] ?? null,
            challengesOrProblemsEncounteredInDairyFarming: $row['challenges_or_problems_encountered_in_dairy_farming'] ?? null,
            mainSourceOfIncome: $this->isEmptyValue($row['main_source_of_income'] ?? null)
                ? null
                : (IncomeSourceEnum::tryFrom((string) $row['main_source_of_income'])
                    ?? IncomeSourceEnum::tryFroMatches((string) $row['main_source_of_income']))?->value,
            mainAnnualNetIncome: $this->isEmptyValue($row['main_annual_net_income']) ? null : (float) str_replace([',', '"'], '', $row['main_annual_net_income']),
            secondarySourceOfIncome: $this->isEmptyValue($row['secondary_source_of_income'] ?? null)
                ? null
                : (IncomeSourceEnum::tryFrom((string) $row['secondary_source_of_income'])
                    ?? IncomeSourceEnum::tryFroMatches((string) $row['secondary_source_of_income']))?->value,
            secondaryAnnualNetIncome: $this->isEmptyValue($row['secondary_annual_net_income']) ? null : (float) str_replace([',', '"'], '', $row['secondary_annual_net_income']),
            tertiarySourceOfIncome: $this->isEmptyValue($row['tertiary_source_of_income'] ?? null)
                ? null
                : (IncomeSourceEnum::tryFrom((string) $row['tertiary_source_of_income'])
                    ?? IncomeSourceEnum::tryFroMatches((string) $row['tertiary_source_of_income']))?->value,
            tertiaryAnnualNetIncome: $this->isEmptyValue($row['tertiary_annual_net_income']) ? null : (float) str_replace([',', '"'], '', $row['tertiary_annual_net_income']),
            quaternarySourceOfIncome: $this->isEmptyValue($row['quaternary_source_of_income'] ?? null)
                ? null
                : (IncomeSourceEnum::tryFrom((string) $row['quaternary_source_of_income'])
                    ?? IncomeSourceEnum::tryFroMatches((string) $row['quaternary_source_of_income']))?->value,
            quaternaryAnnualNetIncome: $this->isEmptyValue($row['quaternary_annual_net_income']) ? null : (float) str_replace([',', '"'], '', $row['quaternary_annual_net_income']),
            avgNetIncome: $this->isEmptyValue($row['avg_net_income']) ? null : (float) str_replace([',', '"'], '', $row['avg_net_income']),
            staffCountMale: is_numeric($row['staffcount']) && $row['staffcount'] > 0 ? (int) $row['staffcount'] : null,
            staffCountFemale: is_numeric($row['staffcountfemale']) && $row['staffcountfemale'] > 0 ? (int) $row['staffcountfemale'] : null,
            aveMonthlySalary: is_numeric($row['avemonthlysalary']) && $row['avemonthlysalary'] > 0 ? (float) $row['avemonthlysalary'] : null,
            statusGoogleMap: $this->isEmptyValue($row['status_google_map']) ? null : $row['status_google_map'],
            googleMapLink: $this->isEmptyValue($row['google_map_link']) ? null : $row['google_map_link'],
            gpsLatitude: $this->isValidLatitude($row['gps_latitude']) ? (float) $row['gps_latitude'] : null,
            gpsLongitude: $this->isValidLongitude($this->getLongitudeFromRow($row)) ? (float) $this->getLongitudeFromRow($row) : null,
            geotagImage: $this->isEmptyValue($row['geotag_image']) ? null : $row['geotag_image'],
            managementSystem: $this->isEmptyValue($row['managementsystem'] ?? null)
                ? null
                : (ManagementSystemEnum::tryFrom(trim(strtolower((string) $row['managementsystem'])))
                    ?? ManagementSystemEnum::tryFroMatches((string) $row['managementsystem']))?->value,
            isDrylot: $this->parseNullableBooleanFromRow($row, 'isdrylot'),
            durationOfStocking: $this->isEmptyValue($row['durationofstocking'] ?? null) ? null : $row['durationofstocking'],
            isAppliedInTheField: $this->parseNullableBooleanFromRow($row, 'isappliedinthefield'),
            isDryRiceField: $this->parseNullableBooleanFromRow($row, 'isdryricefield'),
            isDryVegetable: $this->parseNullableBooleanFromRow($row, 'isdryvegetable'),
            isVermicomposting: $this->parseNullableBooleanFromRow($row, 'isvermicomposting'),
            isPastureRangePaddock: $this->parseNullableBooleanFromRow($row, 'ispasturerangepaddock'),
            isDailySpread: $this->parseNullableBooleanFromRow($row, 'isdailyspread'),
            isStorageBulking: $this->parseNullableBooleanFromRow($row, 'isstoragebulking'),
            isLiquidSlurry: $this->parseNullableBooleanFromRow($row, 'isliquidslurry'),
            isAnaerobicDigester: $this->parseNullableBooleanFromRow($row, 'isanaerobicdigester'),
            foragesRainyAndDry: $this->isEmptyValue($row['forages_rainy_and_dry'] ?? null) ? null : $row['forages_rainy_and_dry'],
            silageSpecification: $this->isEmptyValue($row['silagespecfication']) ? null : $row['silagespecfication'],
            dairyCattle: is_numeric($row['dairycattle']) && $row['dairycattle'] > 0 ? (int) $row['dairycattle'] : null,
            beefCattle: is_numeric($row['beefcattle']) && $row['beefcattle'] > 0 ? (int) $row['beefcattle'] : null,
            goat: is_numeric($row['goat']) && $row['goat'] > 0 ? (int) $row['goat'] : null,
            chicken: is_numeric($row['chicken']) && $row['chicken'] > 0 ? (int) $row['chicken'] : null,
            duck: is_numeric($row['duck']) && $row['duck'] > 0 ? (int) $row['duck'] : null,
            geese: is_numeric($row['geese']) && $row['geese'] > 0 ? (int) $row['geese'] : null,
            swine: is_numeric($row['swine']) && $row['swine'] > 0 ? (int) $row['swine'] : null,
            horse: is_numeric($row['horse']) && $row['horse'] > 0 ? (int) $row['horse'] : null,
            otherLivestockLabel: $this->isEmptyValue($row['otherlivestock']) ? null : $row['otherlivestock'],
            otherLivestockCount: is_numeric($row['otherlivestock']) && $row['otherlivestock'] > 0 ? (int) $row['otherlivestock'] : null,
            farmName: $this->isEmptyValue($row['farmname'] ?? null) ? null : $row['farmname'],
            farmRegion: $this->isEmptyValue($row['farmerregion'] ?? null) ? null : $row['farmerregion'],
            farmProvince: $this->isEmptyValue($row['farmerprovince'] ?? null) ? null : $row['farmerprovince'],
            farmMunicipalityCity: $this->isEmptyValue($row['farmermunicipalitycity'] ?? null) ? null : $row['farmermunicipalitycity'],
            farmBarangay: $this->isEmptyValue($row['farmerbarangay'] ?? null) ? null : $row['farmerbarangay'],
            farmPurokZone: $this->isEmptyValue($row['farmerpurokzone'] ?? null) ? null : $row['farmerpurokzone'],
            pccProjectsAssets: $this->isEmptyValue($row['pcc_projects_assets'] ?? null) ? null : $row['pcc_projects_assets'],
            isSolidStorageCovered: $this->parseNullableBooleanFromRow($row, 'issolidstoragecovered'),
            isSolidStorageAdditives: $this->parseNullableBooleanFromRow($row, 'issolidstorageadditives'),
            isUncoveredAnaerobicLagoon: $this->parseNullableBooleanFromRow($row, 'isuncoveredlagoon'),
            entrepreneurImage: $this->isEmptyValue($row['entrepreneur_image'] ?? null) ? null : $row['entrepreneur_image'],
            enterpriseImage: $this->isEmptyValue($row['enterprise_image'] ?? null) ? null : $row['enterprise_image'],
            description: $this->isEmptyValue($row['description'] ?? null) ? null : $row['description'],
            status: $status,
            category: $category,
            categorySize: $categorySize,
            dateOfEnrollment: Sanitizer::parseDate($row['date_of_enrollment'] ?? null),
            operationInCharge: $this->isEmptyValue($row['operation_in_charge'] ?? null) ? null : $row['operation_in_charge'],
            isAssisted: $this->parseNullableBooleanFromRow($row, 'isassisted'),
            projectCategory: $this->isEmptyValue($row['projectcategory'] ?? null)
                ? null
                : (ProjectProgramEnum::tryFrom(trim(strtolower((string) $row['projectcategory'])))
                    ?? ProjectProgramEnum::tryFroMatches((string) $row['projectcategory']))?->value,
            hasPersonalCellphone: $this->parseNullableBooleanFromRow($row, 'availability_of_personal_cellphone'),
            purokZone: $this->isEmptyValue($row['purokzone'] ?? null) ? null : $row['purokzone'],
            zipCode: $this->isEmptyValue($row['zipcode'] ?? null) ? null : (string) $row['zipcode'],
            metadata: $metadata,
            proposedFarmName: $this->isEmptyValue($row['proposedfarmname'] ?? null) ? null : $row['proposedfarmname'],
            farmDistrict: $this->isEmptyValue($row['farmerdistrict'] ?? null) ? null : $row['farmerdistrict'],
            farmStreet: $this->isEmptyValue($row['farmstreet'] ?? null) ? null : $row['farmstreet'],
            typeOfFarmBusinessRegistration: $this->parseListFromRow($row['typeoffarmbusinessregistration'] ?? null),
            statusOfRegistration: $this->isEmptyValue($row['statusofregistration'] ?? null) ? null : $row['statusofregistration'],
            farmRegistrationNumber: $this->isEmptyValue($row['farmregistrationnumber'] ?? null) ? null : $row['farmregistrationnumber'],
            yearFarmEstablished: $this->isEmptyValue($row['yearfarmestablished'] ?? null)
                ? null
                : (int) Sanitizer::parseNumeric($row['yearfarmestablished']),
        );

        if (! $farmer) {
            return;
        }

        $region = AddressRegionEnum::tryFroMatches($row['region'] ?? null);
        $district = AddressDistrictEnum::tryFroMatches($row['district'] ?? null);

        // Upsert Home Address
        $this->upsertAddressAction->execute(
            addressableType: Str::of(class_basename(Farmer::class))->lower(),
            addressableId: $farmer->id,
            street: $row['street'] ?? null,
            barangay: $row['barangay'] ?? null,
            city: $row['municipalitycity'] ?? null,
            province: $row['province'] ?? null,
            region: $region->value ?? null,
            district: $district->value ?? null,
            zipCode: isset($row['zipcode']) && Sanitizer::parseNumeric($row['zipcode']) !== 0.0
                ? (int) Sanitizer::parseNumeric($row['zipcode'])
                : null,
            latitude: $this->isValidLatitude($row['gps_latitude']) ? (float) $row['gps_latitude'] : null,
            longitude: $this->isValidLongitude($this->getLongitudeFromRow($row)) ? (float) $this->getLongitudeFromRow($row) : null,
            markerIcon: null,
            category: AddressCategoryEnum::FARM->value,
            centerId: $this->centerId
        );

        // Upsert Farm Address

        $region = AddressRegionEnum::tryFroMatches($row['farmerregion'] ?? null);
        $district = AddressDistrictEnum::tryFroMatches($row['farmerdistrict'] ?? null);

        $this->upsertAddressAction->execute(
            addressableType: Str::of(class_basename(Farmer::class))->lower(),
            addressableId: $farmer->id,
            street: $row['farmname'] ?? null,
            barangay: $row['farmerbarangay'] ?? null,
            city: $row['farmermunicipalitycity'] ?? null,
            province: $row['farmerprovince'] ?? null,
            region: $region->value ?? null,
            district: $district->value ?? null,
            zipCode: null,
            latitude: null,
            longitude: null,
            markerIcon: null,
            category: AddressCategoryEnum::FARM->value,
            centerId: $this->centerId
        );

        if (isset($row['documents']) && env('DEMO_MODE')) {
            $documents = explode(',', $row['documents']);
            foreach ($documents as $document) {
                $documentEnum = DocumentEnum::fromLabel($document);
                $fileName = $documentEnum->value.'-demo'.'.pdf';
                $document = $this->createDocumentAction->execute(
                    title: $document." {$farmerCode} ".'demo',
                    fileName: $fileName,
                    fileKey: 'presets/'.$fileName,
                    fileType: 'application/pdf',
                    fileSize: 1000,
                    uploadedBy: 1,
                );
                Tag::updateOrCreate(
                    [
                        'key' => Str::of(class_basename(Farmer::class))->lower(),
                        'value' => $farmer->id,
                        'taggable_type' => Str::of(class_basename(Document::class))->lower(),
                        'taggable_id' => $document->id,
                    ],
                    []
                );
            }
        }
        // @todo provides roles in value chain, some of the partners info by default.
        if (isset($row['roles_in_value_chain'])) {
            // generate production, cosolidation etc..
        }
    }
}

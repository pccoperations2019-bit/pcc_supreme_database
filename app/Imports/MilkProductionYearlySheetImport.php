<?php

namespace App\Imports;

use App\Actions\Farmer\UpsertFarmerAction;
use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\MilkingTimeEnum;
use App\Enums\MonthEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Farmer;
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

class MilkProductionYearlySheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow
{
    private UpsertMilkProductionAction $upsertMilkProductionAction;

    private UpsertMilkSaleAction $upsertMilkSaleAction;

    private UpsertPartnerAction $upsertPartnerAction;

    private UpsertFarmerAction $upsertFarmerAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private array $defaultPartnerIdByCenterId = [];

    private array $farmerIdsByCode = [];

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    private ?int $defaultYear = null;

    private bool $isSales = false;

    private bool $isFarmerSheet = false;

    public function __construct(
        ?UpsertMilkProductionAction $upsertMilkProductionAction = null,
        ?UpsertMilkSaleAction $upsertMilkSaleAction = null,
        ?UpsertPartnerAction $upsertPartnerAction = null,
        ?UpsertFarmerAction $upsertFarmerAction = null
    ) {
        $this->upsertMilkProductionAction = $upsertMilkProductionAction ?? resolve(UpsertMilkProductionAction::class);
        $this->upsertMilkSaleAction = $upsertMilkSaleAction ?? resolve(UpsertMilkSaleAction::class);
        $this->upsertPartnerAction = $upsertPartnerAction ?? resolve(UpsertPartnerAction::class);
        $this->upsertFarmerAction = $upsertFarmerAction ?? resolve(UpsertFarmerAction::class);
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $title = (string) $event->sheet->getTitle();

                if (preg_match('/(\d{4})/', $title, $matches)) {
                    $this->defaultYear = (int) $matches[1];
                }

                $this->isSales = stripos($title, 'sales') !== false;
                $this->isFarmerSheet = stripos($title, 'farmer') !== false;
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

        $centerName = $this->getRowValue($rowData, ['center', 'Center', 'center name', 'center_name', 'center code', 'center_code']);
        $partnerCode = $this->getRowValue($rowData, ['partner code', 'partner_code', 'partner']);
        $cooperativeName = $this->getRowValue($rowData, ['cooperative', 'Cooperative', 'coop']);
        $farmerCode = $this->getRowValue($rowData, ['farmer code', 'farmer_code', 'farmer']);
        $farmerName = $this->getRowValue($rowData, ['farmer name', 'farmer_name']);
        $type = $this->getRowValue($rowData, ['type', 'Type']);
        $rowYear = $this->getRowValue($rowData, ['year', 'Year', 'YEAR']);

        $rowYear = $rowYear !== null && $rowYear !== '' ? (int) $rowYear : null;
        $centerName = $centerName !== null ? trim((string) $centerName) : null;
        $partnerCode = $partnerCode !== null ? trim((string) $partnerCode) : null;
        $cooperativeName = $cooperativeName !== null ? trim((string) $cooperativeName) : null;
        $farmerCode = $farmerCode !== null ? trim((string) $farmerCode) : null;
        $farmerName = $farmerName !== null ? trim((string) $farmerName) : null;
        $type = $type !== null ? trim((string) $type) : null;

        $centerName = $centerName === '' ? null : $centerName;
        $partnerCode = $partnerCode === '' ? null : $partnerCode;
        $cooperativeName = $cooperativeName === '' ? null : $cooperativeName;
        $farmerCode = $farmerCode === '' ? null : $farmerCode;
        $farmerName = $farmerName === '' ? null : $farmerName;
        $type = $type === '' ? null : $type;

        if (! $centerName) {
            throw new \Exception("Row {$row->getIndex()}: Center is required but not found in row data");
        }

        $year = $rowYear ?? $this->defaultYear;
        if (! $year) {
            throw new \Exception("Row {$row->getIndex()}: Year is required but not found. Either include a YEAR column or ensure sheet name contains a year (e.g., '2024 Production')");
        }

        $program = $this->resolveProgram($type) ?? $this->program;

        Log::info('MilkProductionYearlySheetImport: Processing row', [
            'row' => $row->getIndex(),
            'center' => $centerName,
            'partner_code' => $partnerCode,
            'cooperative' => $cooperativeName,
            'farmer_code' => $farmerCode,
            'farmer_name' => $farmerName,
            'raw_type' => $type,
            'resolved_program' => $program->value,
            'sheet_program' => $this->program->value,
            'year' => $year,
        ]);

        [$centerId, $partnerId, $farmerId] = $this->resolveCenterPartnerFarmer($centerName, $partnerCode, $cooperativeName, $farmerCode, $farmerName, $program, $row->getIndex());

        Log::info('MilkProductionYearlySheetImport: Entities resolved', [
            'row' => $row->getIndex(),
            'centerId' => $centerId,
            'partnerId' => $partnerId,
            'farmerId' => $farmerId,
        ]);

        $monthsProcessed = 0;
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

            $transactionDate = Date::create($year, $monthNumber, 1)->endOfMonth()->startOfDay();
            $periodStart = $transactionDate->copy()->startOfMonth();
            $periodEnd = $transactionDate->copy()->endOfMonth();

            $transactionYear = (int) $transactionDate->format('Y');
            $month = (int) $transactionDate->format('m');
            $quarter = (int) ceil($month / 3);
            $week = null;

            try {
                if ($this->isSales) {
                    Log::info('MilkProductionYearlySheetImport: Upserting milk sale', [
                        'row' => $row->getIndex(),
                        'month' => $monthColumn,
                        'volume' => $totalVolume,
                        'centerId' => $centerId,
                        'partnerId' => $partnerId,
                        'farmerId' => $farmerId,
                    ]);

                    $this->upsertMilkSaleAction->execute(
                        milkProductionId: null,
                        centerId: $centerId,
                        partnerId: $partnerId,
                        farmerId: $farmerId,
                        transactionDate: $transactionDate,
                        year: $transactionYear,
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
                        program: $program->value
                    );
                } else {
                    Log::info('MilkProductionYearlySheetImport: Upserting milk production', [
                        'row' => $row->getIndex(),
                        'month' => $monthColumn,
                        'volume' => $totalVolume,
                        'centerId' => $centerId,
                        'partnerId' => $partnerId,
                        'farmerId' => $farmerId,
                    ]);

                    $this->upsertMilkProductionAction->execute(
                        productionCode: null,
                        centerId: $centerId,
                        partnerId: $partnerId,
                        farmerId: $farmerId,
                        transactionDate: $transactionDate,
                        durationStart: $periodStart,
                        durationEnd: $periodEnd,
                        totalVolume: $totalVolume,
                        volumeFromFarmers: null,
                        milkingTimes: MilkingTimeEnum::DEFAULT,
                        numberOfLactating: null,
                        avgMilkPerAnimalPerDay: null,
                        program: $program,
                        status: ProductionStatusEnum::DEFAULT,
                        notes: "Type: {$type}, Sheet: ".($this->isSales ? 'Sales' : 'Production').($this->isFarmerSheet ? ', Farmer Sheet' : ''),
                        year: $transactionYear,
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
                }
                $monthsProcessed++;
            } catch (\Exception $e) {
                Log::error('MilkProductionYearlySheetImport: Error processing row', [
                    'row' => $row->getIndex(),
                    'month' => $monthColumn,
                    'item' => $this->isSales ? 'SALES' : 'PRODUCTION',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $rowData,
                ]);
            }
        }

        Log::info('MilkProductionYearlySheetImport: Row completed', [
            'row' => $row->getIndex(),
            'months_processed' => $monthsProcessed,
        ]);
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

    private function resolveCenter(?string $centerName, int $rowIndex): int
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

            Log::info('MilkProductionYearlySheetImport: Loaded centers cache', [
                'total_centers' => count($this->centerIdsByCode),
                'sample_codes' => array_slice(array_keys($this->centerIdsByCode), 0, 10),
            ]);
        }

        $centerId = null;
        if ($centerName) {
            $searchKey = strtoupper(trim($centerName));
            $centerId = $this->centerIdsByCode[$searchKey]
                ?? $this->centerIdsByName[strtolower(trim($centerName))]
                ?? null;

            Log::info('MilkProductionYearlySheetImport: Center lookup', [
                'row' => $rowIndex,
                'search_key' => $searchKey,
                'found' => $centerId !== null,
                'centerId' => $centerId,
            ]);
        }

        if (! $centerId) {
            Log::error('MilkProductionYearlySheetImport: Center not found - failing import', [
                'row' => $rowIndex,
                'center_value' => $centerName,
                'trimmed' => trim($centerName),
                'uppercase' => strtoupper(trim($centerName)),
                'available_codes' => array_slice(array_keys($this->centerIdsByCode), 0, 20),
            ]);

            throw new \Exception("Center '{$centerName}' not found in database. Available centers: ".implode(', ', array_slice(array_keys($this->centerIdsByCode), 0, 10)));
        }

        return $centerId;
    }

    private function resolveCenterPartnerFarmer(?string $centerName, ?string $partnerCode, ?string $cooperativeName, ?string $farmerCode, ?string $farmerName, ProgramEnum $program, int $rowIndex): array
    {
        $centerId = $this->resolveCenter($centerName, $rowIndex);

        $partner = null;
        if ($centerId && $program !== ProgramEnum::IH && $partnerCode) {
            $partner = Partner::where('center_id', $centerId)
                ->whereRaw('LOWER(code) = ?', [strtolower($partnerCode)])
                ->first();

            if (! $partner) {
                $partner = Partner::where('center_id', $centerId)
                    ->where('code', 'LIKE', "%{$partnerCode}%")
                    ->first();
            }
        }

        if ($centerId && $program !== ProgramEnum::IH && ! $partner && $cooperativeName) {
            $partner = Partner::where('center_id', $centerId)
                ->whereRaw('LOWER(name) = ?', [strtolower($cooperativeName)])
                ->first();

            if (! $partner) {
                $partner = Partner::where('center_id', $centerId)
                    ->where('name', 'LIKE', "%{$cooperativeName}%")
                    ->first();
            }
        }

        if ($centerId && $program !== ProgramEnum::IH && ! $partner && $cooperativeName) {
            $partner = $this->upsertPartnerAction->execute(
                code: $partnerCode,
                centerId: $centerId,
                name: $cooperativeName,
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

        if ($centerId && $program !== ProgramEnum::IH && ! $partner && ! $cooperativeName) {
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

        $farmer = null;
        if ($centerId && $farmerCode) {
            $farmer = $this->resolveFarmer($centerId, $farmerCode, $farmerName, $partner?->id, $rowIndex);
        }

        return [$centerId, $partner?->id, $farmer?->id];
    }

    private function resolveFarmer(int $centerId, ?string $farmerCode, ?string $farmerName, ?int $partnerId, int $rowIndex): ?Farmer
    {
        Log::info('MilkProductionYearlySheetImport: Resolving farmer', [
            'row' => $rowIndex,
            'centerId' => $centerId,
            'farmerCode' => $farmerCode,
            'farmerName' => $farmerName,
            'partnerId' => $partnerId,
        ]);

        if (! $this->farmerIdsByCode) {
            $farmers = Farmer::where('center_id', $centerId)
                ->select('id', 'code', 'first_name', 'last_name')
                ->get();

            foreach ($farmers as $farmer) {
                if (! empty($farmer->code)) {
                    $this->farmerIdsByCode[strtoupper(trim((string) $farmer->code))] = $farmer;
                }
            }

            Log::info('MilkProductionYearlySheetImport: Loaded farmers cache', [
                'centerId' => $centerId,
                'count' => count($this->farmerIdsByCode),
            ]);
        }

        $farmer = null;

        if ($farmerCode) {
            $farmer = $this->farmerIdsByCode[strtoupper(trim($farmerCode))] ?? null;
            if ($farmer) {
                Log::info('MilkProductionYearlySheetImport: Farmer found by code', [
                    'row' => $rowIndex,
                    'farmerCode' => $farmerCode,
                    'farmerId' => $farmer->id,
                ]);
            }
        }

        if (! $farmer && $farmerName) {
            $farmer = Farmer::where('center_id', $centerId)
                ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$farmerName}%"])
                ->first();
            if ($farmer) {
                Log::info('MilkProductionYearlySheetImport: Farmer found by name', [
                    'row' => $rowIndex,
                    'farmerName' => $farmerName,
                    'farmerId' => $farmer->id,
                ]);
            }
        }

        if (! $farmer && $farmerCode && $farmerName) {
            try {
                $nameParts = explode(' ', $farmerName, 2);
                $firstName = $nameParts[0] ?? $farmerName;
                $lastName = $nameParts[1] ?? '';

                Log::info('MilkProductionYearlySheetImport: Creating new farmer', [
                    'row' => $rowIndex,
                    'farmerCode' => $farmerCode,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'partnerId' => $partnerId,
                ]);

                $farmer = $this->upsertFarmerAction->execute(
                    centerId: $centerId,
                    farmerCode: $farmerCode,
                    partnerId: $partnerId,
                    dateOfInterview: null,
                    dateOfBirth: null,
                    photo: null,
                    firstName: $firstName,
                    lastName: $lastName,
                    middleName: null,
                    nickname: null,
                    extensionName: null,
                    contactNumber: null,
                    gender: null,
                    civilStatus: null,
                    spouseName: null,
                    age: null,
                    householdSize: null,
                    position: null,
                    yearInvolvementInBusiness: null,
                    familyMemberAttendingSchoolCount: null,
                    femaleCount: null,
                    maleCount: null,
                    isPwd: null,
                    is4ps: null,
                    isIps: null,
                    indigenousGroup: null,
                    educationalAttainment: null,
                    ndaltoCertificationStatus: null,
                    ndaltoCertification: null,
                    typeOfEntrepreneur: null,
                    rsbsaCertStatus: null,
                    rsbsaCertificateOfRegistration: null,
                    ifProducerPleaseSpecify: null,
                    involvedInBuffaloFarming: null,
                    howManyInFamilyAreInvolvedInBuffaloFarming: null,
                    areYouTheHeadOfTheFamily: null,
                    ifNoHighestEducationalAttainmentOfHeadOfFamily: null,
                    challengesOrProblemsEncounteredInDairyFarming: null,
                    mainSourceOfIncome: null,
                    mainAnnualNetIncome: null,
                    secondarySourceOfIncome: null,
                    secondaryAnnualNetIncome: null,
                    tertiarySourceOfIncome: null,
                    tertiaryAnnualNetIncome: null,
                    quaternarySourceOfIncome: null,
                    quaternaryAnnualNetIncome: null,
                    avgNetIncome: null,
                    staffCountMale: null,
                    staffCountFemale: null,
                    aveMonthlySalary: null,
                    statusGoogleMap: null,
                    googleMapLink: null,
                    gpsLatitude: null,
                    gpsLongitude: null,
                    geotagImage: null,
                    managementSystem: null,
                    isDrylot: null,
                    durationOfStocking: null,
                    isAppliedInTheField: null,
                    isDryRiceField: null,
                    isDryVegetable: null,
                    isVermicomposting: null,
                    isPastureRangePaddock: null,
                    isDailySpread: null,
                    isStorageBulking: null,
                    isLiquidSlurry: null,
                    isAnaerobicDigester: null,
                    foragesRainyAndDry: null,
                    silageSpecification: null,
                    dairyCattle: null,
                    beefCattle: null,
                    goat: null,
                    chicken: null,
                    duck: null,
                    geese: null,
                    swine: null,
                    horse: null,
                    otherLivestockLabel: null,
                    otherLivestockCount: null,
                    farmName: null,
                    farmRegion: null,
                    farmProvince: null,
                    farmMunicipalityCity: null,
                    farmBarangay: null,
                    farmPurokZone: null,
                    pccProjectsAssets: null,
                    isSolidStorageCovered: null,
                    isSolidStorageAdditives: null,
                    isUncoveredAnaerobicLagoon: null,
                    entrepreneurImage: null,
                    enterpriseImage: null,
                    description: null,
                    status: null,
                    category: null,
                    categorySize: null,
                    dateOfEnrollment: null,
                    operationInCharge: null,
                    isAssisted: null,
                    projectCategory: null,
                    hasPersonalCellphone: null,
                    purokZone: null,
                    zipCode: null,
                    metadata: null,
                    proposedFarmName: null,
                    farmDistrict: null,
                    farmStreet: null,
                    typeOfFarmBusinessRegistration: null,
                    statusOfRegistration: null,
                    farmRegistrationNumber: null,
                    hasRsbsa: null,
                    yearFarmEstablished: null
                );

                if ($farmer && ! empty($farmer->code)) {
                    $this->farmerIdsByCode[strtoupper(trim($farmer->code))] = $farmer;
                    Log::info('MilkProductionYearlySheetImport: Farmer created successfully', [
                        'row' => $rowIndex,
                        'farmerId' => $farmer->id,
                        'farmerCode' => $farmer->code,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('MilkProductionYearlySheetImport: Failed to create farmer', [
                    'row' => $rowIndex,
                    'farmer_code' => $farmerCode,
                    'farmer_name' => $farmerName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if (! $farmer && $farmerCode) {
            throw new \Exception("Farmer with code '{$farmerCode}' not found in database for center ID {$centerId}. Either the farmer doesn't exist or there's a typo in the farmer code.");
        }

        return $farmer;
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

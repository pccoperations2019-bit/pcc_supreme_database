<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Actions\Project\UpsertMilkFeedingProgramAction;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkFeedingStatusEnum;
use App\Enums\MilkSubcategoryEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Enums\ProgramEnum;
use App\Models\MilkFeedingProgram;
use App\Models\Partner;
use App\Support\Sanitizer;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CenterMilkFeedingProgramImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
{
    private array $centerIds = [];

    public function __construct(
        private int $projectId,
        private readonly UpsertMilkFeedingProgramAction $upsertMilkFeedingActionAction,
        private readonly UpsertPartnerAction $upsertPartnerAction,
        private readonly UpsertAddressAction $upsertAddressAction
    ) {}

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        $rowData = $row->toArray(calculateFormulas: true);

        $getValue = function (array $keys, mixed $default = null) use ($rowData): mixed {
            foreach ($keys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '') {
                    return $rowData[$key];
                }
            }

            return $default;
        };
        $centerCode = trim($getValue(['center', 'Center'], ''));

        if ($centerCode === '' || $centerCode === '0') {
            return;
        }

        $centerId = null;
        $centerCodeUpper = strtoupper($centerCode);
        foreach ($this->centerIds as $code => $id) {
            if (strtoupper(trim($code)) === $centerCodeUpper) {
                $centerId = $id;
                break;
            }
        }

        if (! $centerId) {
            info("Skipping row - center code '{$centerCode}' not found in database. Available: ".implode(', ', array_keys($this->centerIds)));

            return;
        }

        $supplierName = trim($getValue(['supplier', 'Supplier'], ''));
        $supplierCode = trim($getValue(['supplier_code', 'Supplier Code', 'partner_code', 'Partner Code'], ''));
        $partner = null;

        if (! in_array($supplierName, ['', '0', '0'], true)) {

            if ($supplierCode !== '' && $supplierCode !== '0') {
                $partner = Partner::query()->where('center_id', $centerId)
                    ->where('code', $supplierCode)
                    ->first();
            }

            if (! $partner) {
                $partner = Partner::query()->where('center_id', $centerId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($supplierName)])
                    ->first();

                if (! $partner) {
                    $partner = Partner::query()->where('center_id', $centerId)
                        ->where('name', 'LIKE', "%{$supplierName}%")
                        ->first();
                }
            }

            if (! $partner) {
                $partner = $this->upsertPartnerAction->execute(
                    code: $supplierCode ?: null,
                    centerId: $centerId,
                    name: $supplierName,
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

        $year = Sanitizer::parseNumeric($getValue(['year', 'Year'])) !== 0.0 ? (int) $getValue(['year', 'Year']) : (int) date('Y');
        $fundedByEnum = MilkFeedingProgramFunderEnum::tryFroMatches($getValue(['funded_by', 'funded by', 'Funded By']));
        $regionEnum = AddressRegionEnum::tryFroMatches($getValue(['region', 'Region']));
        $districtEnum = AddressDistrictEnum::tryFroMatches($getValue(['district', 'District']));
        $provinceValue = Sanitizer::parseString($getValue(['province', 'Province']));
        $cityValue = Sanitizer::parseString($getValue(['municipality', 'Municipality', 'city', 'City']));

        // Backward compatibility for older sheets that still use a combined column.
        if (! $cityValue) {
            $cityValue = Sanitizer::parseString($getValue([
                'province_municipality',
                'province_ municipality',
                'province/ municipality',
                'Province/ Municipality',
            ]));
        }

        $elementarySchool = Sanitizer::parseString($getValue(['elementary_school', 'elementary school', 'Elementary School']));
        $division = Sanitizer::parseString($getValue(['division', 'Division']));

        // Determine program based on supplier name
        $supplierName = $partner->name ?? '';
        $program = null;
        if (stripos($supplierName, 'PCC') !== false && stripos($supplierName, "PCC at {$centerCode}") !== false) {
            $program = ProgramEnum::IH;
        } else {
            $program = ProgramEnum::CBED;
        }
        $partnerId = $program === ProgramEnum::IH ? null : $partner->id ?? null;

        $recordName = sprintf('Milk Feeding Program - %s - %s', $centerCode, $year);
        $totalVolumeRequirements = Sanitizer::parseNumeric($getValue(['total_volume_requirements', 'total volume requirements ', 'Total Volume Requirements '], 0));
        $grossIncome = Sanitizer::parseNumeric($getValue(['milk_cost', 'milk cost', 'Milk Cost', 'gross_income_of_suppliers', 'Gross Income of Suppliers'], 0));
        $dateStarted = Sanitizer::parseDateWithTypoCorrection($getValue(['date_started', 'date started', 'Date Started']))?->format('Y-m-d');
        $dateCompleted = Sanitizer::parseDateWithTypoCorrection($getValue(['date_completed', 'date completed', 'Date Completed']))?->format('Y-m-d');

        $existingRecord = MilkFeedingProgram::query()
            ->where('project_id', $this->projectId)
            ->where('center_id', $centerId)
            ->where('partner_id', $partnerId)
            ->where('name', $recordName)
            ->where('total_volume_requirements', $totalVolumeRequirements)
            ->where('gross_income', $grossIncome)
            ->where('date_started', $dateStarted)
            ->where('date_completed', $dateCompleted)
            ->first();

        info('[MilkFeedingImport] Row processing', [
            'row' => $row->getIndex(),
            'center_code' => $centerCode,
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'partner_name' => $partner->name ?? null,
            'program' => $program?->value,
            'name' => $recordName,
            'year' => $year,
            'total_volume_requirements' => $totalVolumeRequirements,
            'gross_income' => $grossIncome,
            'date_started' => $dateStarted,
            'date_completed' => $dateCompleted,
            'action' => $existingRecord ? 'UPDATE (id='.$existingRecord->id.')' : 'CREATE',
        ]);

        $milkFeedingProgram = $this->upsertMilkFeedingActionAction->execute(
            projectId: $this->projectId,
            centerId: $centerId,
            partnerId: $partnerId,
            name: $recordName,
            description: null,
            year: $year,
            fundedBy: $fundedByEnum,
            elementarySchool: $elementarySchool,
            division: $division,
            program: $program,
            milkPacks: (int) Sanitizer::parseNumeric($getValue(['milk_packs', 'milk packs', 'Milk Packs'], 0)),
            batch: Sanitizer::parseNumeric($getValue(['batch', 'Batch'])) ?: null,
            totalVolumeRequirements: $totalVolumeRequirements,
            rawMilkUsed: Sanitizer::parseNumeric($getValue(['raw_milk_used_in_liters', 'raw milk used in liters', 'Raw Milk Used in Liters'], 0)),
            wholeMilk: Sanitizer::parseNumeric($getValue(['whole_milk_kg', 'whole_milk', 'whole milk (kg)', 'Whole Milk (kg)'], 0)),
            skimmedMilk: Sanitizer::parseNumeric($getValue(['skimmed_milk_kg', 'skimmed_milk', 'skimmed milk (kg)', 'Skimmed Milk (kg)'], 0)),
            sugar: Sanitizer::parseNumeric($getValue(['sugar', 'Sugar'], 0)),
            milkType: MilkTypeEnum::tryFroMatches($getValue(['milk_type', 'milk type', 'Milk Type'])),
            milkSubcategory: MilkSubcategoryEnum::tryFroMatches($getValue(['milk_subcategory', 'milk subcategory', 'Milk Subcategory'])),
            pricePerMilkPack: Sanitizer::parseNumeric($getValue(['price', 'Price'], 0)),
            feedingDays: (int) Sanitizer::parseNumeric($getValue(['feeding_days', 'feeding days', 'Feeding Days'], 0)),
            numberOfBeneficiaries: (int) Sanitizer::parseNumeric($getValue(['beneficiaries', 'Beneficiaries'], 0)),
            totalContractAmount: Sanitizer::parseNumeric($getValue(['total_funds_transferred', 'total funds transferred', 'Total Funds Transferred', 'contract_amount', 'Contract Amount'], 0)),
            serviceFee: Sanitizer::parseNumeric($getValue(['service_fee_admin_cost', 'service fee/ admin cost', 'Service Fee/ Admin Cost', 'service_fee', 'Service Fee'], 0)),
            grossIncome: $grossIncome,
            liquidationDate: Sanitizer::parseDateWithTypoCorrection($getValue(['liquidation', 'Liquidation']))?->format('Y-m-d'),
            modeOfProcurement: ModeOfProcurementEnum::tryFroMatches($getValue(['mode_of_procurement', 'mode of procurement', 'Mode of Procurement'])),
            moaSigningDate: Sanitizer::parseDateWithTypoCorrection($getValue(['moa_signing', 'moa signing', 'MOA Signing']))?->format('Y-m-d'),
            fundTransferDate: Sanitizer::parseDateWithTypoCorrection($getValue(['fund_transfer', 'fund transfer', 'Fund Transfer']))?->format('Y-m-d'),
            dateStarted: $dateStarted,
            dateCompleted: $dateCompleted,
            status: MilkFeedingStatusEnum::PLANNED
        );

        if ($provinceValue || $cityValue || $regionEnum || $districtEnum) {
            $this->upsertAddressAction->execute(
                addressableType: $milkFeedingProgram->getMorphClass(),
                addressableId: $milkFeedingProgram->id,
                street: null,
                barangay: null,
                city: $cityValue,
                province: $provinceValue,
                region: $regionEnum,
                district: $districtEnum,
                zipCode: null,
                latitude: null,
                longitude: null,
                markerIcon: null,
                category: null,
                centerId: $centerId
            );
        }
    }
}

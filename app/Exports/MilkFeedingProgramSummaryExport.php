<?php

namespace App\Exports;

use App\Enums\AddressRegionEnum;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkTypeEnum;
use App\Exports\Concerns\FilterByAddress;
use App\Models\BeneficiarySource;
use App\Models\MilkFeedingProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MilkFeedingProgramSummaryExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected string|array|null $fundedBy = null,
        protected string|array|null $region = null,
        protected string|array|null $division = null,
        protected ?string $milkType = null,
        protected string|array|null $modeOfProcurement = null,
        protected string|array|null $supplier = null,
        protected string|array|null $province = null,
        protected ?string $yearRange = null,
    ) {}

    public function sheets(): array
    {
        if ($this->fundedBy === 'all' || $this->fundedBy === null) {
            return [
                new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, 'deped', $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange),
                new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, 'dswd', $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange),
                new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, 'lgu', $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange),
                new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, 'lds', $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange),
            ];
        }

        if (is_array($this->fundedBy)) {
            $sheets = [];
            foreach ($this->fundedBy as $funder) {
                $sheets[] = new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, $funder, $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange);
            }

            return $sheets;
        }

        return [
            new MilkFeedingProgramSummarySheet($this->year, $this->centerId, $this->partnerId, $this->fundedBy, $this->region, $this->milkType, $this->modeOfProcurement, $this->supplier, $this->province, $this->yearRange),
        ];
    }
}

class MilkFeedingProgramSummarySheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable, FilterByAddress;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected string|array|null $fundedBy = null,
        protected string|array|null $region = null,
        protected ?string $milkType = null,
        protected string|array|null $modeOfProcurement = null,
        protected string|array|null $supplier = null,
        protected string|array|null $province = null,
        protected ?string $yearRange = null,
    ) {}

    private function getBeneficiarySources(array $years): array
    {
        /** @var Builder<BeneficiarySource> $query */
        $query = BeneficiarySource::query();
        $beneficiaries = $query->whereIn('year', $years)
            ->get()
            ->keyBy(function ($item) {
                return $item->code.'_'.$item->year;
            })
            ->toArray();

        $result = [];
        foreach ($years as $year) {
            $result[$year] = [
                MilkFeedingProgramFunderEnum::DEPED->value => $beneficiaries[MilkFeedingProgramFunderEnum::DEPED->value.'_'.$year]['amount'] ?? 0,
                MilkFeedingProgramFunderEnum::DSWD->value => $beneficiaries[MilkFeedingProgramFunderEnum::DSWD->value.'_'.$year]['amount'] ?? 0,
                MilkFeedingProgramFunderEnum::LGU->value => $beneficiaries[MilkFeedingProgramFunderEnum::LGU->value.'_'.$year]['amount'] ?? 0,
                MilkFeedingProgramFunderEnum::LDS->value => $beneficiaries[MilkFeedingProgramFunderEnum::LDS->value.'_'.$year]['amount'] ?? 0,
            ];
        }

        return $result;
    }

    public function title(): string
    {
        return strtoupper($this->fundedBy ?? 'All');
    }

    public function headings(): array
    {
        $years = $this->getYearsToDisplay();
        $headers = ['Parameters'];

        foreach ($years as $year) {
            $nextYear = $year + 1;
            $headers[] = "SY {$year}-{$nextYear}";
        }

        return $headers;
    }

    public function collection()
    {
        $years = $this->getYearsToDisplay();

        if (strtolower($this->fundedBy) === MilkFeedingProgramFunderEnum::DSWD->value) {
            $beneficiarySources = $this->getBeneficiarySources($years);

            return $this->getDSWDCollection($years, $beneficiarySources);
        }

        $data = $this->getAggregatedData($years);
        $beneficiarySources = $this->getBeneficiarySources($years);
        $summaryRows = [];

        $funderLabels = MilkFeedingProgramFunderEnum::beneficiaryLabels();
        $funderToShow = ($this->fundedBy && $this->fundedBy !== 'all' && isset($funderLabels[strtolower($this->fundedBy)]))
            ? strtolower($this->fundedBy)
            : MilkFeedingProgramFunderEnum::DEPED->value;

        // Beneficiary source row
        if (isset($funderLabels[$funderToShow])) {
            $row = ['parameter' => $funderLabels[$funderToShow]];
            foreach ($years as $year) {
                $row[$year] = $beneficiarySources[$year][$funderToShow] ?? 0;
            }
            $summaryRows[] = $row;
        }

        // SDO count
        $row = ['parameter' => 'No. of Schools Division Offices (SDOs)'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['sdo_count'] ?? 0;
        }
        $summaryRows[] = $row;

        // Cooperative count
        $row = ['parameter' => 'No. of Cooperatives engaged in MFP'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['cooperative_count'] ?? 0;
        }
        $summaryRows[] = $row;

        // PCC Commitment (Beneficiaries)
        $row = ['parameter' => 'PCC Commitment (Beneficiaries)'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['total_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        // Pasteurized Milk (beneficiaries)
        $row = ['parameter' => 'Pasteurized Milk'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['pasteurized_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        // Sterilized Milk (beneficiaries)
        $row = ['parameter' => 'Sterilized Milk'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['sterilized_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        // % Commitment
        $row = ['parameter' => '% Commitment'];
        foreach ($years as $year) {
            $targetBeneficiaries = $beneficiarySources[$year][$funderToShow] ?? 0;
            $pccBeneficiaries = $data[$year]['total_beneficiaries'] ?? 0;
            $commitmentPercentage = $targetBeneficiaries > 0
                ? ($pccBeneficiaries / $targetBeneficiaries) * 100
                : 0;
            $row[$year] = number_format($commitmentPercentage, 2, '.', ',').'%';
        }
        $summaryRows[] = $row;

        // No. of Milk Packs
        $row = ['parameter' => 'No. of Milk Packs'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['total_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        // Pasteurized Milk (packs)
        $row = ['parameter' => 'Pasteurized Milk'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['pasteurized_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        // Sterilized Milk (packs)
        $row = ['parameter' => 'Sterilized Milk'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['sterilized_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        // Gross Income
        $row = ['parameter' => 'Gross Income of Dairy Cooperatives, PhP'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['gross_income'] ?? 0;
        }
        $summaryRows[] = $row;

        // % Service Fee
        $row = ['parameter' => '% Service Fee'];
        foreach ($years as $year) {
            $grossIncome = $data[$year]['gross_income'] ?? 0;
            $serviceFeeAmount = $data[$year]['service_fee_amount'] ?? 0;

            if ($grossIncome > 0 && $serviceFeeAmount > 0) {
                $row[$year] = number_format(($serviceFeeAmount / $grossIncome), 2, '.', ',').'%';
            } else {
                $row[$year] = 'N/A';
            }
        }
        $summaryRows[] = $row;

        // Amount of Service Fee
        $row = ['parameter' => 'Amount of Service Fee, PhP'];
        foreach ($years as $year) {
            $serviceFeeAmount = $data[$year]['service_fee_amount'] ?? 0;
            $row[$year] = $serviceFeeAmount > 0 ? $serviceFeeAmount : 'N/A';
        }
        $summaryRows[] = $row;

        // Total Funds Transferred to PCC
        $row = ['parameter' => 'Total Funds Transferred to PCC'];
        foreach ($years as $year) {
            $row[$year] = $data[$year]['total_contract_amount'] ?? 0;
        }
        $summaryRows[] = $row;

        return collect($summaryRows);
    }

    private function getAggregatedData(array $years): array
    {
        /** @var Builder<MilkFeedingProgram> $baseQuery */
        $baseQuery = MilkFeedingProgram::query();
        $baseQuery->whereIn('year', $years);

        if ($this->centerId) {
            is_array($this->centerId)
                ? $baseQuery->whereIn('center_id', $this->centerId)
                : $baseQuery->where('center_id', $this->centerId);
        }
        if ($this->partnerId) {
            is_array($this->partnerId)
                ? $baseQuery->whereIn('partner_id', $this->partnerId)
                : $baseQuery->where('partner_id', $this->partnerId);
        }
        if ($this->fundedBy && $this->fundedBy !== 'all') {
            if (is_array($this->fundedBy)) {
                $fundedByValues = array_map(fn ($fb) => MilkFeedingProgramFunderEnum::tryFrom($fb)?->value ?? $fb, $this->fundedBy);
                $baseQuery->whereIn('funded_by', $fundedByValues);
            } else {
                $baseQuery->where('funded_by', MilkFeedingProgramFunderEnum::tryFrom($this->fundedBy)?->value ?? $this->fundedBy);
            }
        }

        $regionValues = $this->region;
        if ($regionValues) {
            if (is_array($regionValues)) {
                $regionValues = array_map(fn ($reg) => AddressRegionEnum::tryFrom($reg)?->value ?? $reg, $regionValues);
            } else {
                $regionValues = AddressRegionEnum::tryFrom($regionValues)?->value ?? $regionValues;
            }
        }

        $baseQuery = $this->applyAddressFilters($baseQuery, $regionValues, $this->province, null, null);

        if ($this->supplier) {
            is_array($this->supplier)
                ? $baseQuery->whereIn('partner_id', $this->supplier)
                : $baseQuery->where('partner_id', $this->supplier);
        }

        $pmMatches = implode('", "', array_merge([MilkTypeEnum::PM->value], MilkTypeEnum::matches()[MilkTypeEnum::PM->value]));
        $smpMatches = implode('", "', array_merge([MilkTypeEnum::SMP->value], MilkTypeEnum::matches()[MilkTypeEnum::SMP->value]));
        $karabunMatches = implode('", "', array_merge([MilkTypeEnum::KARABUN->value], MilkTypeEnum::matches()[MilkTypeEnum::KARABUN->value]));
        $mtMatches = implode('", "', array_merge([MilkTypeEnum::MILK_TABLE->value], MilkTypeEnum::matches()[MilkTypeEnum::MILK_TABLE->value] ?? [MilkTypeEnum::MILK_TABLE->value]));
        $othersMatches = implode('", "', array_merge([MilkTypeEnum::OTHERS->value], MilkTypeEnum::matches()[MilkTypeEnum::OTHERS->value]));

        $aggregatedData = $baseQuery->select([
            'year',
            DB::raw('SUM(COALESCE(number_of_beneficiaries, 0)) as total_beneficiaries'),
            DB::raw('SUM(COALESCE(milk_packs, 0)) as total_milk_packs'),
            DB::raw('SUM(COALESCE(milk_packs, 0) * COALESCE(price_per_milk_pack, 0)) as gross_income'),
            DB::raw('SUM(COALESCE(service_fee, 0)) as service_fee_amount'),
            DB::raw('SUM(COALESCE(total_contract_amount, 0)) as total_contract_amount'),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$pmMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as pasteurized_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$smpMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as sterilized_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$karabunMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as karabun_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$mtMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as milk_table_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$othersMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as others_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$pmMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as pasteurized_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$smpMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as sterilized_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$karabunMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as karabun_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$mtMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as milk_table_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$othersMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as others_milk_packs"),
        ])
            ->groupBy('year')
            ->get()
            ->keyBy('year')
            ->toArray();

        $countsData = [];
        foreach ($years as $year) {
            $yearQuery = clone $baseQuery;
            $yearQuery->where('year', $year);
            $countsData[$year] = [
                'sdo_count' => $yearQuery->distinct('division')->count('division'),
                'cooperative_count' => (clone $yearQuery)->distinct('partner_id')->whereNotNull('partner_id')->count('partner_id'),
            ];
        }

        $result = [];
        foreach ($years as $year) {
            $result[$year] = array_merge(
                $aggregatedData[$year] ?? [
                    'total_beneficiaries' => 0,
                    'total_milk_packs' => 0,
                    'gross_income' => 0,
                    'service_fee_amount' => 0,
                    'total_contract_amount' => 0,
                    'pasteurized_beneficiaries' => 0,
                    'sterilized_beneficiaries' => 0,
                    'karabun_beneficiaries' => 0,
                    'milk_table_beneficiaries' => 0,
                    'others_beneficiaries' => 0,
                    'pasteurized_milk_packs' => 0,
                    'sterilized_milk_packs' => 0,
                    'karabun_milk_packs' => 0,
                    'milk_table_milk_packs' => 0,
                    'others_milk_packs' => 0,
                ],
                $countsData[$year] ?? ['sdo_count' => 0, 'cooperative_count' => 0]
            );
        }

        return $result;
    }

    private function getDSWDCollection(array $years, array $beneficiarySources): Collection
    {
        /** @var Builder<MilkFeedingProgram> $baseQuery */
        $baseQuery = MilkFeedingProgram::query();
        $baseQuery->whereIn('year', $years)
            ->where('funded_by', MilkFeedingProgramFunderEnum::DSWD->value);

        if ($this->centerId) {
            is_array($this->centerId)
                ? $baseQuery->whereIn('center_id', $this->centerId)
                : $baseQuery->where('center_id', $this->centerId);
        }
        if ($this->partnerId) {
            is_array($this->partnerId)
                ? $baseQuery->whereIn('partner_id', $this->partnerId)
                : $baseQuery->where('partner_id', $this->partnerId);
        }

        $regionValues = $this->region;
        if ($regionValues) {
            if (is_array($regionValues)) {
                $regionValues = array_map(fn ($reg) => AddressRegionEnum::tryFrom($reg)?->value ?? $reg, $regionValues);
            } else {
                $regionValues = AddressRegionEnum::tryFrom($regionValues)?->value ?? $regionValues;
            }
        }

        $baseQuery = $this->applyAddressFilters($baseQuery, $regionValues, $this->province, null, null);

        if ($this->supplier) {
            is_array($this->supplier)
                ? $baseQuery->whereIn('partner_id', $this->supplier)
                : $baseQuery->where('partner_id', $this->supplier);
        }

        $pmMatches = implode('", "', array_merge([MilkTypeEnum::PM->value], MilkTypeEnum::matches()[MilkTypeEnum::PM->value]));
        $smpMatches = implode('", "', array_merge([MilkTypeEnum::SMP->value], MilkTypeEnum::matches()[MilkTypeEnum::SMP->value]));
        $karabunMatches = implode('", "', array_merge([MilkTypeEnum::KARABUN->value], MilkTypeEnum::matches()[MilkTypeEnum::KARABUN->value]));
        $mtMatches = implode('", "', array_merge([MilkTypeEnum::MILK_TABLE->value], MilkTypeEnum::matches()[MilkTypeEnum::MILK_TABLE->value] ?? [MilkTypeEnum::MILK_TABLE->value]));
        $othersMatches = implode('", "', array_merge([MilkTypeEnum::OTHERS->value], MilkTypeEnum::matches()[MilkTypeEnum::OTHERS->value]));

        $aggregatedData = $baseQuery->select([
            'year',
            DB::raw('SUM(COALESCE(milk_packs, 0) * COALESCE(price_per_milk_pack, 0)) as gross_income'),
            DB::raw('SUM(COALESCE(service_fee, 0)) as administrative_cost'),
            DB::raw('SUM(COALESCE(total_contract_amount, 0)) as total_contract_amount'),
            DB::raw('SUM(COALESCE(milk_packs, 0) * COALESCE(price_per_milk_pack, 0) + COALESCE(service_fee, 0)) as total_funds'),
            DB::raw('SUM(COALESCE(number_of_beneficiaries, 0)) as pcc_beneficiaries'),
            DB::raw('SUM(COALESCE(feeding_days, 0)) as feeding_days'),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$pmMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as pasteurized_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$smpMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as sterilized_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$karabunMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as karabun_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$mtMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as milk_table_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$othersMatches}\") THEN COALESCE(number_of_beneficiaries, 0) ELSE 0 END) as others_beneficiaries"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$pmMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as pasteurized_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$smpMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as sterilized_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$karabunMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as karabun_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$mtMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as milk_table_milk_packs"),
            DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(milk_type, ''))) IN (\"{$othersMatches}\") THEN COALESCE(milk_packs, 0) ELSE 0 END) as others_milk_packs"),
        ])
            ->groupBy('year')
            ->get()
            ->keyBy('year')
            ->toArray();

        $countsData = [];
        foreach ($years as $year) {
            $yearQuery = clone $baseQuery;
            $yearQuery->where('year', $year);
            $countsData[$year] = [
                'region_count' => $yearQuery->distinct('division')->count('division'),
                'province_count' => $yearQuery->distinct('elementary_school')->count('elementary_school'),
                'municipality_count' => $yearQuery->distinct('elementary_school')->count('elementary_school'),
                'cooperative_count' => $yearQuery->distinct('partner_id')->whereNotNull('partner_id')->count('partner_id'),
                'cycle' => $year - 2010,
                'feeding_days' => $aggregatedData[$year]['feeding_days'] ?? 120,
            ];
        }

        $summaryRows = [];

        $row = ['parameter' => 'Cycle'];
        foreach ($years as $year) {
            $row[$year] = ($countsData[$year]['cycle'] ?? 0).'th';
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Feeding Days'];
        foreach ($years as $year) {
            $row[$year] = $countsData[$year]['feeding_days'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'DSWD No. of Beneficiaries'];
        foreach ($years as $year) {
            $row[$year] = $beneficiarySources[$year][MilkFeedingProgramFunderEnum::DSWD->value] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Region'];
        foreach ($years as $year) {
            $row[$year] = $countsData[$year]['region_count'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Provinces'];
        foreach ($years as $year) {
            $row[$year] = $countsData[$year]['province_count'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Municipalities/ Cities'];
        foreach ($years as $year) {
            $row[$year] = $countsData[$year]['municipality_count'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Cooperatives engaged in MFP'];
        foreach ($years as $year) {
            $row[$year] = $countsData[$year]['cooperative_count'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'PCC Commitment (Beneficiaries)'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['pcc_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Pasteurized Milk'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['pasteurized_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Sterilized Milk'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['sterilized_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Karabun'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['karabun_beneficiaries'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => '% Commitment'];
        foreach ($years as $year) {
            $dswdBeneficiaries = $beneficiarySources[$year][MilkFeedingProgramFunderEnum::DSWD->value] ?? 0;
            $pccBeneficiaries = $aggregatedData[$year]['pcc_beneficiaries'] ?? 0;
            $commitmentPercentage = $dswdBeneficiaries > 0
                ? ($pccBeneficiaries / $dswdBeneficiaries) * 100
                : 0;
            $row[$year] = number_format($commitmentPercentage, 2, '.', ',').'%';
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'No. of Milk Packs'];
        foreach ($years as $year) {
            $row[$year] = ($aggregatedData[$year]['pasteurized_milk_packs'] ?? 0)
                + ($aggregatedData[$year]['sterilized_milk_packs'] ?? 0)
                + ($aggregatedData[$year]['karabun_milk_packs'] ?? 0);
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Pasteurized Milk'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['pasteurized_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Sterilized Milk'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['sterilized_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Karabun'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['karabun_milk_packs'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Gross Income of Dairy Cooperatives, PhP'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['gross_income'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Administrative Cost, PhP'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['administrative_cost'] ?? 0;
        }
        $summaryRows[] = $row;

        $row = ['parameter' => 'Total Funds Transferred to PCC'];
        foreach ($years as $year) {
            $row[$year] = $aggregatedData[$year]['total_contract_amount'] ?? 0;
        }
        $summaryRows[] = $row;

        return collect($summaryRows);
    }

    private function getYearsToDisplay(): array
    {
        if ($this->year) {
            return is_array($this->year) ? array_map('intval', $this->year) : [(int) $this->year];
        }

        if ($this->yearRange) {
            $years = explode('-', $this->yearRange);
            if (count($years) === 1) {
                return [(int) $years[0]];
            } elseif (count($years) === 2) {
                return range((int) $years[0], (int) $years[1]);
            }
        }

        /** @var Builder<MilkFeedingProgram> $query */
        $query = MilkFeedingProgram::query();

        if ($this->fundedBy && $this->fundedBy !== 'all') {
            if (is_array($this->fundedBy)) {
                $fundedByValues = array_map(fn ($fb) => MilkFeedingProgramFunderEnum::tryFrom($fb)?->value ?? $fb, $this->fundedBy);
                $query->whereIn('funded_by', $fundedByValues);
            } else {
                $query->where('funded_by', MilkFeedingProgramFunderEnum::tryFrom($this->fundedBy)?->value ?? $this->fundedBy);
            }
        }

        return $query
            ->selectRaw('DISTINCT year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();
    }

    public function map($row): array
    {
        $years = $this->getYearsToDisplay();
        $mapped = [$row['parameter']];

        foreach ($years as $year) {
            $value = $row[$year] ?? 0;
            $rawValue = is_string($value) ? str_replace(['%', ','], '', $value) : $value;
            $numericValue = is_numeric($rawValue) ? (float) $rawValue : 0;
            $param = $row['parameter'];

            if ($value === 'N/A') {
                $mapped[] = 'N/A';
            } elseif (str_contains($param, 'PhP') || str_contains($param, 'Income') || str_contains($param, 'Cost') || str_contains($param, 'Amount') || str_contains($param, 'Funds')) {
                $mapped[] = number_format($numericValue, 2, '.', ',');
            } elseif (str_contains($param, '%')) {
                $mapped[] = number_format($numericValue, 2, '.', ',').'%';
            } elseif (str_contains($param, 'Cycle')) {
                $mapped[] = $value;
            } else {
                $mapped[] = number_format($numericValue, 0, '.', ',');
            }
        }

        return $mapped;
    }

    public function columnWidths(): array
    {
        $years = $this->getYearsToDisplay();
        $columnWidths = ['A' => 65];
        $columnLetter = 'B';

        foreach ($years as $year) {
            $columnWidths[$columnLetter] = 32;
            $columnLetter++;
        }

        return $columnWidths;
    }

    public function styles(Worksheet $sheet)
    {
        $years = $this->getYearsToDisplay();
        $totalRows = $sheet->getHighestRow();
        $lastColumn = chr(ord('A') + count($years));

        for ($i = 1; $i <= $totalRows; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(42);
        }
        $sheet->getRowDimension(1)->setRowHeight(52);

        for ($i = 1; $i <= $totalRows; $i++) {
            $sheet->getStyle("A{$i}")->getAlignment()->setIndent(2);
        }

        $sheet->freezePane('A2');

        return [

            "A1:{$lastColumn}{$totalRows}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'font' => [
                    'name' => 'Arial',
                    'size' => 14,
                ],
            ],

            "A1:{$lastColumn}1" => [
                'font' => [
                    'bold' => true,
                    'name' => 'Arial',
                    'size' => 14,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'f0f0f0'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],

            "A1:A{$totalRows}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}

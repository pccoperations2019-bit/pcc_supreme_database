<?php

namespace App\Actions\Project;

use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkFeedingStatusEnum;
use App\Enums\MilkSubcategoryEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Enums\ProgramEnum;
use App\Models\MilkFeedingProgram;

class UpsertMilkFeedingProgramAction
{
    public function execute(
        int $projectId,
        int $centerId,
        ?int $partnerId,
        string $name,
        ?string $description,
        int $year,
        ?MilkFeedingProgramFunderEnum $fundedBy,
        ?string $elementarySchool,
        ?string $division,
        ?ProgramEnum $program,
        int $milkPacks,
        ?int $batch,
        float $totalVolumeRequirements,
        float $rawMilkUsed,
        float $wholeMilk,
        float $skimmedMilk,
        float $sugar,
        ?MilkTypeEnum $milkType,
        ?MilkSubcategoryEnum $milkSubcategory,
        float $pricePerMilkPack,
        int $feedingDays,
        int $numberOfBeneficiaries,
        float $totalContractAmount,
        float $serviceFee,
        float $grossIncome,
        ?string $liquidationDate,
        ?ModeOfProcurementEnum $modeOfProcurement,
        ?string $moaSigningDate,
        ?string $fundTransferDate,
        ?string $dateStarted,
        ?string $dateCompleted,
        MilkFeedingStatusEnum $status
    ): MilkFeedingProgram {
        return MilkFeedingProgram::updateOrCreate(
            [
                'project_id' => $projectId,
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'name' => $name,
                'total_volume_requirements' => $totalVolumeRequirements,
                'gross_income' => $grossIncome,
                'date_started' => $dateStarted,
                'date_completed' => $dateCompleted,
            ],
            array_filter([
                'description' => $description,
                'elementary_school' => $elementarySchool,
                'division' => $division,
                'program' => $program?->value,
                'year' => $year,
                'funded_by' => $fundedBy?->value,
                'milk_packs' => $milkPacks,
                'raw_milk_used' => $rawMilkUsed,
                'whole_milk' => $wholeMilk,
                'skimmed_milk' => $skimmedMilk,
                'sugar' => $sugar,
                'batch' => $batch,
                'milk_type' => $milkType?->value,
                'milk_subcategory' => $milkSubcategory?->value,
                'price_per_milk_pack' => $pricePerMilkPack,
                'feeding_days' => $feedingDays,
                'number_of_beneficiaries' => $numberOfBeneficiaries,
                'total_contract_amount' => $totalContractAmount,
                'service_fee' => $serviceFee,
                'liquidation_date' => $liquidationDate,
                'mode_of_procurement' => $modeOfProcurement?->value,
                'moa_signing_date' => $moaSigningDate,
                'fund_transfer_date' => $fundTransferDate,
                'date_started' => $dateStarted,
                'date_completed' => $dateCompleted,
                'status' => $status->value,
            ], fn ($value) => ! is_null($value))
        );
    }
}

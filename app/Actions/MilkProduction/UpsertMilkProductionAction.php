<?php

namespace App\Actions\MilkProduction;

use App\Enums\MilkingTimeEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\MilkProduction;
use DateTimeInterface;

class UpsertMilkProductionAction
{
    public function execute(
        ?string $productionCode,
        ?int $centerId,
        ?int $partnerId,
        ?int $farmerId,
        DateTimeInterface $transactionDate,
        ?DateTimeInterface $durationStart,
        ?DateTimeInterface $durationEnd,
        ?float $totalVolume = null,
        ?int $volumeFromFarmers = null,
        MilkingTimeEnum $milkingTimes = MilkingTimeEnum::DEFAULT,
        ?int $numberOfLactating = 0,
        ?float $avgMilkPerAnimalPerDay = null,
        ProgramEnum $program = ProgramEnum::DEFAULT,
        ProductionStatusEnum $status = ProductionStatusEnum::DEFAULT,
        ?string $notes = null,
        ?int $year = null,
        ?int $month = null,
        ?int $quarter = null,
        int|MilkProductionWeekEnum|null $week = null,
        ?string $idNumber = null,
        ?DateTimeInterface $collectionDate = null,
        ?int $herdId = null,
        ?float $amVolume = null,
        ?float $pmVolume = null,
        ?string $sourceOfMilk = null,
        ?int $f1 = null,
        ?int $f2 = null
    ): MilkProduction {
        $weekEnum = is_int($week) ? MilkProductionWeekEnum::fromInt($week) : $week;

        if (! $weekEnum instanceof MilkProductionWeekEnum) {
            if ($durationStart instanceof DateTimeInterface) {
                $weekEnum = MilkProductionWeekEnum::fromDate($durationStart);
            } elseif ($transactionDate instanceof DateTimeInterface) {
                $weekEnum = MilkProductionWeekEnum::fromDate($transactionDate);
            }
        }

        if ($year === null && $transactionDate instanceof DateTimeInterface) {
            $year = (int) $transactionDate->format('Y');
        }

        $weekValue = $weekEnum?->value ?? $week;
        $transactionDateValue = $transactionDate instanceof DateTimeInterface
            ? $transactionDate->format('Y-m-d')
            : $transactionDate;

        return MilkProduction::updateOrCreate(
            [
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'program' => $program->value,
                'year' => $year,
                'week' => $weekValue,
                'transaction_date' => $transactionDateValue,
            ],
            array_filter([
                'production_code' => $productionCode,
                'id_number' => $idNumber,
                'collection_date' => $collectionDate,
                'herd_id' => $herdId,
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'farmer_id' => $farmerId,
                'period_start' => $durationStart,
                'period_end' => $durationEnd,
                'am_volume' => $amVolume,
                'pm_volume' => $pmVolume,
                'total_volume_produced' => $totalVolume,
                'volume_from_farmers' => $volumeFromFarmers,
                'milking_times' => $milkingTimes,
                'source_of_milk' => $sourceOfMilk,
                'f1' => $f1,
                'f2' => $f2,
                'program' => $program->value,
                'number_of_lactating' => $numberOfLactating,
                'avg_milk_per_animal_per_day' => $avgMilkPerAnimalPerDay,
                'status' => $status,
                'notes' => $notes,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'week' => $weekValue,
                'transaction_date' => $transactionDateValue,
            ], fn ($value) => ! is_null($value))
        );
    }
}

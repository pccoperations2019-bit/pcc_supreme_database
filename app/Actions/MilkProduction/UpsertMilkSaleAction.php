<?php

namespace App\Actions\MilkProduction;

use App\Models\MilkSale;

class UpsertMilkSaleAction
{
    public function execute(
        ?int $milkProductionId,
        ?int $centerId = null,
        ?int $partnerId = null,
        ?int $farmerId = null,
        ?\DateTimeInterface $transactionDate = null,
        ?int $year = null,
        ?int $month = null,
        ?int $quarter = null,
        ?int $week = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        float $totalVolumeSold = 0,
        float $farmGatePrice = 0,
        float $rawMilkTradedSales = 0,
        float $valueAddedProductsSales = 0,
        float $dairyBoxSales = 0,
        float $milkSales = 0,
        float $milkFeedingSales = 0,
        float $salesOfValueAdded = 0,
        float $valueOfTotalMilkProduction = 0,
        ?string $program = null
    ): MilkSale {
        $transactionDateValue = $transactionDate instanceof \DateTimeInterface
            ? $transactionDate->format('Y-m-d')
            : $transactionDate;

        $payload = array_filter([
            'milk_production_id' => $milkProductionId,
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'farmer_id' => $farmerId,
            'transaction_date' => $transactionDateValue,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
            'week' => $week,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_volume_sold' => $totalVolumeSold,
            'farm_gate_price' => $farmGatePrice,
            'raw_milk_traded_sales' => $rawMilkTradedSales,
            'value_added_products_sales' => $valueAddedProductsSales,
            'dairy_box_sales' => $dairyBoxSales,
            'milk_sales' => $milkSales,
            'milk_feeding_sales' => $milkFeedingSales,
            'sales_of_value_added' => $salesOfValueAdded,
            'value_of_total_milk_production' => $valueOfTotalMilkProduction,
            'program' => $program,
        ], fn ($value) => ! is_null($value));

        if ($milkProductionId) {
            $existing = MilkSale::query()
                ->where('milk_production_id', $milkProductionId)
                ->first();

            if ($existing) {
                $existing->fill($payload)->save();

                return $existing;
            }
        }

        // Build query based on data type (weekly vs monthly)
        $query = MilkSale::query()
            ->where('center_id', $centerId)
            ->where('partner_id', $partnerId);

        if ($program !== null) {
            $query->where('program', $program);
        }

        // For monthly data (week is null), use year-month combination
        if ($week === null && $month !== null) {
            $query->where('year', $year)
                ->where('month', $month);
        }
        // For weekly data, use year-week combination
        else {
            $query->where('year', $year)
                ->where('week', $week);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->fill($payload)->save();

            return $existing;
        }

        return MilkSale::create($payload);
    }
}

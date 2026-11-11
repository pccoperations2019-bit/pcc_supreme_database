<?php

namespace App\Actions\MilkConsolidation;

use App\Models\MilkConsolidation;
use DateTimeInterface;

class UpsertMilkConsolidationAction
{
    public function execute(
        int $centerId,
        ?int $partnerId,
        ?int $farmerId,
        ?string $supplierName,
        ?DateTimeInterface $periodStart,
        ?DateTimeInterface $periodEnd,
        float $totalRawMilk = 0,
        float $totalFromCenter = 0,
        float $totalFromPartners = 0,
        float $totalFromFarmers = 0,
        float $totalFromSuppliers = 0,
        ?float $averageBuyingPrice = null,
        ?float $totalCost = null,
        ?float $averageSellingPrice = null,
        ?float $revenueGenerated = null,
        ?float $processingCost = null,
        ?array $metadata = null,
        ?int $createdBy = null
    ): MilkConsolidation {
        $searchAttributes = [
            'center_id' => $centerId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];

        $updateAttributes = array_filter([
            'partner_id' => $partnerId,
            'farmer_id' => $farmerId,
            'supplier_name' => $supplierName,
            'total_raw_milk' => $totalRawMilk,
            'total_from_center' => $totalFromCenter,
            'total_from_partners' => $totalFromPartners,
            'total_from_farmers' => $totalFromFarmers,
            'total_from_suppliers' => $totalFromSuppliers,
            'average_buying_price' => $averageBuyingPrice,
            'total_cost' => $totalCost,
            'average_selling_price' => $averageSellingPrice,
            'revenue_generated' => $revenueGenerated,
            'processing_cost' => $processingCost,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);

        $milkConsolidation = MilkConsolidation::updateOrCreate(
            $searchAttributes,
            $updateAttributes
        );

        if ($centerId !== 0) {
            $milkConsolidation->center()->associate($centerId);
            $milkConsolidation->save();
        }
        if ($partnerId) {
            $milkConsolidation->partner()->associate($partnerId);
            $milkConsolidation->save();
        }
        if ($farmerId) {
            $milkConsolidation->farmer()->associate($farmerId);
            $milkConsolidation->save();
        }

        return $milkConsolidation;
    }
}

<?php

namespace App\Actions\DairyBox;

use App\Models\DairyBoxSale;

class UpsertDairyBoxSaleAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $dairyBoxId,
        ?int $centerId,
        ?int $partnerId,
        ?\DateTime $periodStart,
        ?\DateTime $periodEnd,
        ?float $totalSales = null,
        ?int $year = null
    ): DairyBoxSale {

        $dairyBoxSale = DairyBoxSale::updateOrCreate(
            [
                'dairy_box_id' => $dairyBoxId,
                'period_start' => $periodStart?->format('Y-m-d'),
                'period_end' => $periodEnd?->format('Y-m-d'),
                'year' => $year,
            ],
            array_filter([
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'total_sales' => $totalSales,
            ], fn ($value) => ! is_null($value))
        );

        if ($centerId) {
            $dairyBoxSale->center()->associate($centerId);
        }

        if ($partnerId) {
            $dairyBoxSale->partner()->associate($partnerId);
        }

        $dairyBoxSale->save();

        return $dairyBoxSale;
    }
}

<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\MilkProduction;
use Illuminate\Http\Request;

class IHDashbordMetricController extends Controller
{
    /**
     * Display the resource.
     */
    public function show(Request $request)
    {
        /**
         * @todo: Add to Query Class IHDashboardMetricsQuery
         */
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $centerId = $request->query('center_id');
        $centerIds = $request->query('center_ids', []);

        if (is_string($centerIds)) {
            $centerIds = explode(',', $centerIds);
        }
        $query = MilkProduction::query()
            ->unless(empty($centerIds), function ($query) use ($centerIds) {
                $query->whereIn('center_id', $centerIds);
            })
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('center_id', $centerId);
            })->when($fromDate, function ($query) use ($fromDate) {
                $query->whereDate('transaction_date', '>=', $fromDate);
            })->when($toDate, function ($query) use ($toDate) {
                $query->whereDate('transaction_date', '<=', $toDate);
            });

        $avgFarmGatePrice = $query->join('milk_sales', 'milk_productions.id', '=', 'milk_sales.milk_production_id')
            ->avg('milk_sales.farm_gate_price') ?? 0;

        return response()->json([
            'total_milk_volume' => $query->sum('total_volume'),
            'avg_milk_per_animal_per_day' => $query->avg('avg_milk_per_animal_per_day'),
            'avg_number_of_lactating' => $query->avg('number_of_lactating'),
            'avg_milk_value' => $query->sum('total_volume') * $avgFarmGatePrice,
        ]);
    }
}

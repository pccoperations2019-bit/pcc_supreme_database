<?php

namespace App\Http\Controllers\Xhr;

use App\Enums\CenterEnum;
use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\MilkProduction;
use Illuminate\Http\Request;

class IHDashbordProductionChartController extends Controller
{
    /**
     * Display the resource.
     */
    public function show(Request $request)
    {
        /**
         * @todo: Add to Query Class IHDashboardProductionChartQuery
         */
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $centerId = $request->query('center_id');
        $centerIds = $request->query('center_ids', []);

        if (is_string($centerIds)) {
            $centerIds = explode(',', $centerIds);
        }

        $centerCodes = Center::query()
            ->unless(empty($centerIds), function ($query) use ($centerIds) {
                $query->whereIn('id', $centerIds);
            })
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('id', $centerId);
            })
            ->pluck('code', 'id')
            ->toArray();
        // dd($centerCodes);

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

        $dataArray = $query->get()->mapToGroups(function ($item) use ($centerCodes) {
            $key = $centerCodes[$item->center_id].'-'.$item->transaction_date->format('M Y');

            return [
                $key => $item->total_volume,
            ];
        })->toArray();

        // / dd($data);

        /**
         * array:13 [▼ // app/Http/Controllers/Xhr/IHDashbordProductionChartController.php:60
  "CLSU-Jan 2024" => array:6 [▼
    0 => 856
    1 => 8368
    2 => 6778
    3 => 1802
    4 => 4803
    5 => 6483
         */
        $data = [];
        $dates = [];
        foreach ($dataArray as $key => $values) {
            [$_, $date] = explode('-', $key);
            $dates[] = $date;
        }

        // Generate random data for each center (this is just a placeholder)
        foreach ($dates as $month) {
            $row = [];
            foreach (CenterEnum::cases() as $center) {
                if (! in_array($center->code(), $centerCodes)) {
                    continue;
                }
                $row['date'] = $month;
                $key = $center->code().'-'.$month;
                $row[$center->code()] = array_sum($dataArray[$key] ?? [0]);
            }
            $data[] = $row;
        }
        // Prepare categories (center codes)
        $categories = array_values(array_filter(
            array_map(function ($center) {
                return $center->code();
            }, CenterEnum::cases()),
            function ($code) use ($centerCodes) {
                return in_array($code, $centerCodes);
            }
        ));

        $colors = array_values(array_reduce(CenterEnum::cases(), function ($carry, $center) use ($centerCodes) {
            if (in_array($center->code(), $centerCodes)) {
                $carry[$center->code()] = $center->color();
            }

            return $carry;
        }, []));

        return [
            'categories' => array_filter($categories, fn ($value) => in_array($value, $centerCodes)),
            'colors' => $colors,
            'data' => $data,
        ];
    }
}

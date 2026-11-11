<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;

class CBEDDashbordChartController extends Controller
{
    /**
     * Display the resource.
     */
    public function show()
    {
        /*
        PRODUUCTION PREFORMANCE PLACEHODERS convert this to php */
        return [
            ['name' => 'Jan', 'total' => rand(1000, 6000)],
            ['name' => 'Feb', 'total' => rand(1000, 6000)],
            ['name' => 'Mar', 'total' => rand(1000, 6000)],
            ['name' => 'Apr', 'total' => rand(1000, 6000)],
            ['name' => 'May', 'total' => rand(1000, 6000)],
            ['name' => 'Jun', 'total' => rand(1000, 6000)],
            ['name' => 'Jul', 'total' => rand(1000, 6000)],
            ['name' => 'Aug', 'total' => rand(1000, 6000)],
            ['name' => 'Sep', 'total' => rand(1000, 6000)],
            ['name' => 'Oct', 'total' => rand(1000, 6000)],
            ['name' => 'Nov', 'total' => rand(1000, 6000)],
            ['name' => 'Dec', 'total' => rand(1000, 6000)],
        ];
    }
}

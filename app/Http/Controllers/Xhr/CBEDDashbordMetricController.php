<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Farmer;
use App\Models\Partner;

class CBEDDashbordMetricController extends Controller
{
    /**
     * Display the resource.
     */
    public function show()
    {
        return response()->json([
            'total_centers' => Center::count(),
            'total_partners' => Partner::count(),
            'total_farmers' => Farmer::count(),
            'total_services' => 1000, // Placeholder value
        ]);
    }
}

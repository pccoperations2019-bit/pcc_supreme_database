<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\Center;

class CBEDDashbordLeaderboardController extends Controller
{
    /**
     * Display the resource.
     */
    public function show()
    {
        return response()->json([
            'top10_by_partners' => Center::orderByDesc('total_partners')->take(10)->get(['id', 'name', 'total_partners']),
            'top10_by_farmers' => Center::orderByDesc('total_farmers')->take(10)->get(['id', 'name', 'total_farmers']),
        ]);
    }
}

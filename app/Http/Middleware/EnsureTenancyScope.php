<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureTenancyScope
{
    public function handle(Request $request, Closure $next)
    {

        $tenant = resolve(TenantContext::class);
        Inertia::share('tenant', [
            'agency_id' => $tenant->agencyId(),
            'center_id' => $tenant->centerId(),
            'selected_center_id' => $tenant->selectedCenterId(),
            'isAgencyLevel' => $tenant->isAgencyLevel(),
            'isCenterLevel' => $tenant->isCenterLevel(),
        ]);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}

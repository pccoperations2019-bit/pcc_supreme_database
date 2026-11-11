<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsCenterGoalTable;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreCenterProductionGoalRequest;
use App\Http\Requests\UpdateCenterProductionGoalRequest;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CenterProductionGoalController extends Controller
{
    use BuildsCenterGoalTable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Inertia::render('CenterProductionGoals/Index', $this->buildCenterGoalTableData($request, 'production'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);
        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $availableYears = CenterProductionGoal::query()
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        $currentYear = (int) date('Y');

        return Inertia::render('CenterProductionGoals/Create', [
            'centers' => $centers,
            'defaultCenterId' => $user && $user->isCenterScoped()
                ? (int) $user->center_id
                : null,
            'availableYears' => $availableYears,
            'currentYear' => $currentYear,
            'programs' => ProgramEnum::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCenterProductionGoalRequest $request)
    {
        $validated = $request->validated();

        // Check for duplicate center_id + year + program combination
        $exists = CenterProductionGoal::where('center_id', '=', $validated['center_id'], 'and')
            ->where('year', '=', $validated['year'], 'and')
            ->where('program', '=', $validated['program'] ?? ProgramEnum::IH, 'and')
            ->exists();

        if ($exists) {
            return back()->withErrors(['year' => 'A production goal already exists for this center, year, and program.'])->withInput();
        }

        CenterProductionGoal::create($validated);

        return to_route('center-production-goals.index')
            ->with('success', 'Production goal created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CenterProductionGoal $centerProductionGoal)
    {
        $centerProductionGoal->load('center');

        return Inertia::render('CenterProductionGoals/Show', [
            'goal' => $centerProductionGoal,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, CenterProductionGoal $centerProductionGoal)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);
        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $centerProductionGoal->load('center');

        $availableYears = CenterProductionGoal::query()
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->get()
            ->pluck('year')
            ->toArray();

        return Inertia::render('CenterProductionGoals/Edit', [
            'goal' => $centerProductionGoal,
            'centers' => $centers,
            'availableYears' => $availableYears,
            'programs' => ProgramEnum::options(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCenterProductionGoalRequest $request, CenterProductionGoal $centerProductionGoal)
    {
        $validated = $request->validated();

        // Check for duplicate center_id + year + program combination (excluding current record)
        $exists = CenterProductionGoal::where('center_id', '=', $validated['center_id'], 'and')
            ->where('year', '=', $validated['year'], 'and')
            ->where('program', '=', $validated['program'] ?? ProgramEnum::IH, 'and')
            ->where('id', '!=', $centerProductionGoal->id, 'and')
            ->exists();

        if ($exists) {
            return back()->withErrors(['year' => 'A production goal already exists for this center, year, and program.'])->withInput();
        }

        $centerProductionGoal->update($validated);

        // Explicit destination: `back()` resolves from the session's previous
        // URL, which Inertia visits never update (they send X-Requested-With),
        // so it lands wherever the last full page load was.
        return to_route('center-production-goals.index')
            ->with('success', 'Production goal updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CenterProductionGoal $centerProductionGoal)
    {
        CenterProductionGoal::destroy($centerProductionGoal->getKey());

        return to_route('center-production-goals.index')
            ->with('success', 'Production goal deleted successfully.');
    }
}

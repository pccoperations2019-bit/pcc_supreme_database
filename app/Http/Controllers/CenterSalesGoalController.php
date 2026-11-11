<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsCenterGoalTable;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreCenterSalesGoalRequest;
use App\Http\Requests\UpdateCenterSalesGoalRequest;
use App\Models\Center;
use App\Models\CenterSalesGoal;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CenterSalesGoalController extends Controller
{
    use BuildsCenterGoalTable;

    /**
     * Sales goals are shown on the same table as production goals, just in the
     * Sales mode, so the index reuses that view.
     */
    public function index(Request $request)
    {
        return Inertia::render('CenterSalesGoals/Index', $this->buildCenterGoalTableData($request, 'sales'));
    }

    /**
     * Display the specified sales goal.
     */
    public function show(Request $request, CenterSalesGoal $centerSalesGoal)
    {
        $centerSalesGoal->load('center');

        return Inertia::render('CenterSalesGoals/show', [
            'goal' => $centerSalesGoal,
        ]);
    }

    /**
     * Show the form for creating a new sales goal.
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

        $availableYears = CenterSalesGoal::query()
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->get()
            ->pluck('year')
            ->toArray();

        return Inertia::render('CenterSalesGoals/Create', [
            'centers' => $centers,
            'availableYears' => $availableYears,
            'currentYear' => (int) date('Y'),
            'programs' => ProgramEnum::options(),
        ]);
    }

    /**
     * Store a newly created sales goal in storage.
     */
    public function store(StoreCenterSalesGoalRequest $request)
    {
        $validated = $request->validated();

        $exists = CenterSalesGoal::where('center_id', '=', $validated['center_id'], 'and')
            ->where('year', '=', $validated['year'], 'and')
            ->where('program', '=', $validated['program'] ?? ProgramEnum::IH, 'and')
            ->exists();

        if ($exists) {
            return back()->withErrors(['year' => 'A sales goal already exists for this center, year, and program.'])->withInput();
        }

        CenterSalesGoal::create($validated);

        return to_route('center-sales-goals.index')
            ->with('success', 'Sales goal created successfully.');
    }

    /**
     * Show the form for editing the specified sales goal.
     */
    public function edit(Request $request, CenterSalesGoal $centerSalesGoal)
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

        $centerSalesGoal->load('center');

        $availableYears = CenterSalesGoal::query()
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->get()
            ->pluck('year')
            ->toArray();

        return Inertia::render('CenterSalesGoals/Edit', [
            'goal' => $centerSalesGoal,
            'centers' => $centers,
            'availableYears' => $availableYears,
            'programs' => ProgramEnum::options(),
        ]);
    }

    /**
     * Update the specified sales goal in storage.
     */
    public function update(UpdateCenterSalesGoalRequest $request, CenterSalesGoal $centerSalesGoal)
    {
        $validated = $request->validated();

        $exists = CenterSalesGoal::where('center_id', '=', $validated['center_id'], 'and')
            ->where('year', '=', $validated['year'], 'and')
            ->where('program', '=', $validated['program'] ?? ProgramEnum::IH, 'and')
            ->where('id', '!=', $centerSalesGoal->id, 'and')
            ->exists();

        if ($exists) {
            return back()->withErrors(['year' => 'A sales goal already exists for this center, year, and program.'])->withInput();
        }

        $centerSalesGoal->update($validated);

        // Explicit destination: `back()` resolves from the session's previous
        // URL, which Inertia visits never update (they send X-Requested-With),
        // so it lands wherever the last full page load was.
        return to_route('center-sales-goals.index')
            ->with('success', 'Sales goal updated successfully.');
    }

    /**
     * Remove the specified sales goal from storage.
     */
    public function destroy(CenterSalesGoal $centerSalesGoal)
    {
        CenterSalesGoal::destroy($centerSalesGoal->getKey());

        return to_route('center-sales-goals.index')
            ->with('success', 'Sales goal deleted successfully.');
    }
}

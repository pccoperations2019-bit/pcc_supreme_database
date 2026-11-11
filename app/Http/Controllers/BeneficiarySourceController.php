<?php

namespace App\Http\Controllers;

use App\Enums\MilkFeedingProgramFunderEnum;
use App\Http\Requests\BeneficiarySourceRequest;
use App\Models\BeneficiarySource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BeneficiarySourceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BeneficiarySource::class);

        $validated = $request->validate([
            'filter.year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = $validated['filter']['year'] ?? null;

        return Inertia::render('BeneficiarySources/Index', [
            'sources' => BeneficiarySource::query()
                ->when($year, fn ($query) => $query->where('year', $year))
                ->orderByDesc('year')->orderBy('name')->orderBy('id')
                ->paginate(10)->withQueryString(),
            'availableYears' => BeneficiarySource::query()->distinct()->orderByDesc('year')->pluck('year'),
            'sourceLabels' => MilkFeedingProgramFunderEnum::labels(),
            'sourceNames' => MilkFeedingProgramFunderEnum::beneficiaryName(),
            'filters' => ['year' => $year],
        ]);
    }

    public function create()
    {
        $this->authorize('create', BeneficiarySource::class);

        return Inertia::render('BeneficiarySources/Create', [
            'currentYear' => (int) date('Y'),
            'sourceLabels' => MilkFeedingProgramFunderEnum::labels(),
            'sourceNames' => MilkFeedingProgramFunderEnum::beneficiaryName(),
        ]);
    }

    public function store(BeneficiarySourceRequest $request)
    {
        BeneficiarySource::create($request->validated());

        return to_route('beneficiary-sources.index')->with('success', 'Beneficiary source created successfully.');
    }

    public function show(BeneficiarySource $beneficiarySource)
    {
        $this->authorize('view', $beneficiarySource);

        return Inertia::render('BeneficiarySources/Show', [
            'source' => $beneficiarySource,
            'sourceLabels' => MilkFeedingProgramFunderEnum::labels(),
            'sourceNames' => MilkFeedingProgramFunderEnum::beneficiaryName(),
        ]);
    }

    public function edit(BeneficiarySource $beneficiarySource)
    {
        $this->authorize('update', $beneficiarySource);

        return Inertia::render('BeneficiarySources/Edit', [
            'source' => $beneficiarySource,
            'sourceLabels' => MilkFeedingProgramFunderEnum::labels(),
            'sourceNames' => MilkFeedingProgramFunderEnum::beneficiaryName(),
        ]);
    }

    public function update(BeneficiarySourceRequest $request, BeneficiarySource $beneficiarySource)
    {
        $beneficiarySource->update($request->validated());

        return to_route('beneficiary-sources.index')->with('success', 'Beneficiary source updated successfully.');
    }

    public function destroy(BeneficiarySource $beneficiarySource)
    {
        $this->authorize('delete', $beneficiarySource);

        $beneficiarySource->delete();

        return to_route('beneficiary-sources.index')->with('success', 'Beneficiary source deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ConsolidationCategoryEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Http\Requests\StoreProcessingUnitRequest;
use App\Http\Requests\UpdateProcessingUnitRequest;
use App\Models\Center;
use App\Models\Partner;
use App\Models\ProcessingUnit;
use Inertia\Inertia;

class ProcessingUnitController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProcessingUnitRequest $request, ?Center $center = null, ?Partner $partner = null)
    {
        $unit = new ProcessingUnit;

        $this->associateOwner($unit, $center, $partner);

        $unit->fill($request->validated())->save();

        return back()->with('success', 'Processing unit created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProcessingUnit $processingUnit)
    {
        $owner = $processingUnit->center_id
            ? $processingUnit->center
            : $processingUnit->partner;

        $owner->load(['productionUnits', 'consolidationUnits', 'processingUnits', 'marketingUnits']);

        $view = $processingUnit->center_id ? 'Centers/Edit' : 'Partners/Edit';
        $key = $processingUnit->center_id ? 'center' : 'partner';

        return Inertia::render($view, [
            $key => $owner,
            'products' => $owner->products()->get(),
            'enums' => $this->enumLabels(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProcessingUnitRequest $request, $parent, ProcessingUnit $processingUnit)
    {
        $this->authorizeParent($request, $processingUnit);

        $processingUnit->update($request->validated());

        return back()->with('success', 'Processing unit updated successfully.');
    }

    public function destroy($parent, ProcessingUnit $processingUnit)
    {
        $processingUnit->delete();

        return back()->with('success', 'Processing unit deleted successfully.');
    }

    private function enumLabels(): array
    {
        return [
            'productionCategory' => ProductionCategoryEnum::labels(),
            'herdType' => HerdTypeEnum::labels(),
            'consolidationCategory' => ConsolidationCategoryEnum::labels(),
            'processingCategory' => ProcessingCategoryEnum::labels(),
            'productCategory' => ProductCategoryEnum::labels(),
            'productSubCategory' => ProductSubCategoryEnum::labels(),
        ];
    }

    private function authorizeParent(UpdateProcessingUnitRequest $request, ProcessingUnit $processingUnit): void
    {
        if ($center = $request->route('center')) {
            abort_unless($processingUnit->center_id == $center, 404);
        } elseif ($partner = $request->route('partner')) {
            abort_unless($processingUnit->partner_id == $partner, 404);
        } else {
            abort(404);
        }
    }

    private function associateOwner(ProcessingUnit $unit, ?Center $center, ?Partner $partner): void
    {
        if ($partner instanceof Partner) {
            $unit->partner()->associate($partner);
            $center = $partner->center ?? $center;
        }

        if ($center) {
            $unit->center()->associate($center);
        }
    }
}

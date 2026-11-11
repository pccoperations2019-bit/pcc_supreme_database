<?php

namespace App\Http\Controllers;

use App\Enums\ConsolidationCategoryEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Http\Requests\StoreConsolidationUnitRequest;
use App\Http\Requests\UpdateConsolidationUnitRequest;
use App\Models\Center;
use App\Models\ConsolidationUnit;
use App\Models\Partner;
use Inertia\Inertia;

class ConsolidationUnitController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConsolidationUnitRequest $request, ?Center $center = null, ?Partner $partner = null)
    {
        $unit = new ConsolidationUnit;

        $this->associateOwner($unit, $center, $partner);

        $unit->fill($request->validated())->save();

        return back()->with('success', 'Consolidation unit created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ConsolidationUnit $consolidationUnit)
    {
        $owner = $consolidationUnit->center_id
            ? $consolidationUnit->center
            : $consolidationUnit->partner;

        $owner->load(['productionUnits', 'consolidationUnits', 'processingUnits', 'marketingUnits']);

        $view = $consolidationUnit->center_id ? 'Centers/Edit' : 'Partners/Edit';
        $key = $consolidationUnit->center_id ? 'center' : 'partner';

        return Inertia::render($view, [
            $key => $owner,
            'products' => $owner->products()->get(),
            'enums' => $this->enumLabels(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConsolidationUnitRequest $request, $parent, ConsolidationUnit $consolidationUnit)
    {
        $this->authorizeParent($request, $consolidationUnit);

        $consolidationUnit->update($request->validated());

        return back()->with('success', 'Consolidation unit updated successfully.');
    }

    public function destroy($parent, ConsolidationUnit $consolidationUnit)
    {
        $consolidationUnit->delete();

        return back()->with('success', 'Consolidation unit deleted successfully.');
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

    private function authorizeParent(UpdateConsolidationUnitRequest $request, ConsolidationUnit $consolidationUnit): void
    {
        if ($center = $request->route('center')) {
            abort_unless($consolidationUnit->center_id == $center, 404);
        } elseif ($partner = $request->route('partner')) {
            abort_unless($consolidationUnit->partner_id == $partner, 404);
        } else {
            abort(404);
        }
    }

    private function associateOwner(ConsolidationUnit $unit, ?Center $center, ?Partner $partner): void
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

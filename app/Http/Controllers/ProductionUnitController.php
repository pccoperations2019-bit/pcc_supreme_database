<?php

namespace App\Http\Controllers;

use App\Enums\ConsolidationCategoryEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Http\Requests\StoreProductionUnitRequest;
use App\Http\Requests\UpdateProductionUnitRequest;
use App\Models\Center;
use App\Models\Partner;
use App\Models\ProductionUnit;
use Inertia\Inertia;

class ProductionUnitController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionUnitRequest $request, ?Center $center = null, ?Partner $partner = null)
    {
        $unit = new ProductionUnit;

        $this->associateOwner($unit, $center, $partner);

        $unit->fill($request->validated())->save();

        return back()->with('success', 'Production unit created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductionUnit $productionUnit)
    {
        $owner = $productionUnit->center_id
            ? $productionUnit->center
            : $productionUnit->partner;

        $owner->load(['productionUnits', 'consolidationUnits', 'processingUnits', 'marketingUnits']);

        $view = $productionUnit->center_id ? 'Centers/Edit' : 'Partners/Edit';
        $key = $productionUnit->center_id ? 'center' : 'partner';

        return Inertia::render($view, [
            $key => $owner,
            'products' => $owner->products()->get(),
            'enums' => $this->enumLabels(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionUnitRequest $request, $parent, ProductionUnit $productionUnit)
    {
        $this->authorizeParent($request, $productionUnit);

        $productionUnit->update($request->validated());

        return back()->with('success', 'Production unit updated successfully.');
    }

    public function destroy($parent, ProductionUnit $productionUnit)
    {
        $productionUnit->delete();

        return back()->with('success', 'Production unit deleted successfully.');
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

    private function authorizeParent(UpdateProductionUnitRequest $request, ProductionUnit $productionUnit): void
    {
        if ($center = $request->route('center')) {
            abort_unless($productionUnit->center_id == $center, 404);
        } elseif ($partner = $request->route('partner')) {
            abort_unless($productionUnit->partner_id == $partner, 404);
        } else {
            abort(404);
        }
    }

    private function associateOwner(ProductionUnit $unit, ?Center $center, ?Partner $partner): void
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

<?php

namespace App\Http\Controllers;

use App\Enums\ConsolidationCategoryEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductionCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Http\Requests\StoreMarketingUnitRequest;
use App\Http\Requests\UpdateMarketingUnitRequest;
use App\Models\Center;
use App\Models\MarketingUnit;
use App\Models\Partner;
use Inertia\Inertia;

class MarketingUnitController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMarketingUnitRequest $request, ?Center $center = null, ?Partner $partner = null)
    {
        $unit = new MarketingUnit;

        $this->associateOwner($unit, $center, $partner);

        if (! $unit->center_id) {
            abort(404);
        }

        $unit->fill($request->validated())->save();

        return back()->with('success', 'Marketing unit created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MarketingUnit $marketingUnit)
    {
        $owner = $marketingUnit->center_id
            ? $marketingUnit->center
            : $marketingUnit->partner;

        $owner->load(['productionUnits', 'consolidationUnits', 'processingUnits', 'marketingUnits']);

        $view = $marketingUnit->center_id ? 'Centers/Edit' : 'Partners/Edit';
        $key = $marketingUnit->center_id ? 'center' : 'partner';

        return Inertia::render($view, [
            $key => $owner,
            'products' => $owner->products()->get(),
            'enums' => $this->enumLabels(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMarketingUnitRequest $request, $parent, MarketingUnit $marketingUnit)
    {
        $this->authorizeParent($request, $marketingUnit);

        $marketingUnit->update($request->validated());

        return back()->with('success', 'Marketing unit updated successfully.');
    }

    public function destroy($parent, MarketingUnit $marketingUnit)
    {
        $marketingUnit->delete();

        return back()->with('success', 'Marketing unit deleted successfully.');
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

    private function authorizeParent(UpdateMarketingUnitRequest $request, MarketingUnit $marketingUnit): void
    {
        if ($center = $request->route('center')) {
            abort_unless($marketingUnit->center_id == $center, 404);
        } elseif ($partner = $request->route('partner')) {
            abort_unless($marketingUnit->partner_id == $partner, 404);
        } else {
            abort(404);
        }
    }

    private function associateOwner(MarketingUnit $unit, ?Center $center, ?Partner $partner): void
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

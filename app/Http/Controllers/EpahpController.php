<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEpahpCapacityBuildingRequest;
use App\Http\Requests\StoreEpahpGatasangKalabawRequest;
use App\Http\Requests\UpdateEpahpCapacityBuildingRequest;
use App\Http\Requests\UpdateEpahpGatasangKalabawRequest;
use App\Models\Center;
use App\Models\EpahpCapacityBuilding;
use App\Models\EpahpGatasangKalabaw;
use App\Support\Centers;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;

class EpahpController extends Controller
{
    public function index()
    {
        $centerId = $this->centerScopeId();
        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($centerId !== null, fn (Builder $query) => $query->whereKey($centerId))
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $gatasangKalabaws = $this->scopeRecordsToCenter(EpahpGatasangKalabaw::query(), $centerId)
            ->with('center')
            ->orderByDesc('year')
            ->orderBy('id')
            ->paginate(10, ['*'], 'gk_page')
            ->withQueryString();

        $capacityBuildings = $this->scopeRecordsToCenter(EpahpCapacityBuilding::query(), $centerId)
            ->with('center')
            ->orderByDesc('year')
            ->orderBy('id')
            ->paginate(10, ['*'], 'cb_page')
            ->withQueryString();

        return Inertia::render('Epahp/Index', [
            'centers' => $centers,
            'gatasangKalabaws' => $gatasangKalabaws,
            'capacityBuildings' => $capacityBuildings,
        ]);
    }

    public function storeGatasangKalabaw(StoreEpahpGatasangKalabawRequest $request)
    {
        $data = $request->validated();
        $data['center_id'] = $this->centerScopeId() ?? $data['center_id'];
        EpahpGatasangKalabaw::create($data);

        return to_route('epahp.index')
            ->with('success', 'Expanded Gatasang Kalabaw record created successfully.');
    }

    public function updateGatasangKalabaw(
        UpdateEpahpGatasangKalabawRequest $request,
        EpahpGatasangKalabaw $epahpGatasangKalabaw
    ) {
        $this->authorizeCenterRecord($epahpGatasangKalabaw);
        $data = $request->validated();
        $data['center_id'] = $this->centerScopeId() ?? $data['center_id'];
        $epahpGatasangKalabaw->update($data);

        return to_route('epahp.index')
            ->with('success', 'Expanded Gatasang Kalabaw record updated successfully.');
    }

    public function destroyGatasangKalabaw(EpahpGatasangKalabaw $epahpGatasangKalabaw)
    {
        $this->authorizeCenterRecord($epahpGatasangKalabaw);
        $epahpGatasangKalabaw->delete();

        return to_route('epahp.index')
            ->with('success', 'Expanded Gatasang Kalabaw record deleted successfully.');
    }

    public function storeCapacityBuilding(StoreEpahpCapacityBuildingRequest $request)
    {
        $data = $request->validated();
        $data['center_id'] = $this->centerScopeId() ?? $data['center_id'];
        EpahpCapacityBuilding::create($data);

        return to_route('epahp.index')
            ->with('success', 'Capacity Building record created successfully.');
    }

    public function updateCapacityBuilding(
        UpdateEpahpCapacityBuildingRequest $request,
        EpahpCapacityBuilding $epahpCapacityBuilding
    ) {
        $this->authorizeCenterRecord($epahpCapacityBuilding);
        $data = $request->validated();
        $data['center_id'] = $this->centerScopeId() ?? $data['center_id'];
        $epahpCapacityBuilding->update($data);

        return to_route('epahp.index')
            ->with('success', 'Capacity Building record updated successfully.');
    }

    public function destroyCapacityBuilding(EpahpCapacityBuilding $epahpCapacityBuilding)
    {
        $this->authorizeCenterRecord($epahpCapacityBuilding);
        $epahpCapacityBuilding->delete();

        return to_route('epahp.index')
            ->with('success', 'Capacity Building record deleted successfully.');
    }

    private function centerScopeId(): ?int
    {
        $user = request()->user();

        return $user?->isCenterScoped() ? (int) $user->center_id : null;
    }

    private function scopeRecordsToCenter(Builder $query, ?int $centerId): Builder
    {
        if ($centerId === null) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($centerId) {
            $scope->where('center_id', $centerId)
                ->orWhere(function (Builder $relatedRecords) use ($centerId) {
                    $relatedRecords->whereNull('center_id')
                        ->where(fn (Builder $relations) => $relations
                            ->whereHas('partner', fn (Builder $partner) => $partner->where('center_id', $centerId))
                            ->orWhereHas('farmer', fn (Builder $farmer) => $farmer->where('center_id', $centerId))
                            ->orWhereHas('projectSite', fn (Builder $site) => $site->where('center_id', $centerId)));
                });
        });
    }

    private function authorizeCenterRecord(EpahpGatasangKalabaw|EpahpCapacityBuilding $record): void
    {
        $centerId = $this->centerScopeId();

        abort_if(
            $centerId !== null && ! $this->scopeRecordsToCenter($record->newQuery(), $centerId)
                ->whereKey($record->getKey())
                ->exists(),
            404,
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProgramEnum;
use App\Enums\ProjectProgramEnum;
use App\Http\Requests\StoreServiceRecordRequest;
use App\Http\Requests\UpdateServiceRecordRequest;
use App\Models\Center;
use App\Models\Farmer;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Sorts\RegionSort;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductionSupportController extends Controller
{
    public function index()
    {
        $serviceRecords = QueryBuilder::for(ServiceRecord::class)
            ->select(['id', 'center_id', 'service_id', 'farmer_id', 'project_id', 'client_name', 'program', 'no_animals_served', 'month_submitted', 'date_conducted', 'created_at'])
            ->with(['service:id,name,simplified_code', 'center:id,name,code', 'address', 'farmer:id,first_name,last_name', 'project:id,name,program'])
            ->whereHas('service', function ($query) {
                $query->where('simplified_code', '1.1.1');
            })
            ->when(request()->get('search'), function ($query, $search) {
                $query->where('client_name', 'like', "%{$search}%");
            })
            ->allowedFilters([
                AllowedFilter::exact('program'),
                AllowedFilter::partial('client_name'),
            ])
            ->allowedSorts([
                AllowedSort::custom('region', new RegionSort),
            ])
            ->defaultSort(AllowedSort::custom('region', new RegionSort))
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('ProductionSupport/Index', [
            'serviceRecords' => $serviceRecords,
            'enums' => [
                'program' => ProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    public function create()
    {
        $service = Service::query()->where('simplified_code', '1.1.1')->first();

        return Inertia::render('ProductionSupport/Create', [
            'serviceRecord' => array_merge((new ServiceRecord)->toArray(), ['address' => null]),
            'service' => $service?->only(['id', 'name', 'simplified_code', 'mfo_code', 'category', 'sub_category']),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->limit(100)->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'enums' => [
                'program' => ProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    public function store(StoreServiceRecordRequest $request)
    {
        $validated = $request->validated();

        $addressData = array_filter([
            'center_id' => $validated['center_id'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['municipality'] ?? null,
            'province' => $validated['province'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
        ], fn ($value) => ! is_null($value) && $value !== '');

        $serviceRecord = ServiceRecord::create(Arr::except($validated, ['barangay', 'municipality', 'province', 'region', 'district']));

        AuditTrail::syncAddress(
            subject: $serviceRecord,
            causer: $request->user(),
            description: 'Production Support Service Record Address Created',
            data: $addressData,
            event: 'created',
        );

        return to_route('production-support.index')
            ->with('success', 'Production Support Service Record created successfully.');
    }

    public function show(ServiceRecord $production_support)
    {
        $production_support->load(['service', 'center', 'address', 'farmer']);

        if ($production_support->service?->simplified_code !== '1.1.1') {
            abort(404);
        }

        return Inertia::render('ProductionSupport/Show', [
            'serviceRecord' => $production_support->toArray(),
            'service' => $production_support->service?->only(['id', 'name', 'simplified_code', 'mfo_code', 'category', 'sub_category']),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->limit(100)->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'enums' => [
                'program' => ProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    public function edit(ServiceRecord $production_support)
    {
        $production_support->load(['service', 'center', 'address', 'farmer']);

        if ($production_support->service?->simplified_code !== '1.1.1') {
            abort(404);
        }

        return Inertia::render('ProductionSupport/Edit', [
            'serviceRecord' => $production_support->toArray(),
            'service' => $production_support->service?->only(['id', 'name', 'simplified_code', 'mfo_code', 'category', 'sub_category']),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->limit(100)->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'enums' => [
                'program' => ProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    public function update(UpdateServiceRecordRequest $request, ServiceRecord $production_support)
    {
        $production_support->load('service');

        if ($production_support->service?->simplified_code !== '1.1.1') {
            abort(404);
        }

        $validated = $request->validated();

        $addressData = array_filter([
            'center_id' => $validated['center_id'] ?? $production_support->center_id,
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['municipality'] ?? null,
            'province' => $validated['province'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
        ], fn ($value) => ! is_null($value) && $value !== '');

        $production_support->update(Arr::except($validated, ['barangay', 'municipality', 'province', 'region', 'district']));

        AuditTrail::syncAddress(
            subject: $production_support,
            causer: $request->user(),
            description: 'Production Support Service Record Address Updated',
            data: $addressData,
        );

        return to_route('production-support.index')
            ->with('success', 'Production Support Service Record updated successfully.');
    }

    public function destroy(ServiceRecord $production_support)
    {
        $production_support->load('service');

        if ($production_support->service?->simplified_code !== '1.1.1') {
            abort(404);
        }

        ServiceRecord::destroy($production_support->getKey());

        return to_route('production-support.index')
            ->with('success', 'Production Support Service Record deleted successfully.');
    }
}

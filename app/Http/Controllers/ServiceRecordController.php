<?php

namespace App\Http\Controllers;

use App\Enums\ProjectProgramEnum;
use App\Enums\ServiceProgramEnum;
use App\Http\Requests\StoreServiceRecordRequest;
use App\Http\Requests\UpdateServiceRecordRequest;
use App\Models\Center;
use App\Models\Farmer;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Support\Audits\AuditTrail;
use App\Support\Centers;
use App\Support\ServiceRecordSections;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('ServiceRecords/Index', [
            'serviceRecords' => QueryBuilder::for(ServiceRecord::class)
                ->with([
                    'address',
                    'center',
                    'service',
                    'farmer',
                ])
                ->when(request()->get('search'), function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('client_name', 'like', "%{$search}%");
                    });
                })
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('service_id'),
                    AllowedFilter::exact('program'),
                    AllowedFilter::callback('client_name', function ($query, $value) {
                        $query->where('client_name', 'like', "%{$value}%");
                    }),
                    AllowedFilter::callback('date_conducted', function ($query, $value) {
                        if (is_array($value)) {
                            $query->whereBetween('date_conducted', $value);
                        }
                    }),
                ])
                ->allowedSorts([
                    'client_name',
                    'date_conducted',
                    'month_submitted',
                    'no_animals_served',
                    'created_at',
                ])
                ->defaultSort('-created_at')
                ->paginate()
                ->withQueryString(),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'services' => Service::orderBy('name', 'asc')->get(['id', 'name', 'simplified_code', 'category', 'sub_category']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->get(['id', 'first_name', 'last_name']),
            'enums' => [
                'program' => ServiceProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('ServiceRecords/Create', [
            'serviceRecord' => array_merge((new ServiceRecord)->toArray(), ['address' => null]),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'services' => Service::orderBy('name', 'asc')->get(['id', 'name', 'simplified_code', 'category', 'sub_category']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'indexUrl' => route('service-records.index'),
            'enums' => [
                'program' => ServiceProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

        $serviceRecord = ServiceRecord::create($this->serviceRecordAttributes($validated));

        if ($addressData !== []) {
            AuditTrail::syncAddress(
                subject: $serviceRecord,
                causer: $request->user(),
                description: 'Service Record Address Created',
                data: $addressData,
                event: 'created',
            );
        }

        return to_route($this->indexRouteFor($serviceRecord))
            ->with('success', 'Service record created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ServiceRecord $serviceRecord)
    {
        $serviceRecord->load([
            'address',
            'center',
            'service',
            'farmer',
            'project',
        ]);

        return Inertia::render('ServiceRecords/Show', [
            'serviceRecord' => $serviceRecord->toArray(),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'services' => Service::orderBy('name', 'asc')->get(['id', 'name', 'simplified_code', 'category', 'sub_category']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'indexUrl' => route($this->indexRouteFor($serviceRecord)),
            'enums' => [
                'program' => ServiceProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ServiceRecord $serviceRecord)
    {
        $serviceRecord->load([
            'address',
            'center',
            'service',
            'farmer',
            'project',
        ]);

        return Inertia::render('ServiceRecords/Edit', [
            'serviceRecord' => $serviceRecord->toArray(),
            'centers' => Center::query()->excludingHidden(Centers::hiddenCenters())->orderBy('name', 'asc')->get(['id', 'name', 'code']),
            'services' => Service::orderBy('name', 'asc')->get(['id', 'name', 'simplified_code', 'category', 'sub_category']),
            'farmers' => Farmer::orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->get(['id', 'first_name', 'last_name']),
            'projects' => Project::orderBy('name', 'asc')->get(['id', 'name', 'program']),
            'indexUrl' => route($this->indexRouteFor($serviceRecord)),
            'enums' => [
                'program' => ServiceProgramEnum::labels(),
                'project_program' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRecordRequest $request, ServiceRecord $serviceRecord)
    {
        $validated = $request->validated();
        $addressData = array_filter([
            'center_id' => $validated['center_id'] ?? $serviceRecord->center_id,
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['municipality'] ?? null,
            'province' => $validated['province'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
        ], fn ($value) => ! is_null($value) && $value !== '');

        $serviceRecord->update($this->serviceRecordAttributes($validated));

        if ($addressData !== []) {
            AuditTrail::syncAddress(
                subject: $serviceRecord,
                causer: $request->user(),
                description: 'Service Record Address Updated',
                data: $addressData,
            );
        }

        return to_route($this->indexRouteFor($serviceRecord))
            ->with('success', 'Service record updated successfully.');
    }

    /**
     * Where a save on this record should return the user: the index of the
     * service the record points at, so a Production Support record does not land
     * on the combined list that only Production Support manages separately.
     */
    private function indexRouteFor(ServiceRecord $serviceRecord): string
    {
        return ServiceRecordSections::indexRouteFor($serviceRecord->service?->simplified_code);
    }

    /**
     * The record's own columns, minus the address ones that live on the morphOne
     * `addresses` row.
     *
     * `program` follows the project, so a save with no project leaves the stored
     * one alone: the column is NOT NULL, and writing a blank over it fails the
     * whole update for a field the user did not touch.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function serviceRecordAttributes(array $validated): array
    {
        $attributes = Arr::except($validated, ['barangay', 'municipality', 'province', 'region', 'district']);

        if (($attributes['program'] ?? '') === '') {
            unset($attributes['program']);
        }

        return $attributes;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServiceRecord $serviceRecord)
    {
        ServiceRecord::destroy($serviceRecord->getKey());

        return to_route('service-records.index')
            ->with('success', 'Service record deleted successfully.');
    }
}

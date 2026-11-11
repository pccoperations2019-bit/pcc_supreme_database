<?php

namespace App\Http\Controllers;

use App\Enums\ProgramEnum;
use App\Enums\ValueChainSegmentEnum;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = QueryBuilder::for(Service::class)
            ->select(['id', 'name', 'simplified_code', 'mfo_code', 'program', 'created_at'])
            ->when(request()->get('search'), function ($query, $search) {
                $query->globalSearch($search);
            })
            ->allowedFilters([
                AllowedFilter::exact('program'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('simplified_code'),
                AllowedFilter::partial('mfo_code'),
            ])
            ->allowedSorts([
                'name',
                'simplified_code',
                'mfo_code',
                'program',
                'created_at',
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Services/Index', [
            'services' => $services,
            'enums' => [
                'program' => ProgramEnum::labels(),
            ],
            'simplified_codes' => Service::distinct('simplified_code')->pluck('simplified_code')->filter()->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Services/Create', [
            'service' => (new Service)->toArray(),
            'enums' => [
                'program' => ProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request)
    {
        $validated = $request->validated();

        $service = Service::create([
            'simplified_code' => $validated['simplified_code'] ?? null,
            'mfo_code' => $validated['mfo_code'] ?? null,
            'name' => $validated['name'],
            'program' => $validated['program'] ?? ProgramEnum::DEFAULT->value,
            'value_chain_segment' => $validated['value_chain_segment'] ?? ValueChainSegmentEnum::DEFAULT->value,
            'category' => $validated['category'] ?? null,
            'sub_category' => $validated['sub_category'] ?? null,
            'type' => $validated['type'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return to_route('services.show', $service)
            ->with('success', 'Service created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        return Inertia::render('Services/Show', [
            'service' => $service->toArray(),
            'enums' => [
                'program' => ProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Service $service)
    {
        return Inertia::render('Services/Edit', [
            'service' => $service->toArray(),
            'enums' => [
                'program' => ProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRequest $request, Service $service)
    {
        $validated = $request->validated();

        $service->update([
            'simplified_code' => array_key_exists('simplified_code', $validated) ? ($validated['simplified_code'] ?: null) : $service->simplified_code,
            'mfo_code' => array_key_exists('mfo_code', $validated) ? ($validated['mfo_code'] ?: null) : $service->mfo_code,
            'name' => $validated['name'] ?? $service->name,
            'program' => array_key_exists('program', $validated) ? ($validated['program'] ?: ProgramEnum::DEFAULT->value) : $service->program,
            'value_chain_segment' => array_key_exists('value_chain_segment', $validated) ? ($validated['value_chain_segment'] ?: null) : $service->value_chain_segment,
            'category' => array_key_exists('category', $validated) ? ($validated['category'] ?: null) : $service->category,
            'sub_category' => array_key_exists('sub_category', $validated) ? ($validated['sub_category'] ?: null) : $service->sub_category,
            'type' => array_key_exists('type', $validated) ? ($validated['type'] ?: null) : $service->type,
            'description' => array_key_exists('description', $validated) ? ($validated['description'] ?: null) : $service->description,
        ]);

        return to_route('services.show', $service)
            ->with('success', 'Service updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        Service::destroy($service->getKey());

        return to_route('services.index')
            ->with('success', 'Service deleted successfully.');
    }
}

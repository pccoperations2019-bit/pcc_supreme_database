<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\MilkConsolidation;
use App\Support\Centers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MilkConsolidationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $milkConsolidations = QueryBuilder::for(MilkConsolidation::class)
            ->with('center')
            ->allowedFilters([
                AllowedFilter::exact('center_id'),
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Consolidation/Milk/Index', [
            'milkConsolidations' => $milkConsolidations,
            'filters' => [
                'centers' => Center::query()
                    ->excludingHidden(Centers::hiddenCenters())
                    ->orderBy('name', 'asc')
                    ->get()
                    ->map(fn ($center) => [
                        'id' => $center->id,
                        'name' => $center->name,
                        'code' => $center->code,
                    ]),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

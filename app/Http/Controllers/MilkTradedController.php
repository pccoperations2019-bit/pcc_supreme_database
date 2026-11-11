<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\MilkTraded;
use App\Models\Partner;
use App\Support\Centers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MilkTradedController extends Controller
{
    public function index()
    {
        $milkTraders = QueryBuilder::for(MilkTraded::class)
            ->with(['center', 'partner', 'milkProduction'])
            ->allowedFilters([
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('partner_id'),
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $partners = Partner::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'center_id']);

        return Inertia::render('Productions/Milk/Traded/Index', [
            'milkTraders' => $milkTraders,
            'centers' => $centers,
            'partners' => $partners,
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}

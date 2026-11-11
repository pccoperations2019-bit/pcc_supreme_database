<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Http\Requests\StoreDairyBoxSaleRequest;
use App\Http\Requests\UpdateDairyBoxSaleRequest;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\DairyBoxSale;
use App\Models\Partner;
use App\Queries\Aggregate\AggregatedDairyBoxSalesQuery;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DairyBoxSalesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->query('_view', 'data');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = DairyBoxSale::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->globalSearch($search);

        if ($view === 'monthly') {
            $query = clone $baseQuery;

            $query = resolve(AggregatedDairyBoxSalesQuery::class)->applyTo($query);

            collect(['center_id', 'partner_id', 'year', 'month'])->each(function ($filter) use ($query) {
                if ($value = request("filter.$filter")) {
                    $query->where($filter, $value);
                }
            });

            $query->orderByRaw(CenterEnum::codeSortExpression('dairy_box_sales.center_id').' asc');

            $dairyBoxSales = $query->paginate(15)->appends(request()->query());
            $dairyBoxSales->load('center');

            resolve(AggregatedDairyBoxSalesQuery::class)->transform($dairyBoxSales);
        } else {
            $dairyBoxSales = QueryBuilder::for($baseQuery)
                ->with(['center', 'partner', 'dairyBox'])
                ->allowedFilters([
                    AllowedFilter::exact('center_id'),
                    AllowedFilter::exact('partner_id'),
                    AllowedFilter::exact('year'),
                    AllowedFilter::exact('month'),
                ])
                ->allowedSorts([
                    'year',
                    'month',
                    'total_sales',
                ])
                ->defaultSort('-created_at')
                ->paginate(10)
                ->appends(request()->query());
        }

        $tenant = resolve(TenantContext::class);
        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $partners = Partner::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $years = DairyBoxSale::query()
            ->select('year')
            ->distinct()
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->values();

        return Inertia::render('DairyBoxSales/Index', [
            'dairyBoxSales' => $dairyBoxSales,
            'centers' => $centers,
            'partners' => $partners,
            'years' => $years,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $dairyBoxes = DairyBox::query()
            ->when($tenant->agencyId(), fn ($q) => $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId())))
            ->orderBy('business_name')
            ->get(['id', 'dairy_box_id', 'business_name', 'center_id']);

        $partners = Partner::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        return Inertia::render('DairyBoxSales/Create', [
            'dairyBoxSale' => (object) [],
            'dairyBoxes' => $dairyBoxes,
            'centers' => $centers,
            'partners' => $partners,
            'defaultCenterId' => $user?->center_id,
        ]);
    }

    public function store(StoreDairyBoxSaleRequest $request)
    {
        $data = $request->validated();

        $data['quarter'] = $data['month'] ? ceil($data['month'] / 3) : null;

        DairyBoxSale::create($data);

        return to_route('dairy-box-sales.index')
            ->with('success', 'Dairy box sale created successfully.');
    }

    public function show(DairyBoxSale $dairyBoxSale)
    {
        $dairyBoxSale->load(['center', 'partner', 'dairyBox']);

        return Inertia::render('DairyBoxSales/Show', [
            'dairyBoxSale' => $dairyBoxSale,
        ]);
    }

    public function edit(Request $request, DairyBoxSale $dairyBoxSale)
    {
        $user = $request->user();
        $tenant = resolve(TenantContext::class);

        $centers = Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $dairyBoxes = DairyBox::query()
            ->when($tenant->agencyId(), fn ($q) => $q->whereHas('center', fn ($cq) => $cq->where('agency_id', $tenant->agencyId())))
            ->orderBy('business_name')
            ->get(['id', 'dairy_box_id', 'business_name', 'center_id']);

        $partners = Partner::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $dairyBoxSale->load(['center', 'partner', 'dairyBox']);

        return Inertia::render('DairyBoxSales/Edit', [
            'dairyBoxSale' => $dairyBoxSale,
            'dairyBoxes' => $dairyBoxes,
            'centers' => $centers,
            'partners' => $partners,
        ]);
    }

    public function update(UpdateDairyBoxSaleRequest $request, DairyBoxSale $dairyBoxSale)
    {
        $data = $request->validated();

        $data['quarter'] = $data['month'] ? ceil($data['month'] / 3) : null;

        $dairyBoxSale->update($data);

        return to_route('dairy-box-sales.index')
            ->with('success', 'Dairy box sale updated successfully.');
    }

    public function destroy(DairyBoxSale $dairyBoxSale)
    {
        DairyBoxSale::destroy($dairyBoxSale->getKey());

        return to_route('dairy-box-sales.index')
            ->with('success', 'Dairy box sale deleted successfully.');
    }
}

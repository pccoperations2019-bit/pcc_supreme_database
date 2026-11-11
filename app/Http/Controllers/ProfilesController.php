<?php

namespace App\Http\Controllers;

use App\Enums\ValueChainSegmentEnum;
use App\Models\Profile;
use App\Sorts\RegionSort;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProfilesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $profiles = QueryBuilder::for(
            Profile::query()
                ->when(
                    $user && $user->isCenterScoped(),
                    fn ($query) => $query->where('center_id', $user->center_id)
                )
        )
            ->with(['address'])
            ->when(request()->get('search'), function ($query, $search) {
                $query->globalSearch($search);
            })
            ->allowedFilters([
                AllowedFilter::callback('key', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%")
                            ->orWhere('display_name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('profile_type'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'profile_type',
                'category',
                AllowedSort::custom('region', new RegionSort),
                'address',
                'created_at',
            ])
            ->defaultSort(AllowedSort::custom('region', new RegionSort))
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Profiles/Index', [
            'profiles' => $profiles,
            'valueChainSegments' => [
                'labels' => ValueChainSegmentEnum::labels(),
                'colors' => ValueChainSegmentEnum::colors(),
            ],
        ]);
    }
}

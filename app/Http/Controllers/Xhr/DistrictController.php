<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Serves PSGC districts for the address cascade, mirroring the shape
 * used by the vendored `schoolees/laravel-psgc` endpoints so the
 * frontend `fetchAll()` pagination keeps working unchanged.
 *
 * The package only registers regions/provinces/cities/barangays, so
 * districts live here, app-side, reading the table-backed `districts`
 * table (seeded from resources/psgc/district.json).
 */
class DistrictController extends Controller
{
    public function index(): JsonResponse
    {
        $table = config('psgc.tables.districts', 'districts');

        $query = DB::table($table);

        $key = trim((string) request()->query('key', ''));

        $query
            ->when($cityCode = request()->query('city_code'), function ($q) use ($cityCode) {
                return $q->where('city_code', $cityCode);
            })
            ->when($key !== '', function ($q) use ($key) {
                return $q->where('name', 'like', "%{$key}%");
            });

        $orderBy = (string) request()->query('order_by', 'name');
        $sortBy = strtolower((string) request()->query('sort_by', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (in_array($orderBy, ['code', 'name', 'city_code'], true)) {
            $query->orderBy($orderBy, $sortBy);
        }

        $limit = min(max(1, (int) request()->query('limit', 3000)), 10000);
        $offset = max(0, (int) request()->query('offset', 0));

        $total = (clone $query)->count();
        $rows = $query->limit($limit)->offset($offset)->get();

        return response()->json([
            'data' => $rows,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
        ]);
    }
}

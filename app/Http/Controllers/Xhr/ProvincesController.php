<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\MilkFeedingProgram;
use Illuminate\Http\Request;

class ProvincesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $key = $request->query('key');

        $query = MilkFeedingProgram::query()
            ->distinct()
            ->select('province')
            ->whereNotNull('province')
            ->when($key, function ($query) use ($key) {
                $query->where('province', 'like', "%{$key}%");
            })
            ->orderBy('province')
            ->limit(50);

        return $query->get()
            ->map(function ($program) {
                return [
                    'value' => $program->province,
                    'label' => $program->province,
                ];
            });
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilkUtilizationMonthlySummaryRequest;
use App\Models\MilkUtilizationMonthlySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MilkUtilizationMonthlySummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'program' => ['required', 'string', 'in:ih,cbed'],
            'center_id' => ['nullable', 'integer'],
        ]);

        $query = MilkUtilizationMonthlySummary::query()
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->where('program', $validated['program']);

        if (isset($validated['center_id'])) {
            $query->where('center_id', $validated['center_id']);
        }

        return response()->json($query->with(['center', 'partner'])->get());
    }

    public function store(MilkUtilizationMonthlySummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $centerId = null;
        if (isset($validated['center_code'])) {
            $center = DB::table('centers')->where('code', $validated['center_code'])->first();
            if ($center) {
                $centerId = $center->id;
            }
        } elseif (isset($validated['center_id'])) {
            $centerId = $validated['center_id'];
        }

        $updateData = [
            'notes' => $validated['notes'] ?? null,
        ];

        if (array_key_exists('lactating_animals', $validated)) {
            $updateData['lactating_animals'] = $validated['lactating_animals'];
            $updateData['lactating_animals_source'] = $validated['lactating_animals'] !== null ? 'manual' : 'aggregated';
        }

        if (array_key_exists('production', $validated)) {
            $updateData['production'] = $validated['production'];
            $updateData['production_source'] = $validated['production'] !== null ? 'manual' : 'aggregated';
        }

        if (array_key_exists('received', $validated)) {
            $updateData['received'] = $validated['received'];
            $updateData['received_source'] = $validated['received'] !== null ? 'manual' : 'aggregated';
        }

        if (array_key_exists('fed_to_calves', $validated)) {
            $updateData['fed_to_calves'] = $validated['fed_to_calves'];
            $updateData['fed_to_calves_source'] = $validated['fed_to_calves'] !== null ? 'manual' : 'aggregated';
        }

        if (array_key_exists('value_added_products', $validated)) {
            $updateData['value_added_products'] = $validated['value_added_products'];
            $updateData['value_added_products_source'] = $validated['value_added_products'] !== null ? 'manual' : 'aggregated';
        }

        if (array_key_exists('milk_feeding', $validated)) {
            $updateData['milk_feeding'] = $validated['milk_feeding'];
            $updateData['milk_feeding_source'] = $validated['milk_feeding'] !== null ? 'manual' : 'aggregated';
        }

        $summary = MilkUtilizationMonthlySummary::updateOrCreate(
            [
                'center_id' => $centerId,
                'partner_id' => $validated['partner_id'],
                'year' => $validated['year'],
                'month' => $validated['month'],
                'program' => $validated['program'],
            ],
            $updateData
        );

        return response()->json($summary->load(['center', 'partner']), 201);
    }

    public function update(MilkUtilizationMonthlySummaryRequest $request, MilkUtilizationMonthlySummary $summary): JsonResponse
    {
        $validated = $request->validated();

        $summary->update([
            'lactating_animals' => $validated['lactating_animals'],
            'production' => $validated['production'],
            'milk_feeding' => $validated['milk_feeding'],
            'notes' => $validated['notes'] ?? null,
            'lactating_animals_source' => $validated['lactating_animals'] !== null ? 'manual' : 'aggregated',
            'production_source' => $validated['production'] !== null ? 'manual' : 'aggregated',
            'milk_feeding_source' => $validated['milk_feeding'] !== null ? 'manual' : 'aggregated',
        ]);

        return response()->json($summary->load(['center', 'partner']));
    }

    public function destroy(MilkUtilizationMonthlySummary $summary): JsonResponse
    {
        $summary->delete();

        return response()->json(null, 204);
    }
}

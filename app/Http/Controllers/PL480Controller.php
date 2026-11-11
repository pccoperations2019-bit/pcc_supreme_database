<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\PL480\UpsertPl480Action;
use App\Actions\Tag\CreateTagAction;
use App\Enums\PL480ProvinceEnum;
use App\Http\Requests\BulkUpsertPl480Request;
use App\Http\Requests\StorePl480Request;
use App\Models\MilkProduction;
use App\Models\Pl480;
use App\Queries\PL480\PL480Query;
use App\Workflows\ExportPL480Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;

class PL480Controller extends Controller
{
    /**
     * Display the PL480 milk production data.
     */
    public function index()
    {
        $provinces = PL480ProvinceEnum::values();
        $centerId = $this->centerScopeId();

        $currentYear = Date::now()->year;
        $fallbackStartYear = $currentYear - 5;

        $minMilkYear = $this->minMilkProductionYear($centerId, $fallbackStartYear);

        $minOverrideYear = (int) ($this->pl480OverridesQuery($centerId)->min('year') ?? $fallbackStartYear);

        $startYear = min($fallbackStartYear, $minMilkYear, $minOverrideYear);
        $years = range($startYear, $currentYear);

        $milkProductionData = (new PL480Query)->execute($provinces, $startYear, $currentYear, $centerId);

        $inputData = [];
        $computedData = [];

        foreach ($provinces as $province) {
            $inputData[$province] = [];
            $computedData[$province] = [];
            foreach ($years as $year) {
                $inputData[$province][$year] = 0;
                $computedData[$province][$year] = $milkProductionData[$province][$year] ?? 0;
            }
        }

        $overrides = $this->pl480OverridesQuery($centerId)
            ->whereIn('province', $provinces)
            ->whereIn('year', $years)
            ->get()
            ->groupBy('province');

        foreach ($overrides as $province => $items) {
            foreach ($items as $item) {
                $inputData[$province][$item->year] = (float) $item->value;
            }
        }

        return Inertia::render('PL480/Index', [
            'provinces' => $provinces,
            'years' => $years,
            'inputData' => $inputData,
            'computedData' => $computedData,
        ]);
    }

    public function upsertInput(StorePl480Request $request, UpsertPl480Action $upsertPl480Action)
    {
        $this->abortIfCenterAdmin();

        $validated = $request->validated();

        $upsertPl480Action->execute(
            $validated['province'],
            (int) $validated['year'],
            isset($validated['value']) ? (float) $validated['value'] : 0,
        );

        return back();
    }

    public function bulkUpsertInputs(BulkUpsertPl480Request $request, UpsertPl480Action $upsertPl480Action)
    {
        $this->abortIfCenterAdmin();

        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            $upsertPl480Action->execute(
                $item['province'],
                (int) $item['year'],
                isset($item['value']) ? (float) $item['value'] : 0,
            );
        }

        return back();
    }

    public function export(
        Request $request,
        ExportPL480Workflow $exportPL480Workflow,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        // Defence in depth: the route is auth-only, so refuse accounts that may
        // not export anything. A center-scoped exporter is pinned to its own
        // center further down (centerScopeId()).
        abort_unless($request->user()?->canExportSomething(), 403, 'You are not allowed to export data.');

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:xlsx,csv,pdf'],
            'queue_export' => ['nullable', 'boolean'],
            'without_years' => ['nullable', 'array'],
            'with_years' => ['nullable', 'array'],
        ]);

        $format = $validated['format'];
        $queueExport = (bool) ($validated['queue_export'] ?? true);

        $provinces = PL480ProvinceEnum::values();
        $centerId = $this->centerScopeId();

        $currentYear = Date::now()->year;
        $fallbackStartYear = $currentYear - 5;

        $minMilkYear = $this->minMilkProductionYear($centerId, $fallbackStartYear);

        $minOverrideYear = (int) ($this->pl480OverridesQuery($centerId)->min('year') ?? $fallbackStartYear);

        $startYear = min($fallbackStartYear, $minMilkYear, $minOverrideYear);
        $years = range($startYear, $currentYear);

        $milkProductionData = (new PL480Query)->execute($provinces, $startYear, $currentYear, $centerId);

        $inputData = [];
        $computedData = [];

        foreach ($provinces as $province) {
            $inputData[$province] = [];
            $computedData[$province] = [];
            foreach ($years as $year) {
                $inputData[$province][$year] = 0;
                $computedData[$province][$year] = $milkProductionData[$province][$year] ?? 0;
            }
        }

        $overrides = $this->pl480OverridesQuery($centerId)
            ->whereIn('province', $provinces)
            ->whereIn('year', $years)
            ->get()
            ->groupBy('province');

        foreach ($overrides as $province => $items) {
            foreach ($items as $item) {
                $inputData[$province][$item->year] = (float) $item->value;
            }
        }

        $selectedWithoutYears = $validated['without_years'] ?? $years;
        $selectedWithYears = $validated['with_years'] ?? $years;

        if ($queueExport) {
            $document = $exportPL480Workflow->queueExport(
                provinces: $provinces,
                years: $years,
                inputData: $inputData,
                computedData: $computedData,
                selectedWithoutYears: $selectedWithoutYears,
                selectedWithYears: $selectedWithYears,
                format: $format,
                createDocumentAction: $createDocumentAction,
                createTagAction: $createTagAction,
            );

            return response()->json([
                'document_id' => $document->id,
            ]);
        }

        return $exportPL480Workflow->handleDirectExport(
            provinces: $provinces,
            years: $years,
            inputData: $inputData,
            computedData: $computedData,
            selectedWithoutYears: $selectedWithoutYears,
            selectedWithYears: $selectedWithYears,
            format: $format,
        );
    }

    private function abortIfCenterAdmin(): void
    {
        if (request()->user()?->isCenterScoped()) {
            abort(403);
        }
    }

    private function centerScopeId(): ?int
    {
        $user = request()->user();

        return $user?->isCenterScoped() ? (int) $user->center_id : null;
    }

    private function pl480OverridesQuery(?int $centerId): Builder
    {
        return Pl480::query()
            ->when($centerId !== null, fn (Builder $query) => $query->where(function (Builder $scope) use ($centerId) {
                $scope->where('center_id', $centerId)
                    ->orWhere(fn (Builder $partnerRows) => $partnerRows
                        ->whereNull('center_id')
                        ->whereHas('partner', fn (Builder $partner) => $partner->where('center_id', $centerId)));
            }));
    }

    private function minMilkProductionYear(?int $centerId, int $fallbackYear): int
    {
        $query = MilkProduction::query();

        if ($centerId === null) {
            $query->whereHas('partner.address');
        } else {
            $query->where(fn (Builder $scope) => $scope
                ->where('center_id', $centerId)
                ->orWhereHas('partner', fn (Builder $partner) => $partner->where('center_id', $centerId))
                ->orWhereHas('farmer', fn (Builder $farmer) => $farmer->where('center_id', $centerId)));
        }

        return (int) ($query->min('year') ?? $fallbackYear);
    }
}

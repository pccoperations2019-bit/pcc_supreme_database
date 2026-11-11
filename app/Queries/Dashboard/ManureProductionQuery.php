<?php

namespace App\Queries\Dashboard;

use App\Enums\MonthEnum;
use App\Enums\ProgramEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManureProductionQuery
{
    private function monthMap(): array
    {
        return collect(MonthEnum::cases())
            ->mapWithKeys(fn (MonthEnum $month) => [$month->abbreviation() => $month->value])
            ->all();
    }

    public function execute(
        ?int $centerId = null,
        mixed $dateRange = 'this_year',
        ?ProgramEnum $program = null
    ): Collection {
        [$year, $monthAbbr] = $this->resolveYearAndMonth($dateRange);

        $monthNumber = $monthAbbr ? ($this->monthMap()[$monthAbbr] ?? null) : null;

        $query = DB::table('manure_productions')
            ->when($program, function ($query) use ($program) {
                $query->where('manure_productions.program', $program->value);
            })
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('manure_productions.center_id', $centerId);
            })
            ->when($year, function ($query) use ($year) {
                $query->where('manure_productions.year', $year);
            })
            ->when($monthNumber, function ($query) use ($monthNumber) {
                $query->where('manure_productions.month', $monthNumber);
            });

        $totalVolumeProduced = (float) (clone $query)->selectRaw('COALESCE(SUM(manure_productions.total_volume), 0) as total_volume_produced')->value('total_volume_produced') ?? 0;

        $salesQuery = DB::table('manure_sales')
            ->when($program, function ($query) use ($program) {
                $query->where('manure_sales.program', $program->value);
            })
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('manure_sales.center_id', $centerId);
            })
            ->when($year, function ($query) use ($year) {
                $query->where('manure_sales.year', $year);
            })
            ->when($monthNumber, function ($query) use ($monthNumber) {
                $query->where('manure_sales.month', $monthNumber);
            });

        $totalSalesValueAdded = (float) (clone $salesQuery)->selectRaw('COALESCE(SUM(manure_sales.total_sales), 0) as total_sales_value_added')->value('total_sales_value_added') ?? 0;

        return collect([
            'total_volume_produced' => $totalVolumeProduced,
            'total_sales_value_added' => $totalSalesValueAdded,
        ]);
    }

    private function resolveYearAndMonth(mixed $dateRange): array
    {
        if (is_int($dateRange) || (is_string($dateRange) && ctype_digit($dateRange))) {
            return [(int) $dateRange, null];
        }

        if (is_array($dateRange) && count($dateRange) === 2) {
            $start = $dateRange[0] instanceof \DateTimeInterface ? $dateRange[0] : date_create((string) $dateRange[0]);
            if ($start) {
                return [(int) $start->format('Y'), strtolower($start->format('M'))];
            }
        }

        $year = match ($dateRange) {
            'this_year' => now()->year,
            'last_year' => now()->subYear()->year,
            default => now()->year,
        };

        return [$year, null];
    }
}

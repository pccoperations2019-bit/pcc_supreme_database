<?php

namespace App\Queries\Reports;

use App\Enums\ProgramEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryManurePerCenterByYearQuery
{
    public function execute(?int $year): Collection
    {
        $goalsSubquery = DB::table('center_production_goals')
            ->select('center_id', DB::raw('COALESCE(SUM(CASE WHEN program = \''.ProgramEnum::IH->value.'\' THEN manure_based_goal ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN program = \''.ProgramEnum::CBED->value.'\' THEN manure_based_goal ELSE 0 END), 0) as annual_goal'))
            ->whereIn('program', [ProgramEnum::IH->value, ProgramEnum::CBED->value])
            ->when($year, fn ($q) => $q->where('year', $year))
            ->groupBy('center_id');

        return DB::table('centers')
            ->select([
                'centers.id as center_id',
                'centers.code as center_code',
                DB::raw('COALESCE(SUM(CASE WHEN manure_productions.program = \''.ProgramEnum::CBED->value.'\' THEN manure_productions.total_volume ELSE 0 END), 0) as cbed_total_volume_produced'),
                DB::raw('COALESCE(SUM(CASE WHEN manure_productions.program = \''.ProgramEnum::IH->value.'\' THEN manure_productions.total_volume ELSE 0 END), 0) as ih_total_volume_produced'),
                DB::raw('COALESCE(SUM(manure_productions.total_volume), 0) as total_volume_produced'),
                'goals.annual_goal',
            ])
            ->leftJoin('manure_productions', function ($join) use ($year) {
                $join->on('centers.id', '=', 'manure_productions.center_id');
                if ($year) {
                    $join->where('manure_productions.year', '=', $year);
                }
            })
            ->leftJoin(DB::raw("({$goalsSubquery->toSql()}) as goals"), function ($join) {
                $join->on('centers.id', '=', 'goals.center_id');
            })
            ->mergeBindings($goalsSubquery)
            ->groupBy('centers.id', 'centers.code', 'goals.annual_goal')
            ->orderBy('centers.code', 'asc')
            ->get();
    }
}

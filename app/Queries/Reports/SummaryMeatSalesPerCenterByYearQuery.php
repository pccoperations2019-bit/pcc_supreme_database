<?php

namespace App\Queries\Reports;

use App\Enums\ProgramEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryMeatSalesPerCenterByYearQuery
{
    public function execute(
        ?int $year
    ): Collection {
        $defaultProgram = ProgramEnum::IH->value;

        $goalsSubquery = DB::table('center_sales_goals')
            ->select('center_id', DB::raw('COALESCE(SUM(CASE WHEN program = \''.ProgramEnum::IH->value.'\' THEN meat_sales_goal ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN program = \''.ProgramEnum::CBED->value.'\' THEN meat_sales_goal ELSE 0 END), 0) as annual_goal'))
            ->whereIn('program', [ProgramEnum::IH->value, ProgramEnum::CBED->value])
            ->when($year, fn ($q) => $q->where('year', $year))
            ->groupBy('center_id');

        return DB::table('centers')
            ->select([
                'centers.id as center_id',
                'centers.code as center_code',
                DB::raw('COALESCE(SUM(CASE WHEN COALESCE(meat_sales.program, \''.$defaultProgram.'\') = \''.ProgramEnum::CBED->value.'\' THEN meat_sales.total_sales ELSE 0 END), 0) as cbed_total_volume_produced'),
                DB::raw('COALESCE(SUM(CASE WHEN COALESCE(meat_sales.program, \''.$defaultProgram.'\') = \''.ProgramEnum::IH->value.'\' THEN meat_sales.total_sales ELSE 0 END), 0) as ih_total_volume_produced'),
                DB::raw('COALESCE(SUM(meat_sales.total_sales), 0) as total_volume_produced'),
                'goals.annual_goal',
            ])
            ->leftJoin('meat_sales', function ($join) use ($year) {
                $join->on('centers.id', '=', 'meat_sales.center_id');
                if ($year) {
                    $join->where('meat_sales.year', '=', $year);
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

<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithMilkUtilization
{
    protected function setUpMilkUtilization(): void
    {

        Cache::flush();
    }

    protected function muAgency(): int
    {
        return DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.Str::upper(Str::random(6)),
            'name' => 'Milk Utilization Agency',
        ]);
    }

    protected function muProject(string $program = 'cbed'): int
    {
        return DB::table('projects')->insertGetId([
            'program' => $program,
            'name' => 'Milk Utilization Project',
        ]);
    }

    protected function muCenter(string $code, ?int $agencyId = null, ?int $projectId = null): int
    {
        $existing = DB::table('centers')->where('code', $code)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $agencyId ??= $this->muAgency();
        $projectId ??= $this->muProject();

        return DB::table('centers')->insertGetId([
            'code' => $code,
            'name' => 'PCC at '.$code,
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
        ]);
    }

    protected function muPartner(int $centerId, string $name, ?int $projectId = null): int
    {
        return DB::table('partners')->insertGetId([
            'code' => 'COOP-'.Str::upper(Str::random(6)),
            'name' => $name,
            'center_id' => $centerId,
            'project_id' => $projectId ?? $this->muProject(),
            'program' => 'cbed',
            'status' => 'active',
            'category' => 'cooperative',
        ]);
    }

    protected function muProduction(int $centerId, int $year, int $month, array $overrides = []): int
    {
        return DB::table('milk_productions')->insertGetId(array_merge([
            'center_id' => $centerId,
            'program' => 'ih',
            'year' => $year,
            'month' => $month,
            'period_start' => sprintf('%04d-%02d-01 00:00:00', $year, $month),
            'period_end' => sprintf('%04d-%02d-%02d 00:00:00', $year, $month, (int) date('t', mktime(0, 0, 0, $month, 1, $year))),
            'transaction_date' => sprintf('%04d-%02d-01', $year, $month),
            'total_volume_produced' => 0,
            'status' => 'draft',
        ], $overrides));
    }

    protected function muUsage(int $productionId, float $fedToCalves): int
    {
        return DB::table('milk_usages')->insertGetId([
            'milk_production_id' => $productionId,
            'fed_to_calves' => $fedToCalves,
            'family_consumption' => 0,
        ]);
    }

    protected function muTraded(int $productionId, float $volume): int
    {
        return DB::table('milk_tradeds')->insertGetId([
            'milk_production_id' => $productionId,
            'total_volume_traded' => $volume,
        ]);
    }

    protected function muWastage(int $productionId, float $volume): int
    {
        return DB::table('milk_wastages')->insertGetId([
            'milk_production_id' => $productionId,
            'milk_spoiled_volume' => $volume,
            'milk_spoiled_cost' => 0,
            'milk_spoiled_price' => 0,
        ]);
    }

    protected function muMilkFeeding(int $centerId, int $year, float $rawMilkUsed, array $overrides = []): int
    {
        return DB::table('milk_feeding_programs')->insertGetId(array_merge([
            'project_id' => $this->muProject(),
            'center_id' => $centerId,
            'program' => 'ih',
            'name' => 'Milk Feeding Program',
            'year' => $year,
            'raw_milk_used' => $rawMilkUsed,
            'status' => 'planned',
        ], $overrides));
    }

    protected function muDairyBox(int $centerId, int $year, int $month, float $totalSales, array $overrides = []): int
    {
        return DB::table('dairy_box_sales')->insertGetId(array_merge([
            'center_id' => $centerId,
            'total_sales' => $totalSales,
            'year' => $year,
            'month' => $month,
        ], $overrides));
    }

    protected function muRow(array $rows, string $key): ?array
    {
        foreach ($rows as $row) {
            if (($row['key'] ?? null) === $key) {
                return $row;
            }
        }

        return null;
    }
}

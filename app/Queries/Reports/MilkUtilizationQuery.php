<?php

namespace App\Queries\Reports;

use App\Enums\CenterEnum;
use App\Enums\MonthEnum;
use App\Support\Centers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MilkUtilizationQuery
{
    private const CACHE_TTL = 3600;

    public function institutional(int $year, int $month, ?int $centerId = null): array
    {
        $sectionKey = 'institutional';
        $program = (string) config('milk_utilization.sections.institutional.program');

        $production = $this->productionAggregates($program, $year, $month, $centerId);
        $lactating = $this->lastEntryLactating($program, $year, $month, $centerId);

        $dairyBox = $this->dairyBoxAggregates($year, $month, $centerId, underCenterOnly: true);

        $milkFeeding = $this->milkFeedingAggregates($year, $month, $program, $centerId);

        $monthlySummary = $this->monthlySummary($program, $year, $month, $centerId);

        $centerIds = $this->centerIdsByCode();
        $rowOrder = (array) config('milk_utilization.institutional.row_order', []);

        $rows = [];

        foreach ($rowOrder as $code) {
            $rowCenterId = $centerIds[$code] ?? null;
            $inFilter = $this->inFilter($centerId, $rowCenterId);
            $isGenepool = $code === config('milk_utilization.institutional.genepool_code');

            $rows[] = [
                'key' => (string) $code,
                'label' => $this->centerLabel((string) $code, $sectionKey),
                'kind' => 'center',
                'is_genepool' => $isGenepool,
                'has_children' => false,
                'in_filter' => $inFilter,
                'center_id' => $rowCenterId,
                'partner_id' => null,

                'raw' => $inFilter
                    ? $this->composeRaw($production, $lactating, $dairyBox, $milkFeeding, $monthlySummary, $rowCenterId, null, false)
                    : $this->emptyRaw(),
            ];
        }

        return array_values(array_filter(
            $rows,
            fn (array $row) => $centerId === null || $row['in_filter'],
        ));
    }

    public function cbed(int $year, int $month, ?int $centerId = null): array
    {
        $sectionKey = 'cbed';
        $program = (string) config('milk_utilization.sections.cbed.program');

        $production = $this->productionAggregates($program, $year, $month, $centerId);
        $lactating = $this->lastEntryLactating($program, $year, $month, $centerId);

        $dairyBox = $this->dairyBoxAggregates($year, $month, $centerId, underCenterOnly: false);
        $milkFeeding = $this->milkFeedingAggregates($year, $month, $program, $centerId);

        $monthlySummary = $this->monthlySummary($program, $year, $month, $centerId);
        $partnerNames = $this->partnerNamesForMaps($production, $lactating, $dairyBox, $milkFeeding, $monthlySummary);

        $centerIds = $this->centerIdsByCode();
        $rowOrder = (array) config('milk_utilization.cbed.row_order', []);

        $rows = [];

        foreach ($rowOrder as $code) {
            $rowCenterId = $centerIds[$code] ?? null;
            $inFilter = $this->inFilter($centerId, $rowCenterId);

            $centerRaw = $this->composeRaw(
                $production,
                $lactating,
                $dairyBox,
                $milkFeeding,
                $monthlySummary,
                $rowCenterId,
                null,
                false,
            );

            $children = [];
            foreach ($partnerNames as $partnerId => $partnerName) {
                $partnerKey = $this->groupKey((int) $rowCenterId, $partnerId);
                if ($rowCenterId === null || ! str_starts_with($partnerKey, $rowCenterId.':')) {
                    continue;
                }

                $hasData = array_key_exists($partnerKey, $production)
                    || array_key_exists($partnerKey, $lactating)
                    || array_key_exists($partnerKey, $dairyBox)
                    || array_key_exists($partnerKey, $milkFeeding)
                    || array_key_exists($partnerKey, $monthlySummary);

                if (! $hasData) {
                    continue;
                }

                $children[] = [
                    'key' => $code.'-'.$partnerId,
                    'label' => $partnerName,
                    'kind' => 'enterprise',
                    'center_id' => $rowCenterId,
                    'partner_id' => $partnerId,
                    'raw' => $this->composeRaw(
                        $production,
                        $lactating,
                        $dairyBox,
                        $milkFeeding,
                        $monthlySummary,
                        $rowCenterId,
                        $partnerId,
                        true,
                    ),
                ];
            }

            $rows[] = [
                'key' => (string) $code,
                'label' => $this->centerLabel((string) $code, $sectionKey),
                'kind' => 'center',
                'is_genepool' => false,
                'has_children' => $children !== [],
                'in_filter' => $inFilter,
                'center_id' => $rowCenterId,
                'partner_id' => null,
                'raw' => $inFilter ? $centerRaw : $this->emptyRaw(),
                'children' => $children,
            ];
        }

        return array_values(array_filter(
            $rows,
            fn (array $row) => $centerId === null || $row['in_filter'],
        ));
    }

    public function latestPeriod(?int $year = null): array
    {
        return Cache::remember('milk_utilization_latest_period_'.($year ?? 'all'), self::CACHE_TTL, function () use ($year) {
            $query = DB::table('milk_productions')
                ->select('year', 'month')
                ->whereNotNull('year')
                ->whereNotNull('month');

            if ($year !== null) {
                $query->where('year', $year);
            }

            $row = $query->orderByDesc('year')->orderByDesc('month')->first();

            return [
                'year' => (int) ($row->year ?? now()->year),
                'month' => (int) ($row->month ?? now()->month),
            ];
        });
    }

    public function years(): array
    {
        return Cache::remember('milk_utilization_years', self::CACHE_TTL, fn () => DB::table('milk_productions')
            ->select('year')
            ->distinct()
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all());
    }

    public function months(): array
    {
        return collect(MonthEnum::labels())
            ->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public function centerOptions(): array
    {
        return Centers::getCenterOptions()
            ->reject(fn ($option) => $option['id'] === 'all' || $option['id'] === null)
            ->map(fn ($option) => ['value' => (int) $option['id'], 'label' => $option['label']])
            ->values()
            ->all();
    }

    public function genepool(): ?array
    {
        $enum = CenterEnum::GP;
        $code = $enum->code();
        $id = $this->centerIdsByCode()[$code] ?? null;

        return [
            'id' => $id === null ? null : (int) $id,
            'code' => $code,
            'label' => (string) config('milk_utilization.institutional.labels.GP', Centers::displayLabel($enum)),
            'name' => (string) config('milk_utilization.institutional.aliases.GP', $enum->fullName()),
        ];
    }

    private function centerLabel(string $code, string $sectionKey): string
    {
        $override = config("milk_utilization.{$sectionKey}.labels.{$code}");

        return is_string($override) && $override !== '' ? $override : $code;
    }

    private function centerIdsByCode(): array
    {
        return DB::table('centers')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    private function productionAggregates(string $program, int $year, int $month, ?int $centerId): array
    {

        $records = DB::query()
            ->selectRaw(implode(', ', [
                'id, center_id, partner_id, total_volume_produced',
                'volume_from_farmers, number_of_lactating, avg_milk_per_animal_per_day',
            ]))
            ->from('milk_productions')
            ->where('year', $year)
            ->where('month', '<=', $month)
            ->where('program', $program)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId));

        $query = DB::query()->fromSub($records, 'g')
            ->selectRaw('g.center_id, g.partner_id')
            ->selectRaw('SUM(g.total_volume_produced) AS production')
            ->selectRaw('SUM(g.volume_from_farmers) AS received')
            ->selectRaw('SUM(u.v) AS fed_to_calves')
            ->selectRaw('SUM(t.v) AS sold_raw_milk')
            ->selectRaw('SUM(w.v) AS total_milk_out')

            ->selectRaw('NULL AS value_added_products')
            ->selectRaw('SUM(g.avg_milk_per_animal_per_day) AS avg_stored_sum')
            ->selectRaw('COUNT(g.avg_milk_per_animal_per_day) AS avg_stored_count')
            ->leftJoinSub(
                DB::query()->from('milk_usages')->selectRaw('milk_production_id, SUM(fed_to_calves) AS v')->groupBy('milk_production_id'),
                'u',
                'u.milk_production_id',
                '=',
                'g.id',
            )
            ->leftJoinSub(
                DB::query()->from('milk_tradeds')->selectRaw('milk_production_id, SUM(total_volume_traded) AS v')->groupBy('milk_production_id'),
                't',
                't.milk_production_id',
                '=',
                'g.id',
            )
            ->leftJoinSub(
                DB::query()->from('milk_wastages')->selectRaw('milk_production_id, SUM(milk_spoiled_volume) AS v')->groupBy('milk_production_id'),
                'w',
                'w.milk_production_id',
                '=',
                'g.id',
            )
            ->groupBy('g.center_id', 'g.partner_id');

        return $this->keyedByGroup($query->get());
    }

    private function monthlySummary(string $program, int $year, int $month, ?int $centerId): array
    {
        $rows = DB::table('milk_utilization_monthly_summary')
            ->select('center_id', 'partner_id', 'month', 'lactating_animals', 'production', 'received', 'fed_to_calves', 'value_added_products', 'milk_feeding')
            ->where('year', $year)
            ->where('month', '<=', $month)
            ->where('program', $program)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->orderBy('month')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $key = $this->groupKey((int) $row->center_id, $row->partner_id);
            $result[$key] ??= [
                'lactating_animals' => null,
                'production' => null,
                'received' => null,
                'fed_to_calves' => null,
                'value_added_products' => null,
                'milk_feeding' => null,
            ];

            if ((int) $row->month === $month && is_numeric($row->lactating_animals)) {
                $result[$key]['lactating_animals'] = (float) $row->lactating_animals;
            }

            foreach (['production', 'received', 'fed_to_calves', 'value_added_products', 'milk_feeding'] as $column) {
                if (is_numeric($row->{$column})) {
                    $result[$key][$column] = ($result[$key][$column] ?? 0.0) + (float) $row->{$column};
                }
            }
        }

        return $result;
    }

    private function lastEntryLactating(string $program, int $year, int $month, ?int $centerId): array
    {
        $date = "COALESCE(STR_TO_DATE(LEFT(COALESCE(NULLIF(period_start, ''), transaction_date), 10), '%Y-%m-%d'), '1000-01-01')";

        $rows = DB::table('milk_productions')
            ->select('center_id', 'partner_id', 'number_of_lactating')
            ->where('year', $year)
            ->where('month', $month)
            ->where('program', $program)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->orderByRaw("{$date} DESC")
            ->orderByDesc('id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $key = $this->groupKey((int) $row->center_id, $row->partner_id);

            if (! array_key_exists($key, $result)) {
                $result[$key] = is_numeric($row->number_of_lactating) ? (float) $row->number_of_lactating : null;
            }
        }

        return $result;
    }

    private function dairyBoxAggregates(int $year, int $month, ?int $centerId, bool $underCenterOnly): array
    {
        if (config('milk_utilization.dairy_box.mode') !== 'volume') {
            return [];
        }

        $query = DB::table('dairy_box_sales')
            ->selectRaw('center_id, partner_id, SUM(total_sales) AS v')
            ->where('year', $year)
            ->where('month', '<=', $month)
            ->when($centerId !== null, fn ($q) => $q->where('center_id', $centerId))
            ->when($underCenterOnly, fn ($q) => $q->whereNull('partner_id'))
            ->groupBy('center_id', 'partner_id');

        return $this->keyedByGroup($query->get());
    }

    private function milkFeedingAggregates(int $year, int $month, string $program, ?int $centerId): array
    {
        if (config('milk_utilization.milk_feeding.mode') !== 'compatible') {
            return [];
        }

        // The legacy source has a year but no reporting month. It cannot be
        // included in a January-to-selected-month report without overstating K.
        if ($month < 12) {
            return [];
        }

        $query = DB::table('milk_feeding_programs')
            ->selectRaw('center_id, partner_id, SUM(raw_milk_used) AS v')
            ->where('year', $year)
            ->where('program', $program)
            ->when($centerId !== null, fn ($q) => $q->where('center_id', $centerId))
            ->groupBy('center_id', 'partner_id');

        return $this->keyedByGroup($query->get());
    }

    private function composeRaw(
        array $production,
        array $lactating,
        array $dairyBox,
        array $milkFeeding,
        array $monthlySummary,
        ?int $centerId,
        ?int $partnerId,
        bool $partnerScoped,
    ): array {
        if ($centerId === null) {
            return $this->emptyRaw();
        }

        $keys = $partnerScoped && $partnerId !== null
            ? [$this->groupKey($centerId, $partnerId)]
            : array_values(array_unique(array_merge(
                $this->keysForCenter($production, $centerId),
                $this->keysForCenter($lactating, $centerId),
                $this->keysForCenter($dairyBox, $centerId),
                $this->keysForCenter($milkFeeding, $centerId),
                $this->keysForCenter($monthlySummary, $centerId),
            )));

        $groupKey = $this->groupKey($centerId, $partnerId);

        $sum = static function (array $map, array $keys, string $column): ?float {
            $total = null;

            foreach ($keys as $key) {
                $value = $map[$key][$column] ?? null;

                if (! is_numeric($value)) {
                    continue;
                }

                $total = ($total ?? 0) + (float) $value;
            }

            return $total === null ? null : round($total, 2);
        };

        $firstOf = static function (array $map, array $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $map) && $map[$key] !== null) {
                    return $map[$key];
                }
            }

            return null;
        };

        $sumPreferred = static function (
            array $preferred,
            string $preferredColumn,
            array $fallback,
            string $fallbackColumn,
            array $keys,
        ): ?float {
            $total = null;

            foreach ($keys as $key) {
                $value = $preferred[$key][$preferredColumn] ?? null;
                if (! is_numeric($value)) {
                    $value = $fallback[$key][$fallbackColumn] ?? null;
                }

                if (is_numeric($value)) {
                    $total = ($total ?? 0.0) + (float) $value;
                }
            }

            return $total === null ? null : round($total, 2);
        };

        $manualLactating = $monthlySummary[$groupKey]['lactating_animals'] ?? null;

        return [
            'lactating_animals' => $manualLactating ?? $firstOf($lactating, $keys),
            'production' => $sumPreferred($monthlySummary, 'production', $production, 'production', $keys),
            'received' => $sumPreferred($monthlySummary, 'received', $production, 'received', $keys),
            'fed_to_calves' => $sumPreferred($monthlySummary, 'fed_to_calves', $production, 'fed_to_calves', $keys),
            'sold_raw_milk' => $sum($production, $keys, 'sold_raw_milk'),
            'total_milk_out' => $sum($production, $keys, 'total_milk_out'),

            'value_added_products' => $sum($monthlySummary, $keys, 'value_added_products'),
            'dairy_box' => $sum($dairyBox, $keys, 'v'),
            'milk_feeding' => config('milk_utilization.milk_feeding.mode') === 'compatible'
                ? $sumPreferred($monthlySummary, 'milk_feeding', $milkFeeding, 'v', $keys)
                : null,
            'avg_stored_sum' => $sum($production, $keys, 'avg_stored_sum'),
            'avg_stored_count' => (int) ($sum($production, $keys, 'avg_stored_count') ?? 0),
        ];
    }

    private function partnerNamesForMaps(array ...$maps): array
    {
        $partnerIds = [];

        foreach ($maps as $map) {
            foreach (array_keys($map) as $key) {
                [, $partnerId] = array_pad(explode(':', (string) $key, 2), 2, '0');
                if ((int) $partnerId > 0) {
                    $partnerIds[] = (int) $partnerId;
                }
            }
        }

        if ($partnerIds === []) {
            return [];
        }

        return DB::table('partners')
            ->whereIn('id', array_values(array_unique($partnerIds)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    private function keysForCenter(array $map, int $centerId): array
    {
        return array_values(array_filter(
            array_keys($map),
            fn (string $key) => str_starts_with($key, $centerId.':'),
        ));
    }

    private function emptyRaw(): array
    {
        return [
            'lactating_animals' => null,
            'production' => null,
            'received' => null,
            'fed_to_calves' => null,
            'sold_raw_milk' => null,
            'total_milk_out' => null,
            'value_added_products' => null,
            'dairy_box' => null,
            'milk_feeding' => null,
            'avg_stored_sum' => null,
            'avg_stored_count' => 0,
        ];
    }

    private function keyedByGroup($rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $data = (array) $row;

            $result[$this->groupKey((int) ($data['center_id'] ?? 0), $data['partner_id'] ?? null)] = $data;
        }

        return $result;
    }

    private function groupKey(int $centerId, mixed $partnerId): string
    {
        return $centerId.':'.($partnerId === null ? '0' : (int) $partnerId);
    }

    private function inFilter(?int $centerId, ?int $rowCenterId): bool
    {
        return $centerId === null || ($rowCenterId !== null && $rowCenterId === $centerId);
    }
}

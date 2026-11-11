<?php

namespace App\Support;

final class MilkUtilizationCalculator
{
    private const AVERAGE_PRECISION = 4;

    public function sourceKeys(): array
    {
        return array_values(array_filter(array_map(
            fn (array $column) => $column['key'] ?? null,
            array_values(config('milk_utilization.columns', []))
        )));
    }

    public function calculateRow(array $raw): array
    {
        $production = $this->num($raw['production'] ?? null);
        $received = $this->num($raw['received'] ?? null);
        $fedToCalves = $this->num($raw['fed_to_calves'] ?? null);
        $valueAdded = $this->num($raw['value_added_products'] ?? null);
        $dairyBox = $this->dairyBox($raw['dairy_box'] ?? null);
        $milkFeeding = $this->num($raw['milk_feeding'] ?? null);
        $soldRaw = $this->num($raw['sold_raw_milk'] ?? null);
        $milkOut = $this->num($raw['total_milk_out'] ?? null);

        $totalAvailable = $this->sum([$production, $received]);

        $pctFed = $this->ratio($fedToCalves, $totalAvailable);

        $processed = $this->sum([$valueAdded, $dairyBox, $milkFeeding]);
        $pctProcessed = $this->ratio($processed, $totalAvailable);
        $pctRawSold = $this->ratio($soldRaw, $totalAvailable);

        $totalProcessedAndSold = $this->sum([$valueAdded, $dairyBox, $milkFeeding, $soldRaw]);
        $pctTotalProcessedAndSold = $this->ratio($totalProcessedAndSold, $totalAvailable);
        $pctMilkOut = $this->ratio($milkOut, $totalAvailable);

        return [
            'lactating_animals' => $this->num($raw['lactating_animals'] ?? null),
            'avg_milk_production' => self::averageProductionPerAnimalPerDay($raw),
            'production' => $production,
            'received' => $received,
            'total_available' => $totalAvailable,
            'fed_to_calves' => $fedToCalves,
            'pct_fed_to_calves' => $pctFed,
            'value_added_products' => $valueAdded,
            'dairy_box' => $dairyBox,
            'milk_feeding' => $milkFeeding,
            'pct_milk_processed' => $pctProcessed,
            'sold_raw_milk' => $soldRaw,
            'pct_raw_milk_sold' => $pctRawSold,
            'total_processed_and_sold' => $totalProcessedAndSold,
            'pct_total_processed_and_sold' => $pctTotalProcessedAndSold,
            'total_milk_out' => $milkOut,
            'pct_total_milk_out' => $pctMilkOut,
            'pct_used' => $this->both($pctFed, $pctTotalProcessedAndSold),
        ];
    }

    public function calculateTotal(array $rows): array
    {
        $summed = [];

        foreach (config('milk_utilization.total_summed_columns', []) as $key) {
            $summed[$key] = $this->sum($this->pluck($rows, $key));
        }

        $lactating = $this->sum($this->pluck($rows, 'lactating_animals'));

        return [
            'lactating_animals' => $lactating,
            'avg_milk_production' => $this->weightedAverage($rows),
            'production' => $summed['production'] ?? null,
            'received' => $summed['received'] ?? null,
            'total_available' => $summed['total_available'] ?? null,
            'fed_to_calves' => $summed['fed_to_calves'] ?? null,
            'pct_fed_to_calves' => $this->ratio($summed['fed_to_calves'] ?? null, $summed['total_available'] ?? null),
            'value_added_products' => $summed['value_added_products'] ?? null,
            'dairy_box' => $summed['dairy_box'] ?? null,
            'milk_feeding' => $summed['milk_feeding'] ?? null,
            'pct_milk_processed' => $this->ratio(
                $this->addAny([$summed['value_added_products'] ?? null, $summed['dairy_box'] ?? null, $summed['milk_feeding'] ?? null]),
                $summed['total_available'] ?? null,
            ),
            'sold_raw_milk' => $summed['sold_raw_milk'] ?? null,
            'pct_raw_milk_sold' => $this->ratio($summed['sold_raw_milk'] ?? null, $summed['total_available'] ?? null),
            'total_processed_and_sold' => $summed['total_processed_and_sold'] ?? null,
            'pct_total_processed_and_sold' => $this->ratio($summed['total_processed_and_sold'] ?? null, $summed['total_available'] ?? null),
            'total_milk_out' => $summed['total_milk_out'] ?? null,
            'pct_total_milk_out' => $this->ratio($summed['total_milk_out'] ?? null, $summed['total_available'] ?? null),
            'pct_used' => $this->both($this->ratio($summed['fed_to_calves'] ?? null, $summed['total_available'] ?? null),
                $this->ratio($summed['total_processed_and_sold'] ?? null, $summed['total_available'] ?? null)),
        ];
    }

    public static function averageProductionPerAnimalPerDay(array $raw): ?float
    {
        $storedSum = $raw['avg_stored_sum'] ?? null;
        $storedCount = (int) ($raw['avg_stored_count'] ?? 0);

        if ($storedCount > 0 && is_numeric($storedSum)) {
            return round((float) $storedSum / $storedCount, self::AVERAGE_PRECISION);
        }

        return null;
    }

    public function warnings(string $sectionKey, string $rowKey, string $rowLabel, array $values): array
    {
        $threshold = (float) config('milk_utilization.warnings.pct_used_over', 100);
        $labels = $this->columnLabels();

        $warnings = [];

        $pctUsed = $values['pct_used'] ?? null;
        if (is_numeric($pctUsed) && (float) $pctUsed > $threshold) {
            $warnings[] = [
                'code' => 'PCT_USED_OVER_100',
                'section' => $sectionKey,
                'row_key' => $rowKey,
                'column' => 'pct_used',
                'value' => (float) $pctUsed,
                'message' => sprintf(
                    '%s: %% Used is %s%% (> %s%%). Not clamped.',
                    $rowLabel,
                    $this->display((float) $pctUsed),
                    $this->display($threshold),
                ),
            ];
        }

        foreach ((array) config('milk_utilization.warnings.columns', []) as $key) {
            $value = $values[$key] ?? null;

            if (! is_numeric($value) || (float) $value >= 0) {
                continue;
            }

            $label = $labels[$key] ?? $key;

            $warnings[] = [
                'code' => 'NEGATIVE_VALUE',
                'section' => $sectionKey,
                'row_key' => $rowKey,
                'column' => $key,
                'value' => (float) $value,
                'message' => sprintf('%s: %s is negative (%s).', $rowLabel, $label, $this->display((float) $value)),
            ];
        }

        return $warnings;
    }

    private function dairyBox(mixed $value): ?float
    {
        return $this->num($value);
    }

    private function weightedAverage(array $rows): ?float
    {
        $weighted = 0.0;
        $weight = 0.0;

        foreach ($rows as $row) {
            $average = $row['avg_milk_production'] ?? null;
            $lactating = $row['lactating_animals'] ?? null;

            if (! is_numeric($average) || ! is_numeric($lactating) || (float) $lactating <= 0) {
                continue;
            }

            $weighted += (float) $average * (float) $lactating;
            $weight += (float) $lactating;
        }

        return $weight > 0 ? round($weighted / $weight, self::AVERAGE_PRECISION) : null;
    }

    private function pluck(array $rows, string $key): array
    {
        return array_map(fn (array $row) => $row[$key] ?? null, $rows);
    }

    private function sum(array $values): ?float
    {
        $present = false;
        $total = 0.0;

        foreach ($values as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $present = true;
            $total += (float) $value;
        }

        return $present ? round($total, 2) : null;
    }

    private function addAny(array $values): ?float
    {
        return $this->sum($values);
    }

    private function both(?float $left, ?float $right): ?float
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $left + $right;
    }

    private function ratio(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator === 0.0) {
            return null;
        }

        return ($numerator / $denominator) * 100;
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function display(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }

    private function columnLabels(): array
    {
        $labels = [];

        foreach ((array) config('milk_utilization.columns', []) as $definition) {
            if (($definition['key'] ?? null) !== null) {
                $labels[$definition['key']] = $definition['label'];
            }
        }

        return $labels;
    }
}

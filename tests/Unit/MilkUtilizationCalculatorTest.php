<?php

namespace Tests\Unit;

use App\Support\MilkUtilizationCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MilkUtilizationCalculatorTest extends TestCase
{
    #[Test]
    public function totals_sum_each_column_and_recompute_ratios_including_dairy_box(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);
        $rows = [
            $calculator->calculateRow([
                'production' => 100, 'received' => 50, 'fed_to_calves' => 10,
                'value_added_products' => 10, 'milk_feeding' => 20,
                'sold_raw_milk' => 5, 'dairy_box' => 10, 'total_milk_out' => 2,
                'lactating_animals' => 5,
            ]),
            $calculator->calculateRow([
                'production' => 300, 'received' => null, 'fed_to_calves' => null,
                'value_added_products' => 30, 'milk_feeding' => 40,
                'sold_raw_milk' => 25, 'dairy_box' => 90, 'total_milk_out' => 3,
                'lactating_animals' => 15,
            ]),
        ];

        $total = $calculator->calculateTotal($rows);

        $this->assertEqualsWithDelta(26.666666667, $rows[0]['pct_milk_processed'], 0.000000001);
        $this->assertEqualsWithDelta(53.333333333, $rows[1]['pct_milk_processed'], 0.000000001);
        $this->assertSame(30.0, $rows[0]['pct_total_processed_and_sold']);
        $this->assertEqualsWithDelta(61.666666667, $rows[1]['pct_total_processed_and_sold'], 0.000000001);

        foreach (config('milk_utilization.total_summed_columns') as $key) {
            $this->assertSame(array_sum(array_column($rows, $key)), $total[$key], $key);
        }

        $this->assertSame(20.0, $total['lactating_animals']);
        $this->assertSame(100.0, $total['dairy_box']);
        $this->assertSame(450.0, $total['total_available']);
        $this->assertSame(230.0, $total['total_processed_and_sold']);
        $this->assertEqualsWithDelta(2.222222222, $total['pct_fed_to_calves'], 0.000000001);
        $this->assertEqualsWithDelta(44.444444444, $total['pct_milk_processed'], 0.000000001);
        $this->assertEqualsWithDelta(6.666666667, $total['pct_raw_milk_sold'], 0.000000001);
        $this->assertEqualsWithDelta(51.111111111, $total['pct_total_processed_and_sold'], 0.000000001);
        $this->assertEqualsWithDelta(1.111111111, $total['pct_total_milk_out'], 0.000000001);
        $this->assertEqualsWithDelta(53.333333333, $total['pct_used'], 0.000000001);
    }

    #[Test]
    public function missing_calves_values_keep_dependent_percentages_unavailable(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);
        $row = $calculator->calculateRow([
            'production' => 200, 'milk_feeding' => 50, 'dairy_box' => 100,
        ]);

        foreach ([$row, $calculator->calculateTotal([$row])] as $values) {
            $this->assertNull($values['pct_fed_to_calves']);
            $this->assertSame(75.0, $values['pct_milk_processed']);
            $this->assertSame(150.0, $values['total_processed_and_sold']);
            $this->assertNull($values['pct_used']);
        }
    }

    #[Test]
    public function it_reproduces_the_verified_all_nonzero_formula_case(): void
    {
        $values = app(MilkUtilizationCalculator::class)->calculateRow([
            'production' => 1000,
            'received' => 200,
            'fed_to_calves' => 100,
            'value_added_products' => 150,
            'dairy_box' => 50,
            'milk_feeding' => 100,
            'sold_raw_milk' => 200,
            'total_milk_out' => 60,
        ]);

        $this->assertSame(1200.0, $values['total_available']);
        $this->assertEqualsWithDelta(8.333333333, $values['pct_fed_to_calves'], 0.000000001);
        $this->assertSame(25.0, $values['pct_milk_processed']);
        $this->assertEqualsWithDelta(16.666666667, $values['pct_raw_milk_sold'], 0.000000001);
        $this->assertSame(500.0, $values['total_processed_and_sold']);
        $this->assertEqualsWithDelta(41.666666667, $values['pct_total_processed_and_sold'], 0.000000001);
        $this->assertSame(5.0, $values['pct_total_milk_out']);
        $this->assertSame(50.0, $values['pct_used']);
    }

    private function workbookCenters(): array
    {
        return [
            'CSU' => ['CSU', 21416.60],
            'MMSU' => ['MMSU', 27638.50],
            'DMMMSU' => ['DMMMSU', 0.0],
            'CLSU' => ['CLSU', 42152.28],
            'UPLB' => ['UPLB', 25973.68],
            'WVSU' => ['WVSU', 12810.10],
            'VSU' => ['VSU', 33166.80],
            'LCSF' => ['LCSF', 23229.35],
            'USF' => ['USF', 50292.42],
            'CMU' => ['CMU', 75156.52],
            'USM' => ['USM', 29170.20],
            'MLPC' => ['MLPC', 27559.62],
            'GP' => ['GP', 151268.59],
        ];
    }

    private function vsuRaw(): array
    {
        return [
            'lactating_animals' => 16,
            'avg_stored_sum' => 5.435,
            'avg_stored_count' => 1,
            'avg_computed_sum' => null,
            'avg_computed_count' => 0,
            'production' => 33166.80,
            'received' => 0,
            'fed_to_calves' => 585.70,
            'value_added_products' => 1112.50,
            'dairy_box' => 0,
            'milk_feeding' => 0,
            'sold_raw_milk' => 0,
            'total_milk_out' => 0,
        ];
    }

    #[Test]
    public function it_reproduces_the_vsu_golden_row(): void
    {
        $values = app(MilkUtilizationCalculator::class)->calculateRow($this->vsuRaw());

        $this->assertSame(16.0, $values['lactating_animals']);
        $this->assertSame(5.435, $values['avg_milk_production']);
        $this->assertSame(33166.80, $values['production']);
        $this->assertSame(0.0, $values['received']);
        $this->assertSame(33166.80, $values['total_available']);
        $this->assertSame(585.70, $values['fed_to_calves']);
        $this->assertEqualsWithDelta(1.765922549, $values['pct_fed_to_calves'], 0.000000001);
        $this->assertSame(1112.50, $values['value_added_products']);
        $this->assertSame(0.0, $values['dairy_box']);
        $this->assertSame(0.0, $values['milk_feeding']);
        $this->assertEqualsWithDelta(3.354257872, $values['pct_milk_processed'], 0.000000001);
        $this->assertSame(0.0, $values['sold_raw_milk']);
        $this->assertSame(0.0, $values['pct_raw_milk_sold']);
        $this->assertSame(1112.50, $values['total_processed_and_sold']);
        $this->assertEqualsWithDelta(3.354257872, $values['pct_total_processed_and_sold'], 0.000000001);
        $this->assertSame(0.0, $values['total_milk_out']);
        $this->assertSame(0.0, $values['pct_total_milk_out']);
        $this->assertEqualsWithDelta(5.120180421, $values['pct_used'], 0.000000001);
    }

    #[Test]
    public function it_keeps_ratio_precision_until_presentation(): void
    {
        $values = app(MilkUtilizationCalculator::class)->calculateRow($this->vsuRaw());

        $this->assertEqualsWithDelta(
            $values['pct_fed_to_calves'] + $values['pct_total_processed_and_sold'],
            $values['pct_used'],
            0.000000000001,
        );

        $third = app(MilkUtilizationCalculator::class)->calculateRow([
            'production' => 3,
            'received' => 0,
            'fed_to_calves' => 1,
            'value_added_products' => 0,
            'dairy_box' => 0,
            'milk_feeding' => 1,
            'sold_raw_milk' => 0,
            'total_milk_out' => 0,
        ]);

        $this->assertEqualsWithDelta(33.333333333, $third['pct_fed_to_calves'], 0.000000001);
        $this->assertEqualsWithDelta(33.333333333, $third['pct_milk_processed'], 0.000000001);

        $this->assertEqualsWithDelta(66.666666667, $third['pct_used'], 0.000000001);
    }

    #[Test]
    public function it_reproduces_the_total_golden_row_across_all_workbook_centers(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $rows = [];
        foreach ($this->workbookCenters() as $key => [$label, $production]) {
            $raw = $this->vsuRaw();
            $raw['production'] = $production;

            if ($key !== 'VSU') {
                $raw['fed_to_calves'] = 0;
                $raw['value_added_products'] = 0;
                $raw['lactating_animals'] = null;
                $raw['avg_stored_sum'] = null;
                $raw['avg_stored_count'] = 0;
            }

            $rows[] = $calculator->calculateRow($raw);
        }

        $total = $calculator->calculateTotal($rows);

        $this->assertSame(519834.66, $total['production']);
        $this->assertSame(0.0, $total['received']);
        $this->assertSame(519834.66, $total['total_available']);
        $this->assertSame(585.70, $total['fed_to_calves']);
        $this->assertEqualsWithDelta(0.112670440, $total['pct_fed_to_calves'], 0.000000001);
        $this->assertSame(1112.50, $total['value_added_products']);
        $this->assertSame(1112.50, $total['total_processed_and_sold']);
        $this->assertEqualsWithDelta(0.214010355, $total['pct_milk_processed'], 0.000000001);
        $this->assertEqualsWithDelta(0.214010355, $total['pct_total_processed_and_sold'], 0.000000001);
        $this->assertEqualsWithDelta(0.326680795, $total['pct_used'], 0.000000001);
    }

    #[Test]
    public function an_all_zero_row_leaves_the_ratios_null_instead_of_zero_or_an_error(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $row = $calculator->calculateRow([
            'production' => 0,
            'received' => 0,
            'fed_to_calves' => 0,
            'value_added_products' => 0,
            'dairy_box' => 0,
            'milk_feeding' => 0,
            'sold_raw_milk' => 0,
            'total_milk_out' => 0,
        ]);

        $this->assertSame(0.0, $row['total_available'], 'A real zero stays 0.');
        $this->assertNull($row['pct_fed_to_calves']);
        $this->assertNull($row['pct_milk_processed']);
        $this->assertNull($row['pct_raw_milk_sold']);
        $this->assertNull($row['pct_total_processed_and_sold']);
        $this->assertNull($row['pct_total_milk_out']);
        $this->assertNull($row['pct_used']);
    }

    #[Test]
    public function a_row_with_no_records_at_all_is_null_everywhere(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $row = $calculator->calculateRow([]);

        foreach (array_keys($row) as $key) {
            $this->assertNull($row[$key], "{$key} should be null, not a measured zero.");
        }

        $this->assertNull($calculator->calculateTotal([$row])['pct_used']);
    }

    #[Test]
    public function a_missing_denominator_leaves_every_dependent_ratio_null(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $row = $calculator->calculateRow(['fed_to_calves' => 100]);

        $this->assertSame(100.0, $row['fed_to_calves']);
        $this->assertNull($row['total_available']);
        $this->assertNull($row['pct_fed_to_calves']);
        $this->assertNull($row['pct_milk_processed']);
        $this->assertNull($row['pct_raw_milk_sold']);
        $this->assertNull($row['pct_total_processed_and_sold']);
        $this->assertNull($row['pct_total_milk_out']);
        $this->assertNull($row['pct_used']);

        $this->assertNull($calculator->calculateTotal([$row])['pct_fed_to_calves']);
    }

    #[Test]
    public function it_warns_but_never_clamps_a_pct_used_over_one_hundred(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $row = $calculator->calculateRow([
            'production' => 100,
            'received' => 0,
            'fed_to_calves' => 200,
            'value_added_products' => 0,
            'dairy_box' => 0,
            'milk_feeding' => 0,
            'sold_raw_milk' => 0,
            'total_milk_out' => 0,
        ]);

        $this->assertSame(200.0, $row['pct_used'], 'The value is reported as-is.');
        $this->assertSame(200.0, $row['pct_fed_to_calves']);

        $warnings = $calculator->warnings('institutional', 'X', 'X Center', $row);

        $this->assertCount(1, $warnings);
        $this->assertSame('PCT_USED_OVER_100', $warnings[0]['code']);
        $this->assertSame(200.0, $warnings[0]['value']);
        $this->assertStringContainsString('Not clamped', $warnings[0]['message']);
    }

    #[Test]
    public function it_warns_about_negative_values_without_rewriting_them(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $row = $calculator->calculateRow([
            'production' => 100,
            'received' => 0,
            'fed_to_calves' => -10,
            'value_added_products' => 0,
            'dairy_box' => 0,
            'milk_feeding' => 0,
            'sold_raw_milk' => 0,
            'total_milk_out' => 0,
        ]);

        $this->assertSame(-10.0, $row['fed_to_calves'], 'The negative value is preserved.');

        $codes = array_column($calculator->warnings('institutional', 'X', 'X Center', $row), 'code');

        $this->assertContains('NEGATIVE_VALUE', $codes);
    }

    #[Test]
    public function the_total_row_recomputes_percentages_instead_of_averaging_them(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $rows = [
            $calculator->calculateRow(['production' => 100, 'received' => 0, 'fed_to_calves' => 50]),
            $calculator->calculateRow(['production' => 100, 'received' => 0, 'fed_to_calves' => 0]),
        ];

        $total = $calculator->calculateTotal($rows);

        $this->assertSame(200.0, $total['total_available']);
        $this->assertSame(25.0, $total['pct_fed_to_calves']);
    }

    #[Test]
    public function the_total_row_sums_lactating_animals_and_weights_the_average(): void
    {
        $calculator = app(MilkUtilizationCalculator::class);

        $rows = [
            $calculator->calculateRow([
                'lactating_animals' => 10,
                'avg_stored_sum' => 4.0,
                'avg_stored_count' => 1,
                'production' => 100,
                'received' => 0,
            ]),
            $calculator->calculateRow([
                'lactating_animals' => 30,
                'avg_stored_sum' => 6.0,
                'avg_stored_count' => 1,
                'production' => 100,
                'received' => 0,
            ]),
        ];

        $total = $calculator->calculateTotal($rows);

        $this->assertSame(40.0, $total['lactating_animals']);

        $this->assertSame(5.5, $total['avg_milk_production']);
    }
}

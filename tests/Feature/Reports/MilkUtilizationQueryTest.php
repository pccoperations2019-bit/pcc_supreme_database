<?php

namespace Tests\Feature\Reports;

use App\Queries\Reports\MilkUtilizationQuery;
use App\Support\MilkUtilizationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MilkUtilizationQueryTest extends TestCase
{
    use InteractsWithMilkUtilization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMilkUtilization();
    }

    #[Test]
    public function the_year_to_date_window_excludes_the_previous_december_and_later_months(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muProduction($csu, 2022, 12, ['total_volume_produced' => 9999]);
        $this->muProduction($csu, 2023, 1, ['total_volume_produced' => 100]);
        $this->muProduction($csu, 2023, 2, ['total_volume_produced' => 200]);
        $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 300]);
        $this->muProduction($csu, 2023, 4, ['total_volume_produced' => 8888]);

        $values = $this->calculator()->calculateRow($this->row('CSU'));

        $this->assertSame(600.0, $values['production'], 'Jan..Mar only: no 2022, no April.');
        $this->assertSame(600.0, $values['total_available']);
    }

    #[Test]
    public function column_b_uses_the_last_entry_record_inside_the_selected_month(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muProduction($csu, 2023, 1, [
            'number_of_lactating' => 999,
            'period_start' => '2023-01-31 00:00:00',
            'transaction_date' => '2023-01-31',
        ]);
        $this->muProduction($csu, 2023, 3, [
            'number_of_lactating' => 20,
            'period_start' => '2023-03-05 00:00:00',
            'transaction_date' => '2023-03-05',
        ]);
        $this->muProduction($csu, 2023, 3, [
            'number_of_lactating' => 44,
            'period_start' => '2023-03-20 00:00:00',
            'transaction_date' => '2023-03-20',
        ]);
        $this->muProduction($csu, 2023, 3, [
            'number_of_lactating' => 7,
            'period_start' => '2023-03-31 00:00:00',
            'transaction_date' => '2023-03-31',
        ]);

        $values = $this->calculator()->calculateRow($this->row('CSU'));

        $this->assertSame(7.0, $values['lactating_animals']);
    }

    #[Test]
    public function two_records_sharing_a_date_in_the_selected_month_are_broken_by_id_descending(): void
    {
        $csu = $this->muCenter('CSU');

        $first = $this->muProduction($csu, 2023, 3, [
            'number_of_lactating' => 11,
            'period_start' => '2023-03-15 00:00:00',
            'transaction_date' => '2023-03-15',
        ]);
        $second = $this->muProduction($csu, 2023, 3, [
            'number_of_lactating' => 22,
            'period_start' => '2023-03-15 00:00:00',
            'transaction_date' => '2023-03-15',
        ]);

        $this->assertGreaterThan($first, $second);

        $values = $this->calculator()->calculateRow($this->row('CSU'));

        $this->assertSame(22.0, $values['lactating_animals'], 'The higher id wins the tie.');
    }

    #[Test]
    public function the_institutional_and_cbed_blocks_never_see_each_others_records(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muProduction($csu, 2023, 3, ['program' => 'ih', 'total_volume_produced' => 100]);
        $this->muProduction($csu, 2023, 3, ['program' => 'cbed', 'total_volume_produced' => 700]);

        $institutional = $this->calculator()->calculateRow($this->row('CSU'));
        $cbed = $this->calculator()->calculateRow($this->cbedRaw('CSU'));

        $this->assertSame(100.0, $institutional['production']);
        $this->assertSame(700.0, $cbed['production']);
    }

    #[Test]
    public function the_cbed_center_subtotal_folds_in_the_records_that_carry_no_partner(): void
    {
        $csu = $this->muCenter('CSU');
        $coop = $this->muPartner($csu, 'Test Dairy Cooperative');

        $this->muProduction($csu, 2023, 3, [
            'program' => 'cbed',
            'partner_id' => null,
            'total_volume_produced' => 50,
        ]);
        $this->muProduction($csu, 2023, 3, [
            'program' => 'cbed',
            'partner_id' => $coop,
            'total_volume_produced' => 30,
        ]);

        $meta = $this->cbedRow('CSU');

        $this->assertSame(80.0, $meta['raw']['production'], 'Residual (50) + enterprise (30) in the subtotal.');

        $this->assertTrue($meta['has_children']);
        $this->assertCount(1, $meta['children']);
        $this->assertSame('Test Dairy Cooperative', $meta['children'][0]['label']);
        $this->assertSame(30.0, $meta['children'][0]['raw']['production']);
    }

    #[Test]
    public function manual_monthly_values_are_accumulated_only_through_the_selected_month(): void
    {
        $csu = $this->muCenter('CSU');

        foreach ([1 => 10, 2 => 20, 3 => 30, 4 => 999] as $month => $value) {
            DB::table('milk_utilization_monthly_summary')->insert([
                'center_id' => $csu,
                'partner_id' => null,
                'program' => 'ih',
                'year' => 2023,
                'month' => $month,
                'lactating_animals' => $month === 3 ? 7 : null,
                'production' => $value,
                'received' => $value,
                'fed_to_calves' => $value,
                'value_added_products' => $value,
                'milk_feeding' => $value,
            ]);
        }

        $raw = $this->row('CSU');

        $this->assertSame(7.0, $raw['lactating_animals']);
        foreach (['production', 'received', 'fed_to_calves', 'value_added_products'] as $column) {
            $this->assertSame(60.0, $raw[$column], $column);
        }
        $this->assertNull($raw['milk_feeding'], 'Litres are not mixed into kilogram totals without an approved conversion.');
    }

    #[Test]
    public function dairy_box_peso_sales_are_not_reported_as_milk_volume(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muDairyBox($csu, 2023, 3, 4960.74);

        $this->assertNull($this->row('CSU')['dairy_box']);
    }

    #[Test]
    public function a_center_with_no_records_still_gets_a_row_with_every_value_null(): void
    {
        $this->muCenter('CSU');
        $this->muCenter('MMSU');

        $meta = $this->rowMeta('MMSU');
        $raw = $meta['raw'];

        $this->assertSame('MMSU', $meta['label']);
        $this->assertNull($raw['production']);
        $this->assertNull($raw['lactating_animals']);

        $values = $this->calculator()->calculateRow($raw);

        $this->assertNull($values['production']);
        $this->assertNull($values['pct_used']);
    }

    #[Test]
    public function a_center_with_records_but_zero_volume_yields_an_em_dash_rather_than_zero(): void
    {
        $csu = $this->muCenter('CSU');

        $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 0]);

        $values = $this->calculator()->calculateRow($this->row('CSU'));

        $this->assertSame(0.0, $values['production'], 'A captured zero is a zero.');
        $this->assertSame(0.0, $values['total_available']);
        $this->assertNull($values['pct_fed_to_calves']);
        $this->assertNull($values['pct_milk_processed']);
        $this->assertNull($values['pct_used']);
    }

    #[Test]
    public function genepool_is_emitted_as_its_own_peer_row_and_is_included_in_the_total(): void
    {
        $csu = $this->muCenter('CSU');
        $genepool = $this->muCenter('GP');

        $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 100]);

        $this->muProduction($genepool, 2023, 3, ['total_volume_produced' => 151268.59]);

        $rows = app(MilkUtilizationQuery::class)->institutional(2023, 3);

        $this->assertSame('GP', $this->rowMeta('GP')['label']);

        $values = array_map(
            fn (array $row) => $this->calculator()->calculateRow($row['raw']),
            $rows,
        );

        $total = $this->calculator()->calculateTotal($values);

        $this->assertSame(151368.59, $total['production']);
    }

    #[Test]
    public function the_child_tables_contribute_to_their_own_columns(): void
    {
        $csu = $this->muCenter('CSU');

        $production = $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 1000]);
        $this->muUsage($production, 100);
        $this->muTraded($production, 200);
        $this->muWastage($production, 50);

        $values = $this->calculator()->calculateRow($this->row('CSU'));

        $this->assertSame(100.0, $values['fed_to_calves']);
        $this->assertSame(200.0, $values['sold_raw_milk']);
        $this->assertSame(50.0, $values['total_milk_out']);
        $this->assertSame(10.0, $values['pct_fed_to_calves']);
        $this->assertSame(20.0, $values['pct_raw_milk_sold']);
        $this->assertSame(5.0, $values['pct_total_milk_out']);
    }

    #[Test]
    public function a_center_filter_scopes_the_block_to_that_center_only(): void
    {
        $csu = $this->muCenter('CSU');
        $mmsu = $this->muCenter('MMSU');
        $genepool = $this->muCenter('GP');

        $this->muProduction($csu, 2023, 3, ['total_volume_produced' => 100]);
        $this->muProduction($mmsu, 2023, 3, ['total_volume_produced' => 900]);
        $this->muProduction($genepool, 2023, 3, ['total_volume_produced' => 500]);

        foreach ([$csu => ['CSU', 100.0], $mmsu => ['MMSU', 900.0], $genepool => ['GP', 500.0]] as $centerId => [$key, $production]) {
            $rows = app(MilkUtilizationQuery::class)->institutional(2023, 3, $centerId);

            $this->assertSame([$key], array_column($rows, 'key'));
            $values = array_map(fn (array $row) => $this->calculator()->calculateRow($row['raw']), $rows);
            $this->assertSame($production, $values[0]['production']);
            $this->assertSame($production, $this->calculator()->calculateTotal($values)['production']);
        }
    }

    private function calculator(): MilkUtilizationCalculator
    {
        return app(MilkUtilizationCalculator::class);
    }

    private function row(string $key): array
    {
        return $this->rowMeta($key)['raw'];
    }

    private function rowMeta(string $key): array
    {
        $row = $this->muRow(app(MilkUtilizationQuery::class)->institutional(2023, 3), $key);

        $this->assertNotNull($row, "Institutional row {$key} should exist.");

        return $row;
    }

    private function cbedRow(string $key): array
    {
        $row = $this->muRow(app(MilkUtilizationQuery::class)->cbed(2023, 3), $key);

        $this->assertNotNull($row, "CBED row {$key} should exist.");

        return $row;
    }

    private function cbedRaw(string $key): array
    {
        return $this->cbedRow($key)['raw'];
    }
}

<?php

namespace Tests\Unit;

use App\Services\PivotBuilderService;
use App\Services\PivotExportService;
use App\Services\PivotFieldRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class PivotBuilderMultiValueTest extends TestCase
{
    use RefreshDatabase;

    private PivotBuilderService $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PivotBuilderService(new PivotFieldRegistryService);

        $this->createCenter('MV-A', 'Region V', 121.5);
        $this->createCenter('MV-B', 'Region I', 122.5);
    }

    private function createCenter(string $code, string $region, float $longitude): void
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.$code,
            'name' => 'Agency '.$code,
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => $code,
            'name' => 'Center '.$code,
            'agency_id' => $agencyId,
            'status' => 'active',
            'category_size' => 'S',
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => $region,
            'province' => 'Province '.$code,
            'city' => 'City '.$code,
            'barangay' => 'Barangay '.$code,
            'zip_code' => '4400',
            'longitude' => $longitude,
            'latitude' => 13.5,
        ]);
    }

    public function test_every_value_field_survives_the_build(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['code'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
                ['field' => 'longitude', 'agg' => 'sum'],
                ['field' => 'latitude', 'agg' => 'avg'],
            ],
        ]);

        $this->assertSame(
            ['code_count', 'longitude_sum', 'latitude_avg'],
            $result['value_fields']
        );

        $this->assertNotEmpty($result['data']);

        // Each value field used to be written to the same key, so only the last
        // aggregate survived and the earlier ones were silently dropped.
        foreach ($result['data'] as $rowKey => $columns) {
            foreach ($columns as $columnKey => $values) {
                $this->assertSame(
                    ['code_count', 'longitude_sum', 'latitude_avg'],
                    array_keys($values),
                    "Row '{$rowKey}' column '{$columnKey}' lost a value field."
                );
            }
        }
    }

    public function test_value_fields_are_not_overwritten_when_a_column_group_is_set(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['code'],
            'columns' => ['region'],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
                ['field' => 'longitude', 'agg' => 'sum'],
            ],
        ]);

        $this->assertSame(['code_count', 'longitude_sum'], $result['value_fields']);
        $this->assertNotSame([''], $result['columns']);

        $differing = 0;
        foreach ($result['data'] as $rowKey => $columns) {
            foreach ($columns as $values) {
                $this->assertArrayHasKey('code_count', $values, "Row '{$rowKey}'");
                $this->assertArrayHasKey('longitude_sum', $values, "Row '{$rowKey}'");

                if ((float) $values['code_count'] !== (float) $values['longitude_sum']) {
                    $differing++;
                }
            }
        }

        // Guards a payload where both keys exist but carry the same figure, which
        // would mean one aggregate is written twice under two names.
        $this->assertGreaterThan(0, $differing, 'Expected the two aggregates to differ somewhere.');
    }

    public function test_each_value_field_reports_its_own_aggregate(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['code'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
                ['field' => 'longitude', 'agg' => 'sum'],
            ],
        ]);

        $byCode = [];
        foreach ($result['data'] as $rowKey => $columns) {
            foreach ($columns as $values) {
                $byCode[$rowKey] = $values;
            }
        }

        $this->assertArrayHasKey('MV-A', $byCode);
        $this->assertArrayHasKey('MV-B', $byCode);

        $this->assertEquals(1, (float) $byCode['MV-A']['code_count']);
        $this->assertEquals(121.5, (float) $byCode['MV-A']['longitude_sum']);

        $this->assertEquals(1, (float) $byCode['MV-B']['code_count']);
        $this->assertEquals(122.5, (float) $byCode['MV-B']['longitude_sum']);
    }

    public function test_export_writes_a_total_row_under_each_value_column(): void
    {
        $request = [
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['code'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
                ['field' => 'longitude', 'agg' => 'sum'],
            ],
            'filters' => [],
            'show_totals' => true,
        ];

        $result = $this->builder->build($request);
        $sheet = (new Spreadsheet)->getActiveSheet();
        $this->exportFigures($sheet, $result, $request);

        // A = row field, then one column per value field. Totals are a footer
        // row only, so no extra total column may be appended.
        $this->assertSame(3, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
        $this->assertNull($sheet->getCell('D1')->getValue());

        $this->assertSame('Count of Center code', $sheet->getCell('B1')->getValue());
        $this->assertSame('Sum of Longitude', $sheet->getCell('C1')->getValue());

        $this->assertEquals(1, (float) $sheet->getCell('B2')->getValue());
        $this->assertEquals(121.5, (float) $sheet->getCell('C2')->getValue());
        $this->assertEquals(1, (float) $sheet->getCell('B3')->getValue());
        $this->assertEquals(122.5, (float) $sheet->getCell('C3')->getValue());

        $totalRow = $sheet->getHighestRow();
        $this->assertSame('Total', $sheet->getCell("A{$totalRow}")->getValue());
        $this->assertEquals(2, (float) $sheet->getCell("B{$totalRow}")->getValue());
        $this->assertEquals(244.0, (float) $sheet->getCell("C{$totalRow}")->getValue());
    }

    public function test_export_omits_totals_when_not_requested(): void
    {
        $request = [
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['code'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
            'show_totals' => false,
        ];

        $result = $this->builder->build($request);
        $sheet = (new Spreadsheet)->getActiveSheet();
        $this->exportFigures($sheet, $result, $request);

        // A = row field, B = the single value column, and nothing beyond it.
        $this->assertSame(2, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
        $this->assertNull($sheet->getCell('C1')->getValue());
    }

    private function exportFigures($sheet, array $result, array $request): void
    {
        $service = new PivotExportService;
        $method = new ReflectionMethod($service, 'exportFigures');
        $method->setAccessible(true);
        $method->invoke($service, $sheet, $result, $request);
    }
}

<?php

namespace Tests\Unit;

use App\Services\PivotBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PivotBuilderFarmerEnumFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_fields_are_displayed_as_labels(): void
    {
        DB::table('farmers')->insert([
            'code' => 'FARM-ENUM',
            'first_name' => 'Test',
            'status' => 'inactive',
            'category_size' => 'small',
            'category' => 'fca_member',
            'civil_status' => 'solo_parent',
            'main_source_of_income' => 'Dairy Production',
        ]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'farmers',
            'rows' => [
                'status',
                'category_size',
                'category',
                'civil_status',
                'main_source_of_income',
            ],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $row = $result['rows'][0];
        $this->assertSame('Inactive', $row['status']);
        $this->assertSame('Small', $row['category_size']);
        $this->assertSame('FCA Member', $row['category']);
        $this->assertSame('Solo Parent', $row['civil_status']);
        $this->assertSame('Dairy Production', $row['main_source_of_income']);
    }
}

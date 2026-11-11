<?php

namespace Tests\Unit;

use App\Services\PivotBuilderService;
use App\Services\PivotFieldRegistryService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PivotBuilderServiceTest extends TestCase
{
    private PivotBuilderService $builder;

    private PivotFieldRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PivotFieldRegistryService;
        $this->builder = new PivotBuilderService($this->registry);
    }

    public function test_build_with_invalid_source_throws_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->builder->build([
            'source' => 'invalid_source',
            'type' => 'list',
            'rows' => [],
        ]);
    }

    public function test_build_with_invalid_field_throws_422(): void
    {
        $this->expectException(HttpException::class);

        $this->builder->build([
            'source' => 'center',
            'type' => 'list',
            'rows' => ['invalid_field'],
        ]);
    }

    public function test_build_with_valid_center_fields(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'rows' => ['code', 'name'],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_build_figures_with_valid_center_fields(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['status'],
            'columns' => ['province'],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_build_with_invalid_aggregation_throws_422(): void
    {
        $this->expectException(HttpException::class);

        $this->builder->build([
            'source' => 'center',
            'type' => 'figures',
            'rows' => ['status'],
            'columns' => ['province'],
            'values' => [
                ['field' => 'code', 'agg' => 'invalid_agg'],
            ],
        ]);
    }

    public function test_build_with_valid_filter_operators(): void
    {
        $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'like'];

        foreach ($validOperators as $operator) {
            $result = $this->builder->build([
                'source' => 'center',
                'type' => 'list',
                'rows' => ['code'],
                'filters' => [
                    ['field' => 'status', 'op' => $operator, 'value' => 'active'],
                ],
            ]);

            $this->assertIsArray($result);
        }
    }

    public function test_build_with_invalid_filter_operator_throws_422(): void
    {
        $this->expectException(HttpException::class);

        $this->builder->build([
            'source' => 'center',
            'type' => 'list',
            'rows' => ['code'],
            'filters' => [
                ['field' => 'status', 'op' => 'invalid_op', 'value' => 'active'],
            ],
        ]);
    }

    public function test_build_partners_source(): void
    {
        $result = $this->builder->build([
            'source' => 'partners',
            'rows' => ['name', 'category'],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
    }

    public function test_build_farmers_source(): void
    {
        $result = $this->builder->build([
            'source' => 'farmers',
            'rows' => ['first_name', 'last_name'],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
    }

    public function test_build_figures_with_sum_aggregation(): void
    {
        $result = $this->builder->build([
            'source' => 'partners',
            'type' => 'figures',
            'rows' => ['category'],
            'columns' => ['status'],
            'values' => [
                ['field' => 'total_member_count', 'agg' => 'sum'],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
    }

    public function test_build_figures_with_avg_aggregation(): void
    {
        $result = $this->builder->build([
            'source' => 'partners',
            'type' => 'figures',
            'rows' => ['category'],
            'columns' => ['status'],
            'values' => [
                ['field' => 'income_php', 'agg' => 'avg'],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
    }

    public function test_build_empty_rows_returns_empty_result(): void
    {
        $result = $this->builder->build([
            'source' => 'center',
            'rows' => [],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('figures', $result['type']);
        $this->assertEmpty($result['rows']);
    }
}

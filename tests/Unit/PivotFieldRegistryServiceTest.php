<?php

namespace Tests\Unit;

use App\Services\PivotFieldRegistryService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PivotFieldRegistryServiceTest extends TestCase
{
    private PivotFieldRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PivotFieldRegistryService;
    }

    public function test_sources_returns_available_sources(): void
    {
        $sources = $this->registry->sources();

        $this->assertIsArray($sources);
        $this->assertNotEmpty($sources);

        foreach ($sources as $source) {
            $this->assertArrayHasKey('key', $source);
            $this->assertArrayHasKey('label', $source);
        }
    }

    public function test_fields_for_valid_source_returns_fields(): void
    {
        $fields = $this->registry->fieldsFor('center');

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        foreach ($fields as $field) {
            $this->assertArrayHasKey('field', $field);
            $this->assertArrayHasKey('label', $field);
            $this->assertArrayHasKey('type', $field);
        }
    }

    public function test_fields_for_invalid_source_throws_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->registry->fieldsFor('invalid_source');
    }

    public function test_assert_fields_valid_with_valid_fields(): void
    {
        $this->expectNotToPerformAssertions();

        $this->registry->assertFieldsValid('center', ['code', 'name', 'status']);
    }

    public function test_assert_fields_valid_with_invalid_fields_throws_422(): void
    {
        $this->expectException(HttpException::class);

        $this->registry->assertFieldsValid('center', ['code', 'invalid_field']);
    }

    public function test_partners_source_has_expected_fields(): void
    {
        $fields = $this->registry->fieldsFor('partners');

        $fieldNames = array_column($fields, 'field');
        $this->assertContains('name', $fieldNames);
        $this->assertContains('status', $fieldNames);
        $this->assertContains('category', $fieldNames);
    }

    public function test_farmers_source_has_expected_fields(): void
    {
        $fields = $this->registry->fieldsFor('farmers');

        $fieldNames = array_column($fields, 'field');
        $this->assertContains('first_name', $fieldNames);
        $this->assertContains('last_name', $fieldNames);
        $this->assertContains('status', $fieldNames);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\User;
use App\Services\PivotBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PivotBuilderCenterExtendedFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function createCenter(string $code): int
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-001',
            'name' => 'Test Agency',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => $code,
            'name' => 'Test Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'category_size' => 'S',
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'Region V',
            'province' => 'Camarines Sur',
            'district' => '1st District',
            'city' => 'Naga',
            'barangay' => 'San Felipe',
            'zip_code' => '4400',
            'longitude' => 123.186,
            'latitude' => 13.622,
        ]);

        return $centerId;
    }

    private function addLtoDocument(
        string $centerCode,
        string $documentType,
        string $complianceStatus,
        string $validity
    ): void {
        $fileId = DB::table('files')->insertGetId([
            'name' => "{$documentType}.txt",
            'path' => "documents/{$documentType}-{$centerCode}.txt",
            'type' => 'text/plain',
            'size' => 1,
            'uploaded_by' => User::factory()->create([
                'username' => 'user-'.Str::uuid(),
            ])->id,
        ]);

        $document = Document::forceCreate([
            'file_id' => $fileId,
            'title' => strtoupper($documentType),
            'compliance_status' => $complianceStatus,
            'validity' => $validity,
        ]);

        $document->tags()->create([
            'key' => 'document',
            'value' => $documentType,
        ]);

        $document->tags()->create([
            'key' => 'center',
            'value' => $centerCode,
        ]);
    }

    private function addMilkFeedingProgram(int $centerId, string $dateStarted): void
    {
        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
        ]);

        DB::table('milk_feeding_programs')->insert([
            'project_id' => $projectId,
            'center_id' => $centerId,
            'name' => 'Test MFP',
            'year' => 2024,
            'date_started' => $dateStarted,
        ]);
    }

    private function addValueChainUnit(int $centerId, string $table): void
    {
        DB::table($table)->insert([
            'center_id' => $centerId,
            'name' => 'Test Unit',
        ]);
    }

    private function addProduct(int $centerId, string $category, string $name): void
    {
        $productId = DB::table('products')->insertGetId([
            'code' => Str::upper(Str::slug($name)).'-'.Str::uuid(),
            'name' => $name,
            'category' => $category,
        ]);

        DB::table('center_product')->insert([
            'center_id' => $centerId,
            'product_id' => $productId,
        ]);
    }

    public function test_center_address_fields_are_joined_into_the_pivot(): void
    {
        $this->createCenter('TESTC');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['municipality', 'province', 'region', 'zipcode'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Naga', $result['rows'][0]['municipality']);
        $this->assertSame('Camarines Sur', $result['rows'][0]['province']);
        $this->assertSame('Region V (Bicol Region)', $result['rows'][0]['region']);
        $this->assertEquals('4400', $result['rows'][0]['zipcode']);
        $this->assertSame(1, $result['data']['Naga|Camarines Sur|Region V (Bicol Region)|4400']['']['code_count']);
    }

    public function test_fda_and_nda_lto_use_latest_document_with_mapped_compliance_status(): void
    {
        $this->createCenter('TESTL');

        $this->addLtoDocument('TESTL', 'fda-lto', 'for_processing', '2021-01-01');
        $this->addLtoDocument('TESTL', 'fda-lto', 'accredited', '2025-06-01');
        $this->addLtoDocument('TESTL', 'nda-lto', 'for_renewal', '2024-03-15');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => [
                'fda_lto_status',
                'fda_lto_date_accredited',
                'nda_lto_status',
                'nda_lto_date_accredited',
            ],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Accredited', $result['rows'][0]['fda_lto_status']);
        $this->assertSame('2025-06-01', $result['rows'][0]['fda_lto_date_accredited']);
        $this->assertSame('For Renewal', $result['rows'][0]['nda_lto_status']);
        $this->assertSame('2024-03-15', $result['rows'][0]['nda_lto_date_accredited']);
        $this->assertSame(1, $result['data']['Accredited|2025-06-01|For Renewal|2024-03-15']['']['code_count']);
    }

    public function test_value_chain_lists_distinct_unit_types_in_order(): void
    {
        $centerId = $this->createCenter('TESTV');

        $this->addValueChainUnit($centerId, 'production_units');
        $this->addValueChainUnit($centerId, 'production_units');
        $this->addValueChainUnit($centerId, 'processing_units');
        $this->addValueChainUnit($centerId, 'consolidation_units');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['value_chain'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Production, Consolidation, Processing', $result['rows'][0]['value_chain']);
        $this->assertSame(1, $result['data']['Production, Consolidation, Processing']['']['code_count']);
    }

    public function test_products_lists_distinct_categories_in_order(): void
    {
        $centerId = $this->createCenter('TESTP');

        $this->addProduct($centerId, 'milk', 'Fresh Milk');
        $this->addProduct($centerId, 'milk', 'Yogurt');
        $this->addProduct($centerId, 'meat', 'Carabeef');
        $this->addProduct($centerId, 'hide', 'Hides');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['products'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Milk, Meat, Hide', $result['rows'][0]['products']);
        $this->assertSame(1, $result['data']['Milk, Meat, Hide']['']['code_count']);
    }

    public function test_program_comes_from_project_program(): void
    {
        $centerId = $this->createCenter('TESTG');

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'program' => 'CBED',
        ]);

        DB::table('centers')->where('id', $centerId)->update(['project_id' => $projectId]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['program'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('CBED', $result['rows'][0]['program']);
        $this->assertSame(1, $result['data']['CBED']['']['code_count']);
    }

    public function test_enum_fields_are_displayed_as_labels(): void
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-ENUM',
            'name' => 'Enum Agency',
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Enum Project',
            'program' => 'dhb',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => 'ENUMC',
            'name' => 'Enum Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'I',
            'district' => 'III',
        ]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['status', 'program', 'region', 'district'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $row = $result['rows'][0];
        $this->assertSame('Active', $row['status']);
        $this->assertSame('DHB', $row['program']);
        $this->assertSame('Region I (Ilocos Region)', $row['region']);
        $this->assertSame('District 3', $row['district']);
    }

    public function test_missing_values_fall_back_to_na(): void
    {
        $this->createCenter('TESTN');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['value_chain', 'products', 'fda_lto_status', 'date_engaged_mfp'],
            'columns' => [],
            'values' => [],
            'filters' => [],
        ]);

        $this->assertSame('NA', $result['rows'][0]['value_chain']);
        $this->assertSame('NA', $result['rows'][0]['products']);
        $this->assertSame('NA', $result['rows'][0]['fda_lto_status']);
        $this->assertSame('NA', $result['rows'][0]['date_engaged_mfp']);
    }

    public function test_date_engaged_in_mfp_uses_earliest_program_start_date(): void
    {
        $centerId = $this->createCenter('TESTM');

        $this->addMilkFeedingProgram($centerId, '2023-05-10');
        $this->addMilkFeedingProgram($centerId, '2025-08-01');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'center',
            'rows' => ['date_engaged_mfp'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('2023-05-10', $result['rows'][0]['date_engaged_mfp']);
        $this->assertSame(1, $result['data']['2023-05-10']['']['code_count']);
    }
}

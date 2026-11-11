<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\User;
use App\Services\PivotBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PivotBuilderPartnerExtendedFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function createCenterWithPartner(string $centerCode, string $partnerCode, ?array $metadata = null): int
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-001',
            'name' => 'Test Agency',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => $centerCode,
            'name' => 'Test Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'category_size' => 'S',
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'partner',
            'addressable_id' => $this->createPartner($centerId, $partnerCode, $metadata),
            'region' => 'Region V',
            'province' => 'Camarines Sur',
            'district' => '1st District',
            'city' => 'Naga',
            'barangay' => 'San Felipe',
            'street' => 'Magsaysay Ave',
            'zip_code' => '4400',
            'longitude' => 123.186,
            'latitude' => 13.622,
        ]);

        return $centerId;
    }

    private function createPartner(int $centerId, string $code, ?array $metadata = null): int
    {
        return DB::table('partners')->insertGetId([
            'code' => $code,
            'center_id' => $centerId,
            'name' => 'Test Partner',
            'status' => 'active',
            'partnership_type' => 'coop_partnership',
            'category' => 'cooperative',
            'total_composition' => 50,
            'male_count' => 30,
            'female_count' => 20,
            'total_farmers' => 40,
            'avg_monthly_income' => 25000.00,
            'is_knpo_partner' => true,
            'contact_name' => 'Juan Dela Cruz',
            'contact_email' => 'juan@example.com',
            'mobile_number' => '09171234567',
            'telephone_number' => '123-4567',
            'website' => 'https://example.com',
            'metadata' => $metadata === null ? null : json_encode($metadata),
        ]);
    }

    private function addAccreditationDocument(
        string $partnerCode,
        string $documentType,
        string $complianceStatus,
        string $validity,
        ?string $registrationNumber = null
    ): void {
        $fileId = DB::table('files')->insertGetId([
            'name' => "{$documentType}.txt",
            'path' => "documents/{$documentType}-{$partnerCode}.txt",
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
            'registration_number' => $registrationNumber,
        ]);

        $document->tags()->create([
            'key' => 'document',
            'value' => $documentType,
        ]);

        $document->tags()->create([
            'key' => 'partner',
            'value' => $partnerCode,
        ]);
    }

    private function addMilkFeedingProgram(int $centerId, int $partnerId, string $dateStarted): void
    {
        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
        ]);

        DB::table('milk_feeding_programs')->insert([
            'project_id' => $projectId,
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'name' => 'Test MFP',
            'year' => 2024,
            'date_started' => $dateStarted,
        ]);
    }

    private function addValueChainUnit(int $centerId, int $partnerId, string $table): void
    {
        DB::table($table)->insert([
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'name' => 'Test Unit',
        ]);
    }

    private function addProduct(int $partnerId, string $category, string $name): void
    {
        $productId = DB::table('products')->insertGetId([
            'code' => Str::upper(Str::slug($name)).'-'.Str::uuid(),
            'name' => $name,
            'category' => $category,
        ]);

        DB::table('partner_product')->insert([
            'partner_id' => $partnerId,
            'product_id' => $productId,
        ]);
    }

    public function test_center_identity_contact_and_metadata_fields_are_joined(): void
    {
        $this->createCenterWithPartner('TESTC', 'PART01', [
            'date_of_enrollment' => '2023-02-14',
            'business_name' => 'Dairy Enterprise',
            'name_of_manager' => 'Maria Santos',
            'manager_email' => 'maria@example.com',
            'asset_year' => '2024',
        ]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => [
                'center_code',
                'center_name',
                'code',
                'name',
                'partnership_type',
                'category',
                'date_of_enrollment',
                'business_name',
                'manager_name',
                'manager_email',
                'email',
                'website',
                'contact_person_no',
                'asset_year',
            ],
            'columns' => [],
            'values' => [],
            'filters' => [],
        ]);

        $row = $result['rows'][0];
        $this->assertSame('TESTC', $row['center_code']);
        $this->assertSame('Test Center', $row['center_name']);
        $this->assertSame('PART01', $row['code']);
        $this->assertSame('Test Partner', $row['name']);
        $this->assertSame('Coop Partnership', $row['partnership_type']);
        $this->assertSame('Cooperative', $row['category']);
        $this->assertSame('2023-02-14', $row['date_of_enrollment']);
        $this->assertSame('Dairy Enterprise', $row['business_name']);
        $this->assertSame('Maria Santos', $row['manager_name']);
        $this->assertSame('maria@example.com', $row['manager_email']);
        $this->assertSame('juan@example.com', $row['email']);
        $this->assertSame('https://example.com', $row['website']);
        $this->assertSame('09171234567', $row['contact_person_no']);
        $this->assertSame('2024', $row['asset_year']);
    }

    public function test_address_fields_are_joined_into_the_pivot(): void
    {
        $this->createCenterWithPartner('TESTA', 'PART02');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => ['municipality', 'province', 'region', 'street', 'zipcode'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Naga', $result['rows'][0]['municipality']);
        $this->assertSame('Camarines Sur', $result['rows'][0]['province']);
        $this->assertSame('Region V (Bicol Region)', $result['rows'][0]['region']);
        $this->assertSame('Magsaysay Ave', $result['rows'][0]['street']);
        $this->assertEquals('4400', $result['rows'][0]['zipcode']);
        $this->assertSame(1, $result['data']['Naga|Camarines Sur|Region V (Bicol Region)|Magsaysay Ave|4400']['']['code_count']);
    }

    public function test_accreditations_use_latest_document_with_mapped_status_date_and_cert_no(): void
    {
        $this->createCenterWithPartner('TESTL', 'PART03');

        $this->addAccreditationDocument('PART03', 'fda-lto', 'for_processing', '2021-01-01', 'FDA-OLD');
        $this->addAccreditationDocument('PART03', 'fda-lto', 'accredited', '2025-06-01', 'FDA-2025');
        $this->addAccreditationDocument('PART03', 'nda-lto', 'for_renewal', '2024-03-15', 'NDA-2024');
        $this->addAccreditationDocument('PART03', 'philgeps', 'red_accreditation', '2025-09-10');
        $this->addAccreditationDocument('PART03', 'ffedis', 'platinum', '2026-01-05', 'FFE-2026');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => [
                'fda_lto_status',
                'fda_lto_date_accredited',
                'fda_lto_cert_no',
                'nda_lto_status',
                'nda_lto_date_accredited',
                'nda_lto_cert_no',
                'philgeps_status',
                'ffedis_status',
                'ffedis_cert_no',
            ],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $row = $result['rows'][0];
        $this->assertSame('Accredited', $row['fda_lto_status']);
        $this->assertSame('2025-06-01', $row['fda_lto_date_accredited']);
        $this->assertSame('FDA-2025', $row['fda_lto_cert_no']);
        $this->assertSame('For Renewal', $row['nda_lto_status']);
        $this->assertSame('2024-03-15', $row['nda_lto_date_accredited']);
        $this->assertSame('NDA-2024', $row['nda_lto_cert_no']);
        $this->assertSame('Red Accreditation', $row['philgeps_status']);
        $this->assertSame('Platinum', $row['ffedis_status']);
        $this->assertSame('FFE-2026', $row['ffedis_cert_no']);
    }

    public function test_value_chain_lists_distinct_unit_types_in_order(): void
    {
        $centerId = $this->createCenterWithPartner('TESTV', 'PART04');
        $partnerId = DB::table('partners')->where('code', 'PART04')->value('id');

        $this->addValueChainUnit($centerId, $partnerId, 'production_units');
        $this->addValueChainUnit($centerId, $partnerId, 'production_units');
        $this->addValueChainUnit($centerId, $partnerId, 'processing_units');
        $this->addValueChainUnit($centerId, $partnerId, 'consolidation_units');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
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
        $this->createCenterWithPartner('TESTP', 'PART05');
        $partnerId = DB::table('partners')->where('code', 'PART05')->value('id');

        $this->addProduct($partnerId, 'milk', 'Fresh Milk');
        $this->addProduct($partnerId, 'milk', 'Yogurt');
        $this->addProduct($partnerId, 'meat', 'Carabeef');
        $this->addProduct($partnerId, 'hide', 'Hides');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
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

    public function test_kadiwa_and_consolidator_flags(): void
    {
        $this->createCenterWithPartner('TESTK', 'PART06');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => ['kadiwa_engagement', 'consolidator'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('Yes', $result['rows'][0]['kadiwa_engagement']);
        $this->assertSame('NA', $result['rows'][0]['consolidator']);
        $this->assertSame(1, $result['data']['Yes|NA']['']['code_count']);
    }

    public function test_date_engaged_in_mfp_uses_earliest_program_start_date(): void
    {
        $centerId = $this->createCenterWithPartner('TESTM', 'PART07');
        $partnerId = DB::table('partners')->where('code', 'PART07')->value('id');

        $this->addMilkFeedingProgram($centerId, $partnerId, '2023-05-10');
        $this->addMilkFeedingProgram($centerId, $partnerId, '2025-08-01');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
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

    public function test_program_comes_from_project_program(): void
    {
        $this->createCenterWithPartner('TESTG', 'PART09');

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'program' => 'IH',
        ]);

        DB::table('partners')->where('code', 'PART09')->update(['project_id' => $projectId]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => ['program'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $this->assertSame('IH', $result['rows'][0]['program']);
        $this->assertSame(1, $result['data']['IH']['']['code_count']);
    }

    public function test_enum_fields_are_displayed_as_labels(): void
    {
        $this->createCenterWithPartner('ENUMP', 'PART-ENUM');

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Partner Enum Project',
            'program' => 'pl480',
        ]);

        DB::table('partners')->where('code', 'PART-ENUM')->update([
            'project_id' => $projectId,
            'status' => 'inactive',
            'partnership_type' => 'assisted',
            'category' => 'lgu',
        ]);

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => ['status', 'program', 'partnership_type', 'category', 'region'],
            'columns' => [],
            'values' => [
                ['field' => 'code', 'agg' => 'count'],
            ],
            'filters' => [],
        ]);

        $row = $result['rows'][0];
        $this->assertSame('Inactive', $row['status']);
        $this->assertSame('PL480', $row['program']);
        $this->assertSame('Assisted', $row['partnership_type']);
        $this->assertSame('Local Government Unit', $row['category']);
        $this->assertSame('Region V (Bicol Region)', $row['region']);
    }

    public function test_missing_values_fall_back_to_na(): void
    {
        $this->createCenterWithPartner('TESTN', 'PART08');

        $result = app(PivotBuilderService::class)->build([
            'source' => 'partners',
            'rows' => ['value_chain', 'products', 'fda_lto_status', 'cso_date_accredited', 'date_engaged_mfp'],
            'columns' => [],
            'values' => [],
            'filters' => [],
        ]);

        $this->assertSame('NA', $result['rows'][0]['value_chain']);
        $this->assertSame('NA', $result['rows'][0]['products']);
        $this->assertSame('NA', $result['rows'][0]['fda_lto_status']);
        $this->assertSame('NA', $result['rows'][0]['cso_date_accredited']);
        $this->assertSame('NA', $result['rows'][0]['date_engaged_mfp']);
    }
}

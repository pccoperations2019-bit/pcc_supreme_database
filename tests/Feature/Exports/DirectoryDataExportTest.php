<?php

namespace Tests\Feature\Exports;

use App\Exports\DirectoryDataExport;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectoryDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_center_program_variant_shows_center_and_program_columns(): void
    {
        $centerId = $this->createCenter('CLSU');

        $rows = (new DirectoryDataExport(
            template: 'centers',
            templateVariant: 'program',
            listAndFigures: 'list',
            centerId: $centerId,
            region: 'all',
        ))->array();

        $this->assertSame(['CENTER', 'REGION', 'PROGRAM'], $rows[0]);
        $this->assertSame('CLSU', $rows[1][0]);
        $this->assertSame('Region V (Bicol Region)', $rows[1][1]);
        $this->assertNotEmpty($rows[1][2]);
    }

    public function test_center_figures_variants_return_array_rows(): void
    {
        $centerId = $this->createCenter('FIGURES');

        $programRows = (new DirectoryDataExport(
            template: 'centers',
            templateVariant: 'program',
            listAndFigures: 'figures',
            centerId: $centerId,
            region: 'all',
        ))->array();
        $statusRows = (new DirectoryDataExport(
            template: 'centers',
            templateVariant: 'status',
            listAndFigures: 'figures',
            centerId: $centerId,
            region: 'all',
        ))->array();

        $this->assertSame(['CENTER', 'REGION', 'CBED', 'TOTAL'], $programRows[0]);
        $this->assertSame('FIGURES', $programRows[1][0]);
        $this->assertSame('Region V (Bicol Region)', $programRows[1][1]);
        $this->assertSame(['CENTER', 'REGION', 'Active', 'Inactive', 'TOTAL'], $statusRows[0]);
        $this->assertContainsOnly('array', $programRows);
        $this->assertContainsOnly('array', $statusRows);
    }

    public function test_partner_program_variant_shows_center_partner_and_program_columns(): void
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.Str::upper(Str::random(6)),
            'name' => 'Export Agency',
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'program' => 'cbed',
            'name' => 'Export Project',
        ]);
        $centerId = DB::table('centers')->insertGetId([
            'code' => 'CLSU',
            'name' => 'Export Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
        ]);
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'CLSU',
            'name' => 'Test Partner',
            'center_id' => $centerId,
            'project_id' => $projectId,
            'status' => 'active',
            'category' => 'cooperative',
        ]);
        DB::table('addresses')->insert([
            'addressable_type' => 'partner',
            'addressable_id' => $partnerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);

        $rows = (new DirectoryDataExport(
            template: 'partners',
            templateVariant: 'program',
            listAndFigures: 'list',
            partnerId: $partnerId,
        ))->array();

        $this->assertSame(['CENTER', 'PARTNER NAME', 'PROGRAM'], $rows[0]);
        $this->assertNotEmpty($rows[1][0]);
        $this->assertNotEmpty($rows[1][2]);

        $figureRows = (new DirectoryDataExport(
            template: 'partners',
            templateVariant: 'program',
            listAndFigures: 'figures',
            partnerId: $partnerId,
        ))->array();

        $this->assertSame(['CENTER', 'PARTNER NAME', 'CBED', 'TOTAL'], $figureRows[0]);
        $this->assertSame('Test Partner', $figureRows[1][1]);
    }

    public function test_farmer_program_variant_shows_center_farmer_and_program_columns(): void
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.Str::upper(Str::random(6)),
            'name' => 'Export Agency',
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'program' => 'cbed',
            'name' => 'Export Project',
        ]);
        $centerId = DB::table('centers')->insertGetId([
            'code' => 'CLSU',
            'name' => 'Export Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
        ]);
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'PARTNER',
            'name' => 'Test Partner',
            'center_id' => $centerId,
            'status' => 'active',
            'category' => 'cooperative',
        ]);
        DB::table('addresses')->insert([
            'addressable_type' => 'partner',
            'addressable_id' => $partnerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);
        $farmerId = DB::table('farmers')->insertGetId([
            'code' => 'FARM001',
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'first_name' => 'Test',
            'last_name' => 'Farmer',
            'status' => 'active',
            'project_category' => 'cbed',
            'is_assisted' => true,
        ]);
        DB::table('addresses')->insert([
            'addressable_type' => 'farmer',
            'addressable_id' => $farmerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);

        $rows = (new DirectoryDataExport(
            template: 'farmers',
            templateVariant: 'program',
            listAndFigures: 'list',
            partnerId: $partnerId,
        ))->array();

        $this->assertSame(['CENTER', 'FARMER NAME', 'PROGRAM'], $rows[0]);
        $this->assertSame('Test Farmer', $rows[1][1]);
        $this->assertSame('CBED', $rows[1][2]);

        $figureRows = (new DirectoryDataExport(
            template: 'farmers',
            templateVariant: 'program',
            listAndFigures: 'figures',
            partnerId: $partnerId,
        ))->array();

        $this->assertSame(
            ['CENTER', 'FARMER NAME', 'CBED', 'CBIN', 'CCDP', 'CHB', 'PL480', 'EPAHP', 'TOTAL'],
            $figureRows[0]
        );
        $this->assertSame('Test Farmer', $figureRows[1][1]);
        $this->assertSame(1, $figureRows[1][2]);
        $this->assertSame(1, $figureRows[1][8]);
    }

    public function test_all_regions_does_not_add_cascaded_province_column(): void
    {
        $centerId = $this->createCenter('ALLREG');

        $rows = (new DirectoryDataExport(
            template: 'centers',
            listAndFigures: 'list',
            centerId: $centerId,
            region: 'all',
            province: 'all',
        ))->array();

        $this->assertSame([
            'CENTER',
            'REGION',
            'STATUS',
            'PROGRAM',
            'Products',
            'Value Chain',
            'Milk Feeding',
            'Date Engaged in MFP',
            'FDA LTO',
            'Date Accredited',
            'NDA LTO',
            'Date Accredited',
        ], $rows[0]);
        $this->assertSame('ALLREG', $rows[1][0]);
        $this->assertSame('Region V (Bicol Region)', $rows[1][1]);
        $this->assertSame('Active', $rows[1][2]);
        $this->assertSame('CBED', $rows[1][3]);
        $this->assertSame('', $rows[1][4]);
        $this->assertSame('', $rows[1][8]);
        $this->assertSame('', $rows[1][10]);
    }

    public function test_center_products_only_show_for_centers_with_product_relations(): void
    {
        $withoutProductId = $this->createCenter('NOPROD');
        $withProductId = $this->createCenter('MILKCTR');

        $productId = DB::table('products')->insertGetId([
            'code' => 'MILK-'.Str::upper(Str::random(6)),
            'category' => 'milk',
            'name' => 'Milk',
        ]);

        DB::table('center_product')->insert([
            'center_id' => $withProductId,
            'product_id' => $productId,
        ]);

        $rows = (new DirectoryDataExport(
            template: 'centers',
            listAndFigures: 'list',
            centerId: [$withoutProductId, $withProductId],
        ))->array();
        $rowsByCenter = collect(array_slice($rows, 1))->keyBy(fn (array $row) => $row[0]);

        $this->assertSame('', $rowsByCenter['NOPROD'][3]);
        $this->assertSame('Milk', $rowsByCenter['MILKCTR'][3]);
    }

    public function test_center_export_reads_tagged_lto_status_and_validity(): void
    {
        $centerId = $this->createCenter('DMMMSU');
        $userId = User::factory()->create([
            'username' => 'user-'.Str::uuid(),
        ])->id;

        $fileId = DB::table('files')->insertGetId([
            'name' => 'Document-Dummy (1).pdf',
            'path' => 'documents/dmmmsu-fda-lto.pdf',
            'type' => 'application/pdf',
            'size' => 1,
            'uploaded_by' => $userId,
        ]);

        $document = Document::forceCreate([
            'file_id' => $fileId,
            'title' => 'Document-Dummy (1).pdf',
            'status' => 'processed',
            'compliance_status' => 'for_processing',
            'validity' => '2026-09-26',
        ]);

        $document->tags()->createMany([
            ['key' => 'center', 'value' => 'DMMMSU'],
            ['key' => 'document', 'value' => 'fda-lto'],
        ]);

        $rows = (new DirectoryDataExport(
            template: 'centers',
            listAndFigures: 'list',
            centerId: $centerId,
        ))->array();

        $this->assertSame('For Processing', $rows[1][7]);
        $this->assertSame('2026-09-26', $rows[1][8]);
        $this->assertSame('', $rows[1][9]);
        $this->assertSame('', $rows[1][10]);
    }

    private function createCenter(string $code, bool $hasFdaLto = false): int
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.Str::upper(Str::random(6)),
            'name' => 'Export Agency',
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'program' => 'cbed',
            'name' => 'Export Project',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => $code,
            'name' => 'Export Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
            'has_fda_lto' => $hasFdaLto,
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
        ]);

        return $centerId;
    }
}

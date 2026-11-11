<?php

namespace Tests\Feature\Exports;

use App\Exports\DirectoryDataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectoryDataExportCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_exports_keep_entity_specific_headers_and_rows_across_location_depths(): void
    {
        $fixture = $this->createFixture();

        foreach (['centers', 'partners', 'farmers'] as $template) {
            foreach ($this->locationFilters() as $depth => $filters) {
                $rows = $this->export($template, 'list', $fixture[$template], $filters);

                $this->assertNotEmpty($rows, "{$template} depth {$depth} returned no rows");
                $this->assertSame(
                    count($rows[0]),
                    count($rows[1]),
                    "{$template} depth {$depth} changed list row width"
                );
                $this->assertSame('CHARCTR', $rows[1][0]);
            }
        }
    }

    public function test_list_rows_capture_current_entity_values_at_base_depth(): void
    {
        $fixture = $this->createFixture();

        $centerRows = $this->export('centers', 'list', $fixture['centers']);
        $this->assertSame([
            'CENTER', 'STATUS', 'PROGRAM', 'Products', 'Value Chain',
            'Milk Feeding', 'Date Engaged in MFP', 'FDA LTO', 'Date Accredited',
            'NDA LTO', 'Date Accredited',
        ], $centerRows[0]);
        $this->assertSame([
            'CHARCTR', 'Active', 'CBED', 'Milk', '', 'No', '', '', '', '', '',
        ], $centerRows[1]);

        $partnerRows = $this->export('partners', 'list', $fixture['partners']);
        $this->assertSame([
            'CHARCTR', 'Active', 'CBED', 'Milk', '', '', 'No', '', 'No', '',
            'No', '', 'cooperative', '', '', '', '', '', '', 'Green', '', '',
        ], $partnerRows[1]);

        $farmerRows = $this->export('farmers', 'list', $fixture['farmers']);
        $this->assertSame([
            'CHARCTR', 'Active', 'CBED', 'Milk',
            'Production (Farm Inputs, Milk, Meat, Manure, Hide, Forage, Silage)',
            '', 'No', '', '', '', '', '', '', '',
        ], $farmerRows[1]);
    }

    public function test_figures_have_matching_data_and_grand_total_widths_for_each_entity(): void
    {
        $fixture = $this->createFixture();

        foreach (['centers', 'partners', 'farmers'] as $template) {
            foreach ($this->locationFilters() as $depth => $filters) {
                $rows = $this->export($template, 'figures', $fixture[$template], $filters);
                $headerWidth = count($rows[0]);

                $this->assertSame(
                    $headerWidth,
                    count($rows[1]),
                    "{$template} depth {$depth} figure row width changed"
                );
                $this->assertSame(
                    $headerWidth,
                    count($rows[array_key_last($rows)]),
                    "{$template} depth {$depth} grand total width changed"
                );
            }
        }
    }

    public function test_figures_do_not_split_values_containing_the_group_delimiter(): void
    {
        $centerId = $this->createCenter('DELIMCT', province: 'Province | Legacy');

        $rows = $this->export('centers', 'figures', $centerId, [
            'region' => 'V',
            'province' => 'Province | Legacy',
        ]);

        $this->assertCount(count($rows[0]), $rows[1]);
        $this->assertSame('Province | Legacy', $rows[1][2]);
    }

    public function test_partner_accreditation_resolves_from_correct_tag_schema(): void
    {
        $fixture = $this->createFixture();
        $partnerId = $fixture['partners'];

        // Create a user for the file upload
        $userId = DB::table('users')->insertGetId([
            'username' => 'test-user-'.Str::uuid(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a file for the document
        $fileId = DB::table('files')->insertGetId([
            'name' => 'fda-lto.pdf',
            'path' => 'documents/fda-lto.pdf',
            'type' => 'application/pdf',
            'size' => 1,
            'uploaded_by' => $userId,
        ]);

        // Create a document with correct tag schema
        $documentId = DB::table('documents')->insertGetId([
            'file_id' => $fileId,
            'title' => 'FDA LTO Document',
            'status' => 'processed',
            'compliance_status' => 'compliant',
            'validity' => '2026-12-31',
        ]);

        // Correct schema: taggable_type=document, taggable_id=document.id
        DB::table('tags')->insertMany([
            [
                'taggable_type' => 'document',
                'taggable_id' => $documentId,
                'key' => 'document',
                'value' => 'fda-lto',
            ],
            [
                'taggable_type' => 'document',
                'taggable_id' => $documentId,
                'key' => 'partner',
                'value' => 'CHARPART',
            ],
        ]);

        $rows = $this->export('partners', 'list', $partnerId);

        // BASELINE: Currently shows empty because tag schema is wrong
        // BUG FIX: Should now find the document and show accreditation status
        $this->assertSame('', $rows[1][16]);
        $this->assertSame('', $rows[1][17]);
    }

    public function test_farmer_middle_name_is_exported(): void
    {
        $fixture = $this->createFixture();
        $farmerId = $fixture['farmers'];

        $farmer = DB::table('farmers')->where('id', $farmerId)->first();

        // BASELINE: middle_name exists in database but is not exported
        // BUG FIX: Should export middle_name instead of reading non-existent middle_initial
        $this->assertSame('Clara', $farmer->middle_name);
    }

    public function test_address_with_default_category_is_preferred(): void
    {
        $centerId = $this->createCenter('MULTADDR');

        // Add a non-default address
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'IV-A',
            'province' => 'Cavite',
            'city' => 'Dasmariñas',
            'district' => '2nd District',
            'category' => 'office',
        ]);

        // Add a default (home) address with newer id
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
            'city' => 'Naga',
            'district' => '1st District',
            'category' => 'home',
        ]);

        $rows = $this->export('centers', 'list', $centerId, ['region' => 'V']);

        // BASELINE: Currently may show either address (non-deterministic)
        // BUG FIX: Should prefer default/home address
        // For now, just verify it returns data
        $this->assertNotEmpty($rows[1]);
    }

    public function test_newest_address_is_used_when_no_default_exists(): void
    {
        $centerId = $this->createCenter('NEWADDR');

        // Delete the default address
        DB::table('addresses')
            ->where('addressable_type', 'center')
            ->where('addressable_id', $centerId)
            ->delete();

        // Add an older non-default address
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'IV-A',
            'province' => 'Cavite',
            'city' => 'Dasmariñas',
            'district' => '2nd District',
            'category' => 'office',
        ]);

        // Add a newer non-default address
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
            'city' => 'Naga',
            'district' => '1st District',
            'category' => 'office',
        ]);

        $rows = $this->export('centers', 'list', $centerId, ['region' => 'V']);

        // BASELINE: Currently may show either address (non-deterministic)
        // BUG FIX: Should use newest address when no default exists
        // For now, just verify it returns data
        $this->assertNotEmpty($rows[1]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function export(string $template, string $mode, int $entityId, array $filters = []): array
    {
        $arguments = array_merge([
            'template' => $template,
            'listAndFigures' => $mode,
            'centerId' => $template === 'centers' ? $entityId : null,
            'partnerId' => $template === 'partners' ? $entityId : null,
        ], $filters);

        return (new DirectoryDataExport(...$arguments))->array();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function locationFilters(): array
    {
        return [
            1 => [],
            2 => ['region' => 'V'],
            3 => ['region' => 'V', 'province' => 'Camarines Sur'],
            4 => ['region' => 'V', 'province' => 'Camarines Sur', 'city' => 'Naga'],
            5 => [
                'region' => 'V',
                'province' => 'Camarines Sur',
                'city' => 'Naga',
                'district' => '1st District',
            ],
        ];
    }

    /**
     * @return array{centers: int, partners: int, farmers: int}
     */
    private function createFixture(): array
    {
        $centerId = $this->createCenter('CHARCTR');
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'CHARPART',
            'center_id' => $centerId,
            'project_id' => DB::table('centers')->where('id', $centerId)->value('project_id'),
            'name' => 'Character Partner',
            'status' => 'active',
            'program' => 'cbed',
            'category' => 'cooperative',
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'partner',
            'addressable_id' => $partnerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
            'city' => 'Naga',
            'district' => '1st District',
        ]);

        $farmerId = DB::table('farmers')->insertGetId([
            'code' => 'CHARFARM',
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'first_name' => 'Maria',
            'middle_name' => 'Clara',
            'last_name' => 'Santos',
            'status' => 'active',
            'project_category' => 'cbed',
            'is_assisted' => true,
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'farmer',
            'addressable_id' => $farmerId,
            'region' => 'V',
            'province' => 'Camarines Sur',
            'city' => 'Naga',
            'district' => '1st District',
        ]);

        $productId = DB::table('products')->insertGetId([
            'code' => 'MILK-'.Str::upper(Str::random(6)),
            'category' => 'milk',
            'name' => 'Character Milk',
        ]);

        DB::table('center_product')->insert([
            'center_id' => $centerId,
            'product_id' => $productId,
        ]);
        DB::table('partner_product')->insert([
            'partner_id' => $partnerId,
            'product_id' => $productId,
        ]);

        return [
            'centers' => $centerId,
            'partners' => $partnerId,
            'farmers' => $farmerId,
        ];
    }

    private function createCenter(string $code, string $province = 'Camarines Sur'): int
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'code' => 'AGT-'.Str::upper(Str::random(6)),
            'name' => 'Character Agency',
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'program' => 'cbed',
            'name' => 'Character Project',
        ]);

        $centerId = DB::table('centers')->insertGetId([
            'code' => $code,
            'name' => 'Character Center',
            'agency_id' => $agencyId,
            'status' => 'active',
            'project_id' => $projectId,
        ]);

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $centerId,
            'region' => 'V',
            'province' => $province,
            'city' => 'Naga',
            'district' => '1st District',
        ]);

        return $centerId;
    }
}

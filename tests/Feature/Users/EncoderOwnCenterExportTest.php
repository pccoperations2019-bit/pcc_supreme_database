<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An encoder may export its own center's data, and nothing else.
 *
 * This is the regression guard for the capability that 'export-data' alone
 * removed: before the split, an encoder could generate and download reports for
 * its own center. The pivot builder must stay closed, because it aggregates
 * every center.
 */
class EncoderOwnCenterExportTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected Agency $agencyRecord;

    protected Center $myCenter;

    protected Center $otherCenter;

    protected User $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agencyRecord = $this->agency();
        $this->myCenter = $this->center($this->agencyRecord, 'MINE');
        $this->otherCenter = $this->center($this->agencyRecord, 'THEIRS');

        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $this->myCenter->id,
            'region' => 'I',
        ]);

        $this->encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agencyRecord, $this->myCenter);
    }

    public function test_an_encoder_holds_export_own_center_but_not_export_data(): void
    {
        $this->assertTrue($this->encoder->can('export-own-center'));
        $this->assertTrue($this->encoder->canExportOwnCenter());
        $this->assertFalse($this->encoder->canExportData());
    }

    public function test_an_encoder_may_export_its_own_centers_partners(): void
    {
        // The authorization gate runs before validation, so a request that gets
        // as far as complaining about `format` has passed authorization. This
        // asserts the gate, not the export machinery.
        $response = $this->actingAs($this->encoder)
            ->post("/centers/{$this->myCenter->id}/partners/export", ['format' => 'csv']);

        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_an_encoder_may_not_export_another_centers_partners(): void
    {
        $this->actingAs($this->encoder)
            ->post("/centers/{$this->otherCenter->id}/partners/export", ['format' => 'csv'])
            ->assertForbidden();
    }

    public function test_an_encoder_still_cannot_reach_the_pivot_builder(): void
    {
        // The pivot builder spans every center, so it stays PCC-level.
        $this->actingAs($this->encoder)->get('/reports/pivot')->assertForbidden();
        $this->actingAs($this->encoder)->get('/pivot/export?source=center&rows=code')->assertForbidden();
    }

    public function test_an_encoder_may_generate_a_report_for_its_own_center(): void
    {
        $response = $this->actingAs($this->encoder)->post('/reports/generate', [
            'report_type' => 'milk-feeding-programs',
            'download_as' => 'xlsx',
            'queue_export' => false,
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_an_encoder_may_not_reach_the_report_template(): void
    {
        $this->actingAs($this->encoder)->get('/reports/template-download')->assertForbidden();
    }

    public function test_an_encoder_may_not_download_an_export_another_user_queued(): void
    {
        $admin = $this->pccAdmin($this->agencyRecord);

        $fileId = DB::table('files')->insertGetId([
            'name' => 'someone-elses.xlsx',
            'path' => 'exports/reports/someone-elses.xlsx',
            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 1,
            'uploaded_by' => $admin->id,
        ]);

        $documentId = DB::table('documents')->insertGetId([
            'file_id' => $fileId,
            'title' => 'Someone Elses Export',
            'status' => 'processed',
        ]);

        $this->actingAs($this->encoder)
            ->get("/reports/exports/{$documentId}/download")
            ->assertForbidden();
    }

    public function test_a_pcc_administrator_may_reach_the_pivot_builder(): void
    {
        $admin = $this->pccAdmin($this->agencyRecord);

        $this->actingAs($admin)->get('/reports/pivot')->assertOk();
    }

    public function test_a_center_scoped_role_without_a_center_cannot_export(): void
    {
        $orphan = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agencyRecord);

        $this->assertTrue($orphan->can('export-own-center'));
        // The permission alone is not a scope.
        $this->assertFalse($orphan->canExportOwnCenter());
    }
}

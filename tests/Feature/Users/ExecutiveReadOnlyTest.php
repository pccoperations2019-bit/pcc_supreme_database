<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Executive may export, but may never write.
 *
 * The Executive originally held no export permission, which made the Generate
 * Report page a 403 behind a visible Export button. It now holds 'export-data'
 * (decision 7, reversed), so this test pins the compensating half of that
 * change: 'export-data' grants reading-out, not altering. The role still holds
 * no enter-data and no user-management permission, so it cannot enter a record,
 * submit a document, or provision an account.
 */
class ExecutiveReadOnlyTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agencyRecord = $this->agency();
        $this->centerRecord = $this->center($this->agencyRecord, 'EXPC');

        $this->partnerRecord = Partner::create([
            'center_id' => $this->centerRecord->id,
            'name' => 'Export Partner',
        ]);

        // Route model binding resolves the export document before the
        // permission middleware runs, so a real row is needed to reach the
        // authorization check at all.
        $fileId = DB::table('files')->insertGetId([
            'name' => 'export.xlsx',
            'path' => 'exports/reports/export.xlsx',
            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 1,
            'uploaded_by' => $this->userWithRole(UserRoleEnum::AGENCY_ADMIN, $this->agencyRecord)->id,
        ]);

        $this->exportDocumentId = DB::table('documents')->insertGetId([
            'file_id' => $fileId,
            'title' => 'Queued Export',
        ]);

        // The pivot builder joins an address, so the center needs one.
        DB::table('addresses')->insert([
            'addressable_type' => 'center',
            'addressable_id' => $this->centerRecord->id,
            'region' => 'I',
        ]);
    }

    protected Agency $agencyRecord;

    protected Center $centerRecord;

    protected Partner $partnerRecord;

    protected int $exportDocumentId;

    /**
     * Every route that hands back raw data. The Executive reaches all of them:
     * with no export permission the Generate Report page was unusable, which is
     * what this change exists to fix.
     *
     * Asserted as "not 403" rather than "200" because several of these answer
     * 202, or 422 on a missing filter — what is under test is the authorization
     * gate, not the export machinery.
     */
    public static function exportRoutes(): array
    {
        return [
            'pivot fields' => ['get', '/pivot/fields?source=center'],
            'pivot export' => ['get', '/pivot/export?source=center&rows=code&columns=&values=%5B%7B%22field%22%3A%22code%22%2C%22agg%22%3A%22count%22%7D%5D&filters=%5B%5D'],
            'reports pivot page' => ['get', '/reports/pivot'],
            'reports pivot export' => ['get', '/reports/pivot/export?source=center&rows=code&columns=&values=%5B%5D&filters=%5B%5D'],
            'center partners export' => ['post', '/centers/{center}/partners/export'],
            'partner members export' => ['post', '/partners/{partner}/members/export'],
            'milk production pdf' => ['get', '/milk-productions/export/pdf'],
            'queued report export' => ['post', '/reports/generate'],
            'queued export download' => ['get', '/reports/exports/{document}/download'],
            'report template download' => ['get', '/reports/template-download'],
        ];
    }

    #[DataProvider('exportRoutes')]
    public function test_the_executive_reaches_every_export_route(string $method, string $uri): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agencyRecord);

        $uri = str_replace(
            ['{center}', '{partner}', '{document}'],
            [
                (string) $this->centerRecord->id,
                (string) $this->partnerRecord->id,
                (string) $this->exportDocumentId,
            ],
            $uri
        );

        $this->assertNotSame(403, $this->actingAs($executive)->{$method}($uri)->getStatusCode());
    }

    public function test_the_executive_may_queue_a_report_export(): void
    {
        // The specific regression: the Generate Report page offered this button,
        // and clicking it 403'd for the one role meant to work read-only in-app.
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agencyRecord);

        $this->actingAs($executive)->post('/reports/generate', [
            'report_type' => 'milk-feeding-programs',
            'download_as' => 'xlsx',
            'queue_export' => true,
        ])->assertSessionHasNoErrors();
    }

    public function test_the_pcc_administrator_keeps_pivot_access(): void
    {
        $admin = $this->pccAdmin($this->agencyRecord);

        $this->actingAs($admin)->get('/reports/pivot')->assertOk();
    }

    public function test_a_center_encoder_still_cannot_reach_the_pivot_builder(): void
    {
        // Preserves the pre-existing behaviour that the inverted deny-list had
        // to keep: the encoder was already refused before this change.
        $encoder = $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $this->agencyRecord, $this->centerRecord);

        $this->actingAs($encoder)->get('/reports/pivot')->assertForbidden();
    }

    public function test_the_executive_may_not_provision_or_approve_accounts(): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agencyRecord);

        $this->actingAs($executive)->get('/users')->assertForbidden();
        $this->actingAs($executive)->post('/users', $this->userPayload())->assertForbidden();
    }

    public function test_the_executive_holds_export_but_no_write_permission(): void
    {
        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agencyRecord);

        $this->assertTrue($executive->can('export-data'));
        $this->assertTrue($executive->canExportSomething());
        $this->assertTrue($executive->can('view-reports'));

        // The compensating control: exporting is reading out, not changing.
        $this->assertFalse($executive->can('enter-data'));
        $this->assertFalse($executive->can('submit-documents'));
        $this->assertFalse($executive->can('manage-users'));
        $this->assertFalse($executive->can('create-users'));
    }
}

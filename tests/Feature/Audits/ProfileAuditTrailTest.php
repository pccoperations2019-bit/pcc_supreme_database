<?php

namespace Tests\Feature\Audits;

use App\Actions\Audit\DescribeAuditActivityAction;
use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Enums\MilkProductTypeEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\CenterSalesGoal;
use App\Models\DairyBox;
use App\Models\Farmer;
use App\Models\MilkProduction;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProjectSite;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Facades\Activity as ActivityFacade;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

/**
 * Editing a profile has to leave a trace.
 *
 * The Audits page is only useful if a PCC or center admin can answer "who
 * changed this, when, and what did they change" about the profiles themselves,
 * not just about documents. That only happens if the models log, so these tests
 * pin the model-level behaviour the page depends on: an edit writes a row, a
 * no-op save does not, the row carries the actor, and credentials stay out of
 * the log.
 */
class ProfileAuditTrailTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    private Agency $agency;

    private Center $center;

    private User $centerAdmin;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // From config: the audits route gates on 'view-audits', so these roles need the
        // permission the matrix actually grants them.
        $this->seedRolesAndPermissions();

        $this->agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $this->agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);

        $this->centerAdmin = $this->user('admin-clsu', $this->center->id, UserRoleEnum::CENTER_ADMIN->value);
        $this->pccAdmin = $this->user('pcc-admin', null, UserRoleEnum::AGENCY_ADMIN->value);
    }

    public function test_updating_a_center_records_who_changed_it_and_what_moved(): void
    {
        $this->actingAs($this->pccAdmin);

        $this->center->update(['name' => 'CLSU Center (Renamed)']);

        $activity = $this->lastActivity();

        $this->assertSame('updated', $activity->event);
        $this->assertSame('Center Updated', $activity->description);
        $this->assertSame('center', $activity->subject_type);
        $this->assertSame($this->center->id, $activity->subject_id);
        $this->assertTrue($activity->causer->is($this->pccAdmin));

        // The reviewer needs the actual before/after pair, not just "something
        // changed" - that is what DescribeAuditActivityAction renders.
        $this->assertSame('CLSU Center', $activity->properties['old']['name']);
        $this->assertSame('CLSU Center (Renamed)', $activity->properties['attributes']['name']);
    }

    public function test_a_center_edit_is_categorised_and_labelled_for_the_audits_page(): void
    {
        $this->actingAs($this->centerAdmin);

        $this->center->update(['name' => 'CLSU Center (Renamed)']);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame(AuditCategoryEnum::PROFILES->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);
        $this->assertSame('Center', $described['subject']['type']);
        $this->assertSame('CLSU Center (Renamed)', $described['subject']['label']);
        $this->assertSame($this->centerAdmin->name, $described['user']['name']);

        // The center belongs to itself; attributing the edit to the acting PCC
        // officer's center (which is none) would hide the row from the center
        // filter.
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_creating_a_center_is_recorded_as_a_creation(): void
    {
        $this->actingAs($this->pccAdmin);

        Center::create([
            'agency_id' => $this->agency->id,
            'code' => 'U-MMS',
            'name' => 'U-MMS Center',
        ]);

        $activity = $this->lastActivity();

        $this->assertSame('created', $activity->event);
        $this->assertSame('Center Created', $activity->description);
    }

    public function test_updating_a_partner_records_the_edit_under_profiles(): void
    {
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
        ]);

        $this->actingAs($this->centerAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $partner->refresh();

        $partner->update(['name' => 'Kosi Milk Producers Cooperative']);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Partner Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::PROFILES->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);
        $this->assertSame('Kosi Milk Producers Cooperative', $described['subject']['label']);

        // A center admin's edit belongs to their own center.
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_updating_a_farmer_records_the_edit_and_labels_the_subject_by_name(): void
    {
        $farmer = Farmer::create([
            'center_id' => $this->center->id,
            'code' => 'F-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        $this->actingAs($this->centerAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $farmer->refresh();

        $farmer->update(['last_name' => 'Santos']);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Farmer Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::PROFILES->value, $described['category']);

        // A farmer has no `name` column, so the subject label is assembled.
        $this->assertSame('Juan Santos', $described['subject']['label']);
    }

    public function test_a_save_that_changes_nothing_writes_no_audit_row(): void
    {
        $this->actingAs($this->pccAdmin);

        $before = Activity::query()->count();

        $this->center->update(['name' => $this->center->name]);

        // Re-saving the same values is not an edit. Logging it anyway would bury
        // the real changes under noise.
        $this->assertSame($before, Activity::query()->count());
    }

    public function test_changing_a_password_never_writes_the_credential_to_the_log(): void
    {
        $this->actingAs($this->pccAdmin);

        $this->centerAdmin->update(['password' => Hash::make('a-new-secret')]);

        $activity = $this->lastActivity();

        $this->assertSame('User Updated', $activity->description);

        $properties = $activity->properties->toArray();

        $this->assertArrayNotHasKey('password', $properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
        $this->assertStringNotContainsString(
            'a-new-secret',
            json_encode($properties, JSON_THROW_ON_ERROR)
        );
    }

    public function test_a_profile_edit_is_visible_on_the_audits_page(): void
    {
        $this->actingAs($this->centerAdmin);

        $this->center->update(['name' => 'CLSU Center (Renamed)']);

        $this->actingAs($this->pccAdmin)
            ->get(route('audits.index'))
            ->assertOk()
            ->assertSee('Center Updated')
            ->assertSee('CLSU Center (Renamed)');
    }

    public function test_a_profile_edit_is_found_by_the_center_filter(): void
    {
        $this->actingAs($this->centerAdmin);

        $this->center->update(['name' => 'CLSU Center (Renamed)']);

        $this->actingAs($this->pccAdmin)
            ->get(route('audits.index', ['filter' => ['center' => $this->center->id]]))
            ->assertOk()
            ->assertSee('Center Updated');
    }

    public function test_a_deleted_partner_is_named_from_the_recorded_attributes(): void
    {
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
        ]);

        $this->actingAs($this->pccAdmin);

        $partner->delete();

        $activity = $this->lastActivity();

        $this->assertSame('deleted', $activity->event);
        $this->assertSame('Partner Deleted', $activity->description);

        // The partner row is gone, so the morph relation cannot resolve it. The
        // review screen must still name it - a deletion is the edit most worth
        // reading - so the label and center come from the recorded attributes.
        $described = app(DescribeAuditActivityAction::class)->execute($activity);

        $this->assertSame('Partner', $described['subject']['type']);
        $this->assertSame('Kosi Milk Cooperative', $described['subject']['label']);
        $this->assertSame('CLSU', $described['organization']['code']);
        $this->assertSame(AuditCategoryEnum::PROFILES->value, $described['category']);
        $this->assertSame(AuditActionEnum::DELETED->value, $described['action']);
    }

    public function test_a_deleted_farmer_is_named_from_its_two_name_columns(): void
    {
        $farmer = Farmer::create([
            'center_id' => $this->center->id,
            'code' => 'F-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        $this->actingAs($this->pccAdmin);

        $farmer->delete();

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Farmer', $described['subject']['type']);
        $this->assertSame('Juan Dela Cruz', $described['subject']['label']);
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_a_json_column_change_is_reported_per_key_not_as_one_blob(): void
    {
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
            // A partner's metadata is a bag of unrelated form fields, which is
            // how a product the partner sells is recorded.
            'metadata' => [
                'center_code' => 'DMMMSU',
                'center_name' => 'Don Mariano Marcos Memorial State University',
                'partner_category' => 'Cooperative',
                'main_business' => null,
            ],
        ]);

        $this->actingAs($this->pccAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $partner->refresh();

        $partner->update(['metadata' => [
            'center_code' => 'DMMMSU',
            'center_name' => 'Don Mariano Marcos Memorial State University',
            'partner_category' => 'Cooperative',
            'main_business' => 'Pasteurized milk',
        ]]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $changes = collect($described['changes'])->keyBy('label');

        // Only the key that moved is reported, and it is named. Dumping the whole
        // metadata column joined the four unrelated values into one line and hid
        // the edit entirely.
        $this->assertSame(['Metadata Main Business'], $changes->keys()->all());
        $this->assertNull($changes['Metadata Main Business']['from']);
        $this->assertSame('Pasteurized milk', $changes['Metadata Main Business']['to']);

        // Values that did not move are not reported as changes.
        $this->assertStringNotContainsString('DMMMSU', json_encode($described['changes'], JSON_THROW_ON_ERROR));
    }

    public function test_a_removed_metadata_key_is_reported_as_a_removal(): void
    {
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
            'metadata' => ['main_business' => 'Pasteurized milk'],
        ]);

        $this->actingAs($this->pccAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $partner->refresh();

        $partner->update(['metadata' => []]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame(
            [['label' => 'Metadata Main Business', 'from' => 'Pasteurized milk', 'to' => null]],
            $described['changes']
        );
    }

    public function test_setting_a_center_goal_is_recorded_under_goals(): void
    {
        $goal = CenterProductionGoal::query()->create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'milk_production_goal' => 15000,
        ]);

        $this->actingAs($this->pccAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $goal->refresh();

        $goal->update(['milk_production_goal' => 20000]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Center Production Goal Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::GOALS->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);

        // A goal has no name of its own, so it is identified by the center and
        // year it targets rather than showing as "—".
        $this->assertSame('CenterProductionGoal', $described['subject']['type']);
        $this->assertSame('CLSU · 2026', $described['subject']['label']);
        $this->assertSame('CLSU', $described['organization']['code']);

        $this->assertSame(
            [['label' => 'Milk Production Goal', 'from' => '15000.00', 'to' => '20000.00']],
            $described['changes']
        );
    }

    public function test_a_goal_edit_is_found_by_the_center_filter(): void
    {
        $goal = CenterSalesGoal::query()->create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'milk_sales_goal' => 100,
        ]);

        $this->actingAs($this->pccAdmin);

        // Re-read first: the audit snapshots the "before" state from the model's
        // own original attributes, so a record created earlier in this same request
        // would report every database default as a change. Route model binding
        // gives controllers a loaded record; the test has to do the same.
        $goal->refresh();

        $goal->update(['milk_sales_goal' => 250]);

        $this->actingAs($this->pccAdmin)
            ->get(route('audits.index', ['filter' => ['center' => $this->center->id]]))
            ->assertOk()
            ->assertSee('Center Sales Goal Updated');
    }

    public function test_adding_a_product_is_recorded_and_named_by_its_label(): void
    {
        $this->actingAs($this->pccAdmin);

        $product = Product::query()->create([
            'code' => 'MIL-00001',
            'name' => MilkProductTypeEnum::FRESH_MILK->value,
            'category' => ProductCategoryEnum::MILK,
        ]);

        $product->centers()->syncWithoutDetaching([$this->center->id]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Product Created', $described['activity']);
        $this->assertSame(AuditCategoryEnum::PRODUCTS->value, $described['category']);
        $this->assertSame(AuditActionEnum::CREATED->value, $described['action']);

        // The stored name is the enum value; the trail shows what a reader needs.
        $this->assertSame('Fresh Milk', $described['subject']['label']);

        // A product reaches its center through a pivot, not a center_id column.
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_creating_a_ccdp_project_site_is_recorded_under_projects(): void
    {
        $this->actingAs($this->pccAdmin);

        ProjectSite::query()->create([
            'center_id' => $this->center->id,
            'project_site_code' => 'CCDP-0001',
            'year' => 2026,
            'value_chain_status' => 'active',
        ]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Project Site Created', $described['activity']);
        $this->assertSame(AuditCategoryEnum::PROJECTS->value, $described['category']);
        $this->assertSame(AuditActionEnum::CREATED->value, $described['action']);

        // A site has no name; it is identified by its own code.
        $this->assertSame('ProjectSite', $described['subject']['type']);
        $this->assertSame('CCDP-0001', $described['subject']['label']);
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_a_site_edit_is_found_by_the_center_filter(): void
    {
        $site = ProjectSite::query()->create([
            'center_id' => $this->center->id,
            'project_site_code' => 'CBIN-0001',
            'year' => 2026,
        ]);

        $this->actingAs($this->pccAdmin);

        $site->refresh();
        $site->update(['staff_name' => 'Atang Dela Cruz']);

        $this->actingAs($this->pccAdmin)
            ->get(route('audits.index', ['filter' => ['center' => $this->center->id]]))
            ->assertOk()
            ->assertSee('Project Site Updated');
    }

    public function test_a_manual_milk_production_entry_is_recorded_and_named_by_its_period(): void
    {
        $this->actingAs($this->pccAdmin);

        $production = MilkProduction::query()->create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 3,
            'production_code' => 'MP-0001',
        ]);

        $production->refresh();
        $production->update(['total_volume_produced' => 1200]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Milk Production Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::DATA->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);

        // A production row has no name; the trail names it by its coordinates.
        $this->assertSame('CLSU · 2026 · March', $described['subject']['label']);
        $this->assertSame('CLSU', $described['organization']['code']);
    }

    public function test_updating_a_dairy_box_records_the_edit_under_profiles(): void
    {
        $box = DairyBox::create([
            'center_id' => $this->center->id,
            'business_name' => 'CLSU Fresh Milk Kiosk',
        ]);

        $this->actingAs($this->centerAdmin);

        $box->refresh();
        $box->update(['business_name' => 'CLSU Dairy Box']);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Dairy Box Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::PROFILES->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);

        // A dairy box is named by the business it runs, not by a `name` column.
        $this->assertSame('CLSU Dairy Box', $described['subject']['label']);
    }

    public function test_a_service_record_is_recorded_under_services(): void
    {
        $service = Service::query()->create([
            'name' => 'Artificial Insemination',
            'simplified_code' => 'AI-01',
        ]);

        $this->actingAs($this->centerAdmin);

        $record = ServiceRecord::create([
            'center_id' => $this->center->id,
            'service_id' => $service->id,
            'client_name' => 'Juan Dela Cruz',
        ]);

        $record->refresh();
        $record->update(['no_animals_served' => 5]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Service Record Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::SERVICES->value, $described['category']);
        $this->assertSame(AuditActionEnum::UPDATED->value, $described['action']);
    }

    public function test_a_service_is_recorded_under_services(): void
    {
        $this->actingAs($this->pccAdmin);

        $service = Service::query()->create([
            'name' => 'Artificial Insemination',
            'simplified_code' => 'AI-01',
        ]);

        $service->refresh();
        $service->update(['description' => 'AI services delivered to the herd']);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame('Service Updated', $described['activity']);
        $this->assertSame(AuditCategoryEnum::SERVICES->value, $described['category']);
        $this->assertSame('Artificial Insemination', $described['subject']['label']);
    }

    public function test_a_bulk_import_does_not_write_one_audit_row_per_row(): void
    {
        // A sheet import writes one row per line through the same model events a
        // manual entry uses. Left alone, a single upload would push thousands of
        // audit rows and bury the hand-made edits, so the dispatcher silences
        // model logging for the duration of the workflow.
        ActivityFacade::withoutLogs(function () {
            MilkProduction::query()->create([
                'center_id' => $this->center->id,
                'year' => 2026,
                'month' => 3,
            ]);

            MilkProduction::query()->create([
                'center_id' => $this->center->id,
                'year' => 2026,
                'month' => 4,
            ]);
        });

        $this->assertSame(0, $this->activitiesFor('milk_production'));
    }

    public function test_suppression_is_lifted_after_a_bulk_import(): void
    {
        // A failure during an import is still worth recording, so the suppression
        // must not leak past the workflow it wrapped.
        ActivityFacade::withoutLogs(fn () => null);

        $this->assertFalse(app(ActivityLogStatus::class)->disabled());

        $this->actingAs($this->pccAdmin);

        MilkProduction::query()->create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 3,
        ]);

        $this->assertSame(1, $this->activitiesFor('milk_production'));
    }

    /**
     * How many rows the log holds for one subject type. The fixtures in setUp()
     * log too, so a bare total would not isolate what the test is about.
     */
    private function activitiesFor(string $subjectType): int
    {
        return Activity::query()->where('subject_type', $subjectType)->count();
    }

    private function lastActivity(): Activity
    {
        return Activity::query()->latest('id')->firstOrFail();
    }

    private function user(string $username, ?int $centerId, string $role): User
    {
        $user = User::create([
            'agency_id' => $this->agency->id,
            'center_id' => $centerId,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@pcc.gov.ph',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}

<?php

namespace Tests\Feature\Audits;

use App\Actions\Audit\DescribeAuditActivityAction;
use App\Enums\AuditCategoryEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\ManureProduction;
use App\Models\MeatProduction;
use App\Models\MilkProduction;
use App\Models\MilkUtilizationMonthlySummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Facades\Activity as ActivityFacade;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

/**
 * A hand-entered production figure has to leave a trace.
 *
 * Milk, meat and manure rows share the same audit trait as the profiles, so a
 * manual create, edit or delete writes a row naming the actor and what moved.
 * Figures arriving by spreadsheet import deliberately do not: the import writes
 * one "{title} Imported" row on its document instead of a row per figure, so
 * the trail keeps pointing at hand edits rather than burying them.
 */
class ProductionAuditTrailTest extends TestCase
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

    public function test_milk_utilization_inputs_record_the_actor_and_changed_values(): void
    {
        $this->actingAs($this->centerAdmin);

        $payload = [
            'center_code' => $this->center->code,
            'partner_id' => null,
            'year' => 2025,
            'month' => 12,
            'program' => 'ih',
            'received' => 10,
        ];

        $this->postJson('/reports/milk-utilization/monthly-summary', $payload)->assertCreated();

        $created = Activity::where('subject_type', 'milk_utilization_monthly_summary')->latest('id')->firstOrFail();
        $this->assertSame('created', $created->event);
        $this->assertTrue($created->causer->is($this->centerAdmin));
        $this->assertEquals(10, $created->properties['attributes']['received']);
        $this->assertSame(AuditCategoryEnum::DATA->value, app(DescribeAuditActivityAction::class)->execute($created)['category']);
        $this->assertArrayNotHasKey('month', $created->properties['attributes']);
        $this->assertSame('CLSU · 2025', app(DescribeAuditActivityAction::class)->execute($created)['subject']['label']);

        $properties = $created->properties->toArray();
        $properties['attributes']['month'] = 12;
        $created->properties = collect($properties);
        $described = app(DescribeAuditActivityAction::class)->execute($created);
        $this->assertNotContains('Month', array_column($described['changes'], 'label'));

        $payload['received'] = 25;
        $this->postJson('/reports/milk-utilization/monthly-summary', $payload)->assertCreated();

        $updated = Activity::where('subject_type', 'milk_utilization_monthly_summary')->latest('id')->firstOrFail();
        $this->assertSame('updated', $updated->event);
        $this->assertTrue($updated->causer->is($this->centerAdmin));
        $this->assertEquals(10, $updated->properties['old']['received']);
        $this->assertEquals(25, $updated->properties['attributes']['received']);
        $this->assertArrayNotHasKey('updated_at', $updated->properties['attributes']);

        $before = Activity::where('subject_type', 'milk_utilization_monthly_summary')->count();
        $this->postJson('/reports/milk-utilization/monthly-summary', $payload)->assertCreated();
        $this->assertSame($before, Activity::where('subject_type', 'milk_utilization_monthly_summary')->count());
    }

    public function test_deleting_a_milk_utilization_input_records_the_actor(): void
    {
        $this->actingAs($this->centerAdmin);
        $summary = MilkUtilizationMonthlySummary::create([
            'center_id' => $this->center->id, 'year' => 2025, 'month' => 12,
            'program' => 'ih', 'received' => 10,
        ]);

        $summary->delete();

        $activity = Activity::where('subject_type', 'milk_utilization_monthly_summary')->latest('id')->firstOrFail();
        $this->assertSame('deleted', $activity->event);
        $this->assertTrue($activity->causer->is($this->centerAdmin));
        $this->assertSame(AuditCategoryEnum::DATA->value, app(DescribeAuditActivityAction::class)->execute($activity)['category']);
        $this->assertSame('CLSU · 2025', app(DescribeAuditActivityAction::class)->execute($activity)['subject']['label']);
    }

    public function test_manually_entering_a_milk_figure_records_the_actor(): void
    {
        $this->actingAs($this->centerAdmin);

        MilkProduction::create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 1,
            'total_volume_produced' => 10,
        ]);

        $activity = $this->lastActivity();

        $this->assertSame('created', $activity->event);
        $this->assertSame('Milk Production Created', $activity->description);
        $this->assertTrue($activity->causer->is($this->centerAdmin));
    }

    public function test_manually_editing_a_meat_figure_records_what_moved(): void
    {
        $this->actingAs($this->centerAdmin);

        $row = MeatProduction::create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 1,
            'total_volume' => 10,
        ]);
        $row->update(['total_volume' => 20]);

        $activity = $this->lastActivity();

        $this->assertSame('updated', $activity->event);
        $this->assertSame('Meat Production Updated', $activity->description);
        $this->assertEquals(10, $activity->properties['old']['total_volume'] ?? null);
        $this->assertEquals(20, $activity->properties['attributes']['total_volume'] ?? null);
    }

    public function test_manually_deleting_a_manure_figure_records_the_deletion(): void
    {
        $this->actingAs($this->centerAdmin);

        $row = ManureProduction::create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 1,
            'total_volume' => 10,
        ]);
        $row->delete();

        $activity = $this->lastActivity();

        $this->assertSame('deleted', $activity->event);
        $this->assertSame('Manure Production Deleted', $activity->description);
        $this->assertTrue($activity->causer->is($this->centerAdmin));
    }

    public function test_saving_a_figure_unchanged_writes_no_row(): void
    {
        $this->actingAs($this->centerAdmin);

        $row = MilkProduction::create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 1,
            'total_volume_produced' => 10,
        ]);

        $before = $this->activitiesFor('milk_production');

        $row->update(['total_volume_produced' => 10]);

        $this->assertSame($before, $this->activitiesFor('milk_production'));
    }

    public function test_figures_arriving_by_import_leave_no_per_row_entries(): void
    {
        $this->actingAs($this->centerAdmin);

        ActivityFacade::withoutLogs(function () {
            MilkProduction::create([
                'center_id' => $this->center->id,
                'year' => 2026,
                'month' => 1,
                'total_volume_produced' => 10,
            ]);
            MeatProduction::create([
                'center_id' => $this->center->id,
                'year' => 2026,
                'month' => 1,
                'total_volume' => 10,
            ]);
            ManureProduction::create([
                'center_id' => $this->center->id,
                'year' => 2026,
                'month' => 1,
                'total_volume' => 10,
            ]);
        });

        // No per-row entries: only the set-up rows (agency, center, two users).
        $this->assertSame(0, $this->activitiesFor('milk_production'));
        $this->assertSame(0, $this->activitiesFor('meat_production'));
        $this->assertSame(0, $this->activitiesFor('manure_production'));
    }

    public function test_a_manual_entry_reads_under_the_data_category(): void
    {
        $this->actingAs($this->centerAdmin);

        MilkProduction::create([
            'center_id' => $this->center->id,
            'year' => 2026,
            'month' => 1,
            'total_volume_produced' => 10,
        ]);

        $described = app(DescribeAuditActivityAction::class)->execute($this->lastActivity());

        $this->assertSame(AuditCategoryEnum::DATA->value, $described['category']);
        $this->assertSame('CLSU · 2026 · January', $described['subject']['label']);
    }

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

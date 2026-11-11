<?php

namespace Tests\Feature\MonthlyTasks;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Queries\MonthlyTask\TaskCompletionRateQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTaskEndpointsTest extends TestCase
{
    use InteractsWithMonthlyTasks;
    use RefreshDatabase;

    private Agency $agency;

    private Center $mine;

    private Center $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->mine = $this->center($this->agency, 'CLSU');
        $this->theirs = $this->center($this->agency, 'MMSU');
    }

    public function test_the_reminder_preview_names_the_accounts_that_can_act_on_a_task(): void
    {
        $this->monthlyTask($this->mine);
        $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, $this->mine);
        $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->mine);
        $this->monthlyTaskCenterAdmin($this->mine);

        $response = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->postJson(route('monthly-tasks.remind'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['center_count']);
        $this->assertSame(2, $response['recipient_count']);
        $this->assertSame($this->mine->id, $response['centers'][0]['center_id']);
    }

    public function test_the_reminder_preview_excludes_a_deactivated_account(): void
    {
        $admin = $this->userWithRole(
            UserRoleEnum::CENTER_ADMIN,
            $this->agency,
            $this->mine,
            UserStatusEnum::DEACTIVATED,
        );

        $this->monthlyTask($this->mine);

        $response = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->postJson(route('monthly-tasks.remind'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $response['recipient_count']);
        $this->assertSame(UserStatusEnum::DEACTIVATED, $admin->fresh()->status);
    }

    public function test_the_reminder_preview_sends_nothing(): void
    {
        $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->postJson(route('monthly-tasks.remind'))
            ->assertOk()
            ->assertJsonStructure(['centers', 'center_count', 'recipient_count', 'overdue_count']);
    }

    public function test_a_center_scoped_viewers_reminder_preview_covers_only_its_own_center(): void
    {
        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);
        $this->monthlyTaskCenterAdmin($this->mine);
        $this->monthlyTaskCenterAdmin($this->theirs);

        $response = $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->postJson(route('monthly-tasks.remind'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['center_count']);
        $this->assertSame($this->mine->id, $response['centers'][0]['center_id']);
    }

    public function test_the_completion_report_is_pcc_level(): void
    {
        $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->getJson(route('monthly-tasks.reports.completion-rate'))
            ->assertForbidden();
    }

    public function test_the_completion_report_names_the_center_behind_each_rate(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $response = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->getJson(route('monthly-tasks.reports.completion-rate', [
                'from_year' => 2026, 'from_month' => 1, 'to_year' => 2026, 'to_month' => 12,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame('CLSU Center', $response['by_center'][0]['center_name']);
        $this->assertSame('CLSU', $response['by_center'][0]['center_code']);
        $this->assertEqualsWithDelta(100.0, $response['by_center'][0]['completion_rate'], 0.001);
    }

    public function test_an_unreviewed_month_does_not_count_towards_the_completion_rate(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);

        $response = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->getJson(route('monthly-tasks.reports.completion-rate', [
                'from_year' => 2026, 'from_month' => 1, 'to_year' => 2026, 'to_month' => 12,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(0, $response['by_center'][0]['completed']);
        $this->assertEqualsWithDelta(0.0, $response['by_center'][0]['completion_rate'], 0.001);
    }

    public function test_the_completion_report_defaults_to_the_current_year(): void
    {
        $this->monthlyTask($this->mine, ['year' => (int) now()->year, 'month' => 5]);

        $response = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->getJson(route('monthly-tasks.reports.completion-rate'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['by_center']);
    }

    public function test_the_completion_report_refuses_a_range_that_runs_backwards(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.reports.completion-rate', [
                'from_year' => 2026, 'from_month' => 12, 'to_year' => 2026, 'to_month' => 1,
            ]))
            ->assertSessionHasErrors('to_month');
    }

    public function test_the_completion_report_refuses_a_month_outside_the_calendar(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->getJson(route('monthly-tasks.reports.completion-rate', [
                'from_year' => 2026, 'from_month' => 13,
            ]))
            ->assertJsonValidationErrors('from_month');
    }

    public function test_the_completion_report_reads_every_center_for_a_pcc_reader(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $executive = $this->userWithRole(UserRoleEnum::EXECUTIVE, $this->agency, null);

        $response = $this->actingAs($executive)
            ->getJson(route('monthly-tasks.reports.completion-rate', [
                'from_year' => 2026, 'from_month' => 1, 'to_year' => 2026, 'to_month' => 12,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(2, $response['by_center']);
    }

    public function test_an_agency_wide_encoder_still_cannot_read_the_completion_report(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);

        $this->actingAs($encoder)
            ->getJson(route('monthly-tasks.reports.completion-rate'))
            ->assertForbidden();
    }

    public function test_a_center_filter_on_the_report_cannot_widen_a_center_scoped_read(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $rates = app(TaskCompletionRateQuery::class)
            ->execute(2026, 1, 2026, 12, $this->theirs->id, $this->monthlyTaskCenterAdmin($this->mine));

        $this->assertCount(0, $rates);
    }

    public function test_the_listing_sends_an_entry_route_for_every_product_and_type(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $routes = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props']['entryRoutes'];

        $this->assertSame([
            'milk:production',
            'milk:sales',
            'meat:production',
            'meat:sales',
            'manure:production',
            'manure:sales',
        ], array_keys($routes));

        $this->assertSame('meat-productions.create', $routes['meat:production']['route']);
        $this->assertSame('manure-sales.create', $routes['manure:sales']['route']);
    }

    public function test_milk_sales_falls_back_to_its_index(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $routes = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props']['entryRoutes'];

        $this->assertSame('milk-sales.index', $routes['milk:sales']['route']);
        $this->assertFalse($routes['milk:sales']['prefilled']);

        $this->assertTrue($routes['milk:production']['prefilled']);
    }
}

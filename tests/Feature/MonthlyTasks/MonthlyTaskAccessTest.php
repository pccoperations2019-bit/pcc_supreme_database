<?php

namespace Tests\Feature\MonthlyTasks;

use App\Enums\MonthlyTaskStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\MonthlyTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTaskAccessTest extends TestCase
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

    public function test_a_center_admin_reaches_its_own_checklist(): void
    {
        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.index'))
            ->assertOk();
    }

    public function test_a_center_admin_is_pinned_to_its_own_center(): void
    {
        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);

        $props = $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $centerIds = array_column($props['tasks']['data'], 'center_id');

        $this->assertSame([$this->mine->id], array_values(array_unique($centerIds)));
        $this->assertNotContains($this->theirs->id, $centerIds);
    }

    public function test_a_center_filter_naming_another_center_does_not_widen_the_listing(): void
    {
        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);

        $props = $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.index', ['filter' => ['center_id' => $this->theirs->id]]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotContains(
            $this->theirs->id,
            array_column($props['tasks']['data'], 'center_id'),
        );
    }

    public function test_a_center_admin_cannot_open_another_centers_task(): void
    {
        $theirs = $this->monthlyTask($this->theirs);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.show', $theirs))
            ->assertForbidden();
    }

    public function test_a_pcc_admin_reads_every_centers_tasks(): void
    {
        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);

        $props = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $centerIds = array_column($props['tasks']['data'], 'center_id');

        $this->assertContains($this->mine->id, $centerIds);
        $this->assertContains($this->theirs->id, $centerIds);
    }

    public function test_an_agency_wide_encoder_reads_every_centers_tasks(): void
    {
        $encoder = $this->userWithRole(UserRoleEnum::ENCODER, $this->agency, null);

        $this->monthlyTask($this->mine);
        $this->monthlyTask($this->theirs);

        $props = $this->actingAs($encoder)
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $centerIds = array_column($props['tasks']['data'], 'center_id');

        $this->assertContains($this->mine->id, $centerIds);
        $this->assertContains($this->theirs->id, $centerIds);
    }

    public function test_a_reviewer_sees_its_own_centers_tasks_but_cannot_report_on_them(): void
    {
        $reviewer = $this->userWithRole(UserRoleEnum::REVIEWER, $this->agency, $this->mine);
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($reviewer)->get(route('monthly-tasks.index'))->assertOk();

        $this->actingAs($reviewer)
            ->patch(route('monthly-tasks.status', $task), ['no_data_reported' => true])
            ->assertForbidden();

        $this->assertFalse($task->refresh()->is_completed);
    }

    public function test_a_center_admin_may_report_no_data_for_its_own_task(): void
    {
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->patch(route('monthly-tasks.status', $task), ['no_data_reported' => true])
            ->assertRedirect(route('monthly-tasks.index'));

        $this->assertSame(MonthlyTaskStatusEnum::NO_DATA, $task->fresh()->status());
    }

    public function test_a_center_admin_cannot_claim_a_task_is_completed(): void
    {
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->patch(route('monthly-tasks.status', $task), [
                'is_completed' => true,
                'no_data_reported' => true,
            ]);

        $this->assertSame(MonthlyTaskStatusEnum::NO_DATA, $task->fresh()->status());
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $task->fresh()->status());
    }

    public function test_a_task_awaits_review_once_its_figures_are_entered(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 3]);
        $this->withMilkProduction($this->mine, 2026, 3);

        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $task->fresh()->status());
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $task->fresh()->status());
    }

    public function test_a_center_admin_can_open_and_save_its_own_task_form(): void
    {
        $admin = $this->monthlyTaskCenterAdmin($this->mine);
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($admin)->get(route('monthly-tasks.edit', $task))->assertOk();

        $this->actingAs($admin)
            ->put(route('monthly-tasks.update', $task), [
                'no_data_reported' => true,
                'notes' => 'Awaiting the partner consolidated figure.',
            ])
            ->assertRedirect(route('monthly-tasks.show', $task));

        $this->assertTrue($task->refresh()->is_no_data);
        $this->assertSame('Awaiting the partner consolidated figure.', $task->notes);
    }

    public function test_the_task_form_cannot_move_a_task_to_another_center_or_month(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->put(route('monthly-tasks.update', $task), [
                'no_data_reported' => true,
                'center_id' => $this->theirs->id,
                'year' => 2030,
                'month' => 6,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->mine->id, $task->refresh()->center_id);
        $this->assertSame(2026, $task->year);
        $this->assertSame(1, $task->month);
    }

    public function test_a_center_admin_cannot_open_another_centers_task_form(): void
    {
        $theirs = $this->monthlyTask($this->theirs);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.edit', $theirs))
            ->assertForbidden();
    }

    public function test_a_center_admin_cannot_tick_another_centers_task(): void
    {
        $theirs = $this->monthlyTask($this->theirs);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->patch(route('monthly-tasks.status', $theirs), ['is_completed' => true])
            ->assertForbidden();

        $this->assertFalse($theirs->refresh()->is_completed);
    }

    public function test_a_no_data_report_records_who_made_it(): void
    {
        $admin = $this->monthlyTaskCenterAdmin($this->mine);
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($admin)->patch(route('monthly-tasks.status', $task), [
            'no_data_reported' => true,
            'completed_by' => $this->monthlyTaskPccAdmin($this->agency)->id,
        ]);

        $this->assertSame($admin->id, $task->refresh()->no_data_by);
    }

    public function test_a_no_data_report_gives_way_to_figures_that_arrive_afterwards(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 3]);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->patch(route('monthly-tasks.status', $task), ['no_data_reported' => true]);

        $this->withMilkProduction($this->mine, 2026, 3);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $fresh->status());
        $this->assertTrue($fresh->hasEnteredData());
    }

    public function test_withdrawing_a_no_data_report_clears_who_made_it(): void
    {
        $admin = $this->monthlyTaskCenterAdmin($this->mine);
        $task = $this->monthlyTask($this->mine);
        $task->markAsNoData($admin);

        $this->actingAs($admin)
            ->patch(route('monthly-tasks.status', $task), ['no_data_reported' => false])
            ->assertRedirect(route('monthly-tasks.index'));

        $this->assertFalse($task->refresh()->is_completed);
        $this->assertNull($task->no_data_by);
    }

    public function test_a_center_admin_cannot_delete_a_task(): void
    {
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->delete(route('monthly-tasks.destroy', $task))
            ->assertForbidden();

        $this->assertDatabaseHas('monthly_tasks', ['id' => $task->id]);
    }

    public function test_a_pcc_admin_can_delete_a_task(): void
    {
        $task = $this->monthlyTask($this->mine);

        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->delete(route('monthly-tasks.destroy', $task))
            ->assertRedirect(route('monthly-tasks.index'));

        $this->assertDatabaseMissing('monthly_tasks', ['id' => $task->id]);
    }

    public function test_a_pcc_admin_can_open_the_create_page_with_its_options(): void
    {
        $props = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.create'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(
            ['milk', 'meat', 'manure'],
            array_column($props['enums']['products'], 'value'),
        );
        $this->assertSame(
            ['production', 'sales'],
            array_column($props['enums']['taskTypes'], 'value'),
        );
        $this->assertSame([$this->mine->id, $this->theirs->id], array_column($props['centers'], 'id'));
        $this->assertContains((int) now()->year, $props['enums']['availableYears']);
        $this->assertSame((int) now()->year, $props['monthlyTask']['year']);
        $this->assertSame((int) now()->month, $props['monthlyTask']['month']);
        $this->assertSame('ih', $props['monthlyTask']['program']);
        $this->assertSame(['ih', 'cbed'], array_column($props['enums']['programs'], 'value'));
    }

    public function test_a_center_admin_cannot_open_the_create_page(): void
    {
        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.create'))
            ->assertForbidden();
    }

    public function test_a_center_admin_cannot_create_a_task(): void
    {
        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->post(route('monthly-tasks.store'), [
                'center_id' => $this->mine->id,
                'program' => 'ih',
                'product_type' => 'milk',
                'task_type' => 'production',
                'year' => 2026,
                'month' => 6,
            ])
            ->assertForbidden();
    }

    public function test_a_pcc_admin_can_create_a_task_by_hand(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.store'), [
                'center_id' => $this->mine->id,
                'program' => 'ih',
                'product_type' => 'milk',
                'task_type' => 'production',
                'year' => 2026,
                'month' => 6,
            ]);

        $this->assertDatabaseHas('monthly_tasks', [
            'center_id' => $this->mine->id,
            'product_type' => 'milk',
            'task_type' => 'production',
            'year' => 2026,
            'month' => 6,
        ]);
    }

    public function test_creating_a_duplicate_task_is_refused_with_a_field_error(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 6]);

        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.store'), [
                'center_id' => $this->mine->id,
                'program' => 'ih',
                'product_type' => 'milk',
                'task_type' => 'production',
                'year' => 2026,
                'month' => 6,
            ])
            ->assertSessionHasErrors('month');

        $this->assertSame(1, MonthlyTask::query()->count());
    }

    public function test_a_task_is_created_pending_regardless_of_what_the_payload_claims(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.store'), [
                'center_id' => $this->mine->id,
                'program' => 'ih',
                'product_type' => 'milk',
                'task_type' => 'production',
                'year' => 2026,
                'month' => 6,
                'is_completed' => true,
            ]);

        $this->assertFalse(MonthlyTask::query()->firstOrFail()->is_completed);
    }

    public function test_a_center_admin_cannot_create_a_task_in_another_center(): void
    {
        $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->post(route('monthly-tasks.store'), [
                'center_id' => $this->theirs->id,
                'product_type' => 'milk',
                'task_type' => 'production',
                'year' => 2026,
                'month' => 6,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('monthly_tasks', ['center_id' => $this->theirs->id]);
    }
}

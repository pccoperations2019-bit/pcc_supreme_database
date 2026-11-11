<?php

namespace Tests\Feature\MonthlyTasks;

use App\Enums\MonthlyTaskReviewStatusEnum;
use App\Enums\MonthlyTaskStatusEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\MilkProduction;
use App\Queries\MonthlyTask\TaskHistoryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MonthlyTaskStatusTest extends TestCase
{
    use InteractsWithMonthlyTasks;
    use RefreshDatabase;

    private Agency $agency;

    private Center $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->center = $this->center($this->agency, 'CLSU');
    }

    public function test_a_new_task_is_pending_and_asserts_nothing(): void
    {
        $task = $this->monthlyTask($this->center);

        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->status());
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
        $this->assertNull($task->completed_by);
        $this->assertFalse($task->is_no_data);
        $this->assertNull($task->no_data_at);
        $this->assertNull($task->no_data_by);
    }

    public function test_a_task_awaits_review_when_its_figures_arrive(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->status());

        $this->withMilkProduction($this->center, 2026, 1);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $fresh->status());
        $this->assertTrue($fresh->isAwaitingReview());
        $this->assertFalse($fresh->isResolved());
        $this->assertFalse($fresh->is_completed);
    }

    public function test_only_a_reviewers_approval_completes_a_task(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->agency);

        $this->withMilkProduction($this->center, 2026, 1);
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $task->fresh()->status());

        $task->markAsApproved($reviewer);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskStatusEnum::COMPLETED, $fresh->status());
        $this->assertTrue($fresh->isApproved());
        $this->assertTrue($fresh->isResolved());
        $this->assertSame($reviewer->id, $fresh->completed_by);
    }

    public function test_a_payload_cannot_approve_its_own_figures(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->withMilkProduction($this->center, 2026, 1);

        $task->update([
            'review_status' => MonthlyTaskReviewStatusEnum::APPROVED->value,
            'reviewed_at' => now(),
            'is_completed' => true,
        ]);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskReviewStatusEnum::SUBMITTED, $fresh->reviewStatus());
        $this->assertFalse($fresh->isApproved());
        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $fresh->status());
    }

    public function test_a_return_clears_completion_and_records_a_reason(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->agency);

        $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($reviewer);

        $task->markAsReturned($reviewer, 'June volume looks like a daily figure entered as monthly.');

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskReviewStatusEnum::RETURNED, $fresh->reviewStatus());
        $this->assertFalse($fresh->is_completed);
        $this->assertNull($fresh->completed_at);
        $this->assertFalse($fresh->isResolved());
        $this->assertSame(
            'June volume looks like a daily figure entered as monthly.',
            $fresh->return_reason
        );
    }

    public function test_correcting_a_returned_submission_puts_it_back_in_the_queue(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $production = $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsReturned($this->monthlyTaskPccAdmin($this->agency), 'Check the volume.');
        $this->assertNotNull($task->fresh()->return_reason);

        $production->update(['total_volume_produced' => 420]);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskReviewStatusEnum::SUBMITTED, $fresh->reviewStatus());
        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $fresh->status());
        $this->assertNull($fresh->return_reason);
    }

    public function test_an_approval_records_the_reviewers_note(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->agency);

        $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($reviewer, 'Figures tie out against the delivery receipts.');

        $fresh = $task->fresh();

        $this->assertSame(
            'Figures tie out against the delivery receipts.',
            $fresh->review_note,
        );
    }

    public function test_a_return_discards_an_earlier_approval_note(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->agency);

        $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($reviewer, 'Looks right.');
        $this->assertNotNull($task->fresh()->review_note);

        $task->markAsReturned($reviewer, 'Recheck the volume.');

        $fresh = $task->fresh();

        $this->assertNull($fresh->review_note);
        $this->assertSame('Recheck the volume.', $fresh->return_reason);
    }

    public function test_removing_the_figures_withdraws_an_approval(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $production = $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));
        $this->assertSame(MonthlyTaskStatusEnum::COMPLETED, $task->fresh()->status());

        $production->delete();

        $fresh = $task->fresh();

        $this->assertNull($fresh->review_status);
        $this->assertFalse($fresh->is_completed);
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $fresh->status());
    }

    public function test_an_approval_does_not_survive_a_bulk_delete_of_the_figures(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        MilkProduction::query()->where('center_id', $this->center->id)->delete();

        $fresh = $task->fresh();

        $this->assertFalse($fresh->hasEnteredData());
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $fresh->status());
    }

    public function test_a_payload_cannot_write_the_status_columns(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $task->update(['is_completed' => true, 'completed_at' => now()]);

        $this->assertFalse($task->fresh()->is_completed);
        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->fresh()->status());
    }

    public function test_the_one_writable_flag_can_only_ever_mean_no_data(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $task->forceFill(['is_no_data' => true])->save();

        $this->assertSame(MonthlyTaskStatusEnum::NO_DATA, $task->fresh()->status());
        $this->assertNotSame(MonthlyTaskStatusEnum::COMPLETED, $task->fresh()->status());
    }

    public function test_reporting_no_data_settles_the_task_without_claiming_work(): void
    {
        $user = $this->monthlyTaskCenterAdmin($this->center);
        $task = $this->monthlyTask($this->center);

        $task->markAsNoData($user);

        $this->assertSame(MonthlyTaskStatusEnum::NO_DATA, $task->status());
        $this->assertTrue($task->isResolved());
        $this->assertFalse($task->hasEnteredData());
        $this->assertSame($user->id, $task->no_data_by);
    }

    public function test_figures_landing_later_supersede_a_no_data_report(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $task->markAsNoData($this->monthlyTaskCenterAdmin($this->center));

        $this->withMilkProduction($this->center, 2026, 1);

        $fresh = $task->fresh();

        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $fresh->status());
        $this->assertTrue($fresh->hasEnteredData());
    }

    public function test_withdrawing_a_no_data_report_puts_the_task_back_to_pending(): void
    {
        $task = $this->monthlyTask($this->center);
        $task->markAsNoData($this->monthlyTaskCenterAdmin($this->center));

        $task->clearNoDataReport();

        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->status());
        $this->assertNull($task->no_data_at);
        $this->assertNull($task->no_data_by);
    }

    public function test_the_completion_columns_cannot_be_forged_by_a_payload(): void
    {
        $task = $this->monthlyTask($this->center);

        $task->update([
            'is_completed' => true,
            'completed_by' => $this->monthlyTaskPccAdmin($this->agency)->id,
        ]);

        $this->assertFalse($task->refresh()->is_completed);
        $this->assertNull($task->completed_by);
    }

    public function test_a_task_is_due_seven_days_after_its_month_closes(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->assertSame('2026-02-07', $task->dueDate()->toDateString());
    }

    public function test_a_task_is_pending_inside_the_grace_period(): void
    {
        $this->travelTo(now()->parse('2026-02-05'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->status());
    }

    public function test_a_task_is_overdue_after_the_grace_period(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->assertSame(MonthlyTaskStatusEnum::OVERDUE, $task->status());
    }

    public function test_a_completed_task_is_never_overdue_however_late_it_was_filled(): void
    {
        $this->travelTo(now()->parse('2026-06-01'));
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->center, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $this->assertSame(MonthlyTaskStatusEnum::COMPLETED, $task->status());
        $this->assertTrue($task->isPastDue());
        $this->assertTrue($task->fresh()->status()->isResolved());
    }

    public function test_a_completed_task_is_excluded_from_the_overdue_filter(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));

        $late = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $filled = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMeatSales($this->center, 2026, 1);
        $filled->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $awaiting = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'milk', 'task_type' => 'sales',
        ]);
        $this->withMilkSale($this->center, 2026, 1);

        $reported = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'manure',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->center));

        $this->assertSame(MonthlyTaskStatusEnum::OVERDUE, $late->status());
        $this->assertSame(MonthlyTaskStatusEnum::COMPLETED, $filled->fresh()->status());
        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $awaiting->fresh()->status());
        $this->assertFalse($awaiting->fresh()->status()->isResolved());

        $request = Request::create('/monthly-tasks', 'GET', ['filter' => ['overdue' => '1']]);

        $ids = app(TaskHistoryQuery::class)
            ->execute($request, $this->monthlyTaskPccAdmin($this->agency))
            ->pluck('id')
            ->all();

        $this->assertSame([$late->id], $ids);

        $queue = Request::create('/monthly-tasks', 'GET', ['filter' => ['status' => 'under_review']]);

        $queued = app(TaskHistoryQuery::class)
            ->execute($queue, $this->monthlyTaskPccAdmin($this->agency))
            ->pluck('id')
            ->all();

        $this->assertContains($awaiting->id, $queued);
    }

    public function test_a_task_reports_its_period_for_display(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 3]);

        $this->assertSame('March 2026', $task->period());
    }

    public function test_a_production_task_is_answered_only_by_the_production_table(): void
    {
        $task = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMilkProduction($this->center, 2026, 1);

        $this->assertFalse($task->hasEnteredData());
    }

    public function test_a_production_task_does_not_count_a_sales_table(): void
    {
        $task = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat', 'task_type' => 'production',
        ]);
        $this->withMeatSales($this->center, 2026, 1);

        $this->assertFalse($task->hasEnteredData());
    }

    public function test_a_sales_task_with_its_own_table_behind_it_reports_data_entered(): void
    {
        $task = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMeatSales($this->center, 2026, 1);

        $this->assertTrue($task->hasEnteredData());
    }

    public function test_a_production_task_is_not_satisfied_by_another_programs_figures(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $task = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'milk', 'program' => 'cbed',
        ]);
        $this->withMilkProduction($this->center, 2026, 1, 'ih');

        $this->assertFalse($task->hasEnteredData());
        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $task->status());
    }

    public function test_a_production_task_is_satisfied_by_its_own_programs_figures(): void
    {
        $task = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'milk', 'program' => 'cbed',
        ]);
        $this->withMilkProduction($this->center, 2026, 1, 'cbed');

        $this->assertTrue($task->hasEnteredData());
        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $task->status());
        $this->assertTrue($task->isAwaitingReview());
    }

    public function test_both_programs_can_be_satisfied_independently(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));
        $ih = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'milk', 'program' => 'ih',
        ]);
        $cbed = $this->monthlyTask($this->center, [
            'year' => 2026, 'month' => 1, 'product_type' => 'milk', 'program' => 'cbed',
        ]);

        $this->withMilkProduction($this->center, 2026, 1, 'ih');

        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $ih->fresh()->status());
        $this->assertSame(MonthlyTaskStatusEnum::PENDING, $cbed->fresh()->status());
    }

    public function test_another_centers_data_does_not_count_as_this_centers(): void
    {
        $theirs = $this->center($this->agency, 'MMSU');

        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($theirs, 2026, 1);

        $this->assertFalse($task->hasEnteredData());
    }

    public function test_another_periods_data_does_not_count(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->center, 2026, 2);

        $this->assertFalse($task->hasEnteredData());
    }
}

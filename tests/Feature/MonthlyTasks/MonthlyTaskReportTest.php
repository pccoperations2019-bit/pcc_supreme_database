<?php

namespace Tests\Feature\MonthlyTasks;

use App\Enums\MonthlyTaskStatusEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Queries\MonthlyTask\PendingTasksByCenterQuery;
use App\Queries\MonthlyTask\TaskCompletionRateQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTaskReportTest extends TestCase
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

    public function test_the_report_counts_a_centers_tasks_and_rate(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $reported = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->mine));

        $rates = app(TaskCompletionRateQuery::class)->execute(2026, 1, 2026, 12);

        $this->assertCount(1, $rates);

        $rate = $rates->first();

        $this->assertSame($this->mine->id, $rate['center_id']);
        $this->assertSame(2, $rate['total']);
        $this->assertSame(1, $rate['completed']);
        $this->assertSame(1, $rate['no_data']);
        $this->assertSame(0, $rate['pending']);
        $this->assertSame(0, $rate['under_review']);
        $this->assertSame(100.0, $rate['completion_rate']);
    }

    public function test_a_task_with_figures_counts_as_awaiting_review_not_completed(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);

        $rate = app(TaskCompletionRateQuery::class)->execute(2026, 1, 2026, 12)->first();

        $this->assertSame(1, $rate['under_review']);
        $this->assertSame(0, $rate['completed']);
        $this->assertSame(0, $rate['no_data']);
        $this->assertSame(0, $rate['pending']);
        $this->assertSame(0.0, $rate['completion_rate']);
    }

    public function test_an_approved_month_moves_from_the_queue_to_completed(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $task->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $rate = app(TaskCompletionRateQuery::class)->execute(2026, 1, 2026, 12)->first();

        $this->assertSame(1, $rate['completed']);
        $this->assertSame(0, $rate['under_review']);
        $this->assertSame(100.0, $rate['completion_rate']);
    }

    public function test_a_returned_submission_leaves_the_review_queue_and_is_counted_separately(): void
    {
        $returned = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $returned->markAsReturned($this->monthlyTaskPccAdmin($this->agency), 'Volume looks wrong.');

        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'task_type' => 'sales']);
        $this->withMilkSale($this->mine, 2026, 1);

        $summary = app(TaskCompletionRateQuery::class)->executeWithTrend(2026, 1, 2026, 12)['summary'];

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['under_review']);
        $this->assertSame(1, $summary['returned']);
        $this->assertSame(0, $summary['pending']);
    }

    public function test_a_center_with_no_tasks_is_absent_from_the_report_rather_than_reported_as_zero(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $rates = app(TaskCompletionRateQuery::class)->execute(2026, 1, 2026, 12);

        $this->assertSame([$this->mine->id], $rates->pluck('center_id')->all());
    }

    public function test_the_rates_can_be_narrowed_to_one_center(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $rates = app(TaskCompletionRateQuery::class)->execute(2026, 1, 2026, 12, $this->theirs->id);

        $this->assertSame([$this->theirs->id], $rates->pluck('center_id')->all());
    }

    public function test_a_center_scoped_viewer_only_sees_its_own_centers_rate(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $rates = app(TaskCompletionRateQuery::class)
            ->execute(2026, 1, 2026, 12, null, $this->monthlyTaskCenterAdmin($this->mine));

        $this->assertSame([$this->mine->id], $rates->pluck('center_id')->all());
    }

    public function test_the_report_overdue_count_agrees_with_the_models_own_verdict(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));

        $late = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2, 'product_type' => 'meat']);

        $filledLate = $this->monthlyTask($this->mine, [
            'year' => 2025, 'month' => 12, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMeatSales($this->mine, 2025, 12);
        $filledLate->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $awaiting = $this->monthlyTask($this->mine, [
            'year' => 2025, 'month' => 12, 'product_type' => 'milk', 'task_type' => 'sales',
        ]);
        $this->withMilkSale($this->mine, 2025, 12);

        $reported = $this->monthlyTask($this->mine, [
            'year' => 2025, 'month' => 12, 'product_type' => 'manure',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->mine));

        $this->assertSame(MonthlyTaskStatusEnum::OVERDUE, $late->status());
        $this->assertSame(MonthlyTaskStatusEnum::COMPLETED, $filledLate->fresh()->status());
        $this->assertSame(MonthlyTaskStatusEnum::UNDER_REVIEW, $awaiting->fresh()->status());

        $rate = app(TaskCompletionRateQuery::class)->execute(2025, 12, 2026, 12)->first();

        $this->assertSame(1, $rate['overdue']);
        $this->assertSame(1, $rate['under_review']);
    }

    public function test_the_summary_figures_match_the_per_center_rows(): void
    {
        $filled = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $filled->markAsApproved($this->monthlyTaskPccAdmin($this->agency));
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $reported = $this->monthlyTask($this->theirs, [
            'year' => 2026, 'month' => 1, 'task_type' => 'sales',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->theirs));

        $summary = app(TaskCompletionRateQuery::class)->executeWithTrend(2026, 1, 2026, 12)['summary'];

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $summary['no_data']);
        $this->assertSame(0, $summary['under_review']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(66.67, $summary['completion_rate']);
    }

    public function test_an_empty_period_reports_zero_rather_than_dividing_by_zero(): void
    {
        $summary = app(TaskCompletionRateQuery::class)->executeWithTrend(2030, 1, 2030, 12)['summary'];

        $this->assertSame(0, $summary['total']);
        $this->assertSame(0, $summary['completed']);
        $this->assertSame(0.0, $summary['completion_rate']);
    }

    public function test_the_trend_reports_one_entry_per_month_with_tasks(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2, 'product_type' => 'meat']);

        $trend = app(TaskCompletionRateQuery::class)->executeWithTrend(2026, 1, 2026, 12)['trend'];

        $this->assertCount(2, $trend);
        $this->assertSame([2026, 1], [$trend->first()['year'], $trend->first()['month']]);
        $this->assertSame([2026, 2], [$trend->last()['year'], $trend->last()['month']]);
    }

    public function test_the_report_can_be_broken_down_by_product(): void
    {
        $production = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'product_type' => 'milk']);
        $this->withMilkProduction($this->mine, 2026, 1);
        $production->markAsApproved($this->monthlyTaskPccAdmin($this->agency));
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'product_type' => 'milk', 'task_type' => 'sales']);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'product_type' => 'meat']);

        $byProduct = app(TaskCompletionRateQuery::class)->executeByProduct(2026, 1, 2026, 12);

        $milk = $byProduct->firstWhere('product_type', 'milk');

        $this->assertSame(2, $milk['total']);
        $this->assertSame(1, $milk['completed']);
        $this->assertSame(1, $milk['pending']);
        $this->assertSame(50.0, $milk['completion_rate']);
    }

    public function test_the_pending_list_puts_the_most_overdue_center_first(): void
    {
        $this->travelTo(now()->parse('2026-03-01'));

        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 2]);

        $pending = app(PendingTasksByCenterQuery::class)->execute();

        $this->assertSame(
            [$this->mine->id, $this->theirs->id],
            $pending->pluck('center_id')->all(),
        );
    }

    public function test_the_outstanding_list_excludes_everything_that_is_settled(): void
    {
        $outstanding = $this->monthlyTask($this->mine, ['product_type' => 'meat']);

        $this->monthlyTask($this->mine, ['product_type' => 'milk']);
        $this->withMilkProduction($this->mine, (int) now()->year, (int) now()->month);

        $reported = $this->monthlyTask($this->mine, [
            'product_type' => 'manure', 'task_type' => 'sales',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->mine));

        $ids = app(PendingTasksByCenterQuery::class)->execute()->pluck('id')->all();

        $this->assertSame([$outstanding->id], $ids);
    }

    public function test_the_pending_counts_per_center_carry_the_overdue_split(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));

        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'task_type' => 'sales']);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 2]);

        $counts = app(PendingTasksByCenterQuery::class)->countsByCenter();

        $this->assertSame(2, $counts->firstWhere('center_id', $this->mine->id)['overdue']);
        $this->assertSame(2, $counts->firstWhere('center_id', $this->mine->id)['pending']);
        $this->assertArrayNotHasKey('completed', $counts->firstWhere('center_id', $this->mine->id));
        $this->assertSame(0, $counts->firstWhere('center_id', $this->theirs->id)['overdue']);
    }

    public function test_several_entries_in_one_period_count_as_a_single_task(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $this->withMilkProduction($this->mine, 2026, 1);

        $summary = app(TaskCompletionRateQuery::class)->executeWithTrend(2026, 1, 2026, 12)['summary'];

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['under_review']);
    }
}

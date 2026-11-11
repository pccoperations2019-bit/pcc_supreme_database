<?php

namespace Tests\Feature\MonthlyTasks;

use App\Models\Agency;
use App\Models\Center;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTaskFilterTest extends TestCase
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

    public function test_it_can_be_filtered_by_period(): void
    {
        $january = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2, 'product_type' => 'meat']);

        $ids = $this->taskIdsFor(['year' => 2026, 'month' => 1]);

        $this->assertSame([$january->id], $ids);
    }

    public function test_it_can_be_filtered_by_year_alone(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $next = $this->monthlyTask($this->mine, ['year' => 2027, 'month' => 1]);

        $this->assertSame([$next->id], $this->taskIdsFor(['year' => 2027]));
    }

    public function test_it_can_be_filtered_by_product(): void
    {
        $meat = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'product_type' => 'meat']);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'product_type' => 'milk']);

        $this->assertSame([$meat->id], $this->taskIdsFor(['product_type' => 'meat']));
    }

    public function test_it_can_be_filtered_by_task_type(): void
    {
        $sales = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'task_type' => 'sales']);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'task_type' => 'production']);

        $this->assertSame([$sales->id], $this->taskIdsFor(['task_type' => 'sales']));
    }

    public function test_it_can_be_filtered_by_status(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));

        $awaiting = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);

        $pending = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 1, 'task_type' => 'sales',
        ]);

        $reported = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->mine));

        $approved = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 2, 'product_type' => 'manure', 'task_type' => 'sales',
        ]);
        $this->withManureSales($this->mine, 2026, 2);
        $approved->markAsApproved($this->monthlyTaskPccAdmin($this->agency));

        $returned = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 1, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMeatSales($this->mine, 2026, 1);
        $returned->markAsReturned($this->monthlyTaskPccAdmin($this->agency), 'Volume looks wrong.');

        $this->assertSame([$pending->id], $this->taskIdsFor(['status' => 'pending']));
        $this->assertSame([$approved->id], $this->taskIdsFor(['status' => 'completed']));
        $this->assertSame([$awaiting->id], $this->taskIdsFor(['status' => 'under_review']));
        $this->assertSame([$returned->id], $this->taskIdsFor(['status' => 'returned']));
        $this->assertSame([$reported->id], $this->taskIdsFor(['status' => 'no_data']));
    }

    public function test_it_can_be_filtered_by_program(): void
    {
        $cbed = $this->monthlyTask($this->mine, [
            'year' => 2026, 'month' => 1, 'program' => 'cbed',
        ]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'program' => 'ih']);

        $this->assertSame([$cbed->id], $this->taskIdsFor(['program' => 'cbed']));
    }

    public function test_an_unknown_program_filter_is_ignored_rather_than_compared(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame([$task->id], $this->taskIdsFor(['program' => 'blockr']));
    }

    public function test_it_can_be_filtered_to_overdue_tasks_only(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));

        $late = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2, 'product_type' => 'meat']);

        $lateButFilled = $this->monthlyTask($this->mine, [
            'year' => 2025, 'month' => 12, 'product_type' => 'meat', 'task_type' => 'sales',
        ]);
        $this->withMeatSales($this->mine, 2025, 12);
        $this->assertSame('under_review', $lateButFilled->fresh()->status()->value);

        $reported = $this->monthlyTask($this->mine, [
            'year' => 2025, 'month' => 11, 'product_type' => 'manure', 'task_type' => 'production',
        ]);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->mine));

        $this->assertSame([$late->id], $this->taskIdsFor(['overdue' => '1']));
    }

    public function test_it_can_be_searched_by_center_name_or_code(): void
    {
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);
        $mine = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame([$mine->id], $this->taskIdsFor(['search' => 'clsu']));
    }

    public function test_an_unknown_product_filter_is_ignored_rather_than_compared(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame([$task->id], $this->taskIdsFor(['product_type' => 'goat milk']));
    }

    public function test_an_out_of_range_month_is_ignored_rather_than_compared(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame([$task->id], $this->taskIdsFor(['year' => 2026, 'month' => 99]));
    }

    public function test_a_non_numeric_year_is_ignored_rather_than_compared(): void
    {
        $task = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame([$task->id], $this->taskIdsFor(['year' => 'this year']));
    }

    public function test_the_listing_is_ordered_newest_period_first(): void
    {
        $old = $this->monthlyTask($this->mine, ['year' => 2025, 'month' => 12]);
        $new = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2]);
        $middle = $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $this->assertSame(
            [$new->id, $middle->id, $old->id],
            $this->taskIdsFor([]),
        );
    }

    public function test_the_listing_carries_the_derived_fields_the_page_renders(): void
    {
        $this->travelTo(now()->parse('2026-02-08'));
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);

        $task = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props']['tasks']['data'][0];

        $this->assertSame('2026-02-07', $task['due_date']);
        $this->assertSame('January 2026', $task['period']);
        $this->assertSame('overdue', $task['status']);
        $this->assertSame('Overdue', $task['status_label']);
        $this->assertFalse($task['is_resolved']);
        $this->assertFalse($task['has_entered_data']);
    }

    public function test_the_page_offers_the_filter_options_for_the_products_and_types(): void
    {
        $props = $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(
            ['milk', 'meat', 'manure'],
            array_column($props['filters']['products'], 'value'),
        );
        $this->assertSame(
            ['production', 'sales'],
            array_column($props['filters']['task_types'], 'value'),
        );
    }

    public function test_the_status_filter_offers_returned_for_correction(): void
    {
        $options = $this->propsFor([])['filters']['statuses'];

        $this->assertContains(
            ['value' => 'returned', 'label' => 'Returned for correction'],
            $options,
        );
    }

    public function test_a_center_scoped_viewer_is_only_offered_its_own_center_to_filter_by(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $props = $this->actingAs($this->monthlyTaskCenterAdmin($this->mine))
            ->get(route('monthly-tasks.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame([$this->mine->id], array_column($props['centers'], 'id'));
    }

    public function test_the_summary_follows_the_period_filter(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 2]);

        $this->assertSame(2, $this->summaryFor(['year' => 2026])['total']);
        $this->assertSame(1, $this->summaryFor(['year' => 2026, 'month' => 2])['total']);
    }

    public function test_the_summary_follows_the_center_filter(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->monthlyTask($this->theirs, ['year' => 2026, 'month' => 1]);

        $this->assertSame(2, $this->summaryFor(['year' => 2026])['total']);
        $this->assertSame(1, $this->summaryFor(['year' => 2026, 'center_id' => $this->theirs->id])['total']);
    }

    public function test_the_summary_follows_the_program_filter(): void
    {
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'program' => 'cbed']);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'program' => 'ih']);

        $this->assertSame(1, $this->summaryFor(['year' => 2026, 'program' => 'cbed'])['total']);
    }

    public function test_the_summary_ignores_the_status_filter_so_the_cards_remain_a_breakdown(): void
    {
        $this->travelTo(now()->parse('2026-02-01'));

        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->mine, 2026, 1);
        $this->monthlyTask($this->mine, ['year' => 2026, 'month' => 1, 'task_type' => 'sales']);

        $this->assertSame(2, $this->summaryFor(['year' => 2026, 'status' => 'under_review'])['total']);
    }

    public function test_the_year_filter_does_not_offer_years_in_the_future(): void
    {
        $years = $this->propsFor([])['filters']['years'];

        $this->assertContains((int) now()->year, $years);
        $this->assertNotContains((int) now()->year + 1, $years);
        $this->assertNotContains((int) now()->year + 2, $years);
    }

    private function taskIdsFor(array $filters): array
    {
        return array_column($this->propsFor($filters)['tasks']['data'], 'id');
    }

    private function summaryFor(array $filters): array
    {
        return $this->propsFor($filters)['summary'];
    }

    private function propsFor(array $filters): array
    {
        return $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->get(route('monthly-tasks.index', ['filter' => $filters]))
            ->assertOk()
            ->viewData('page')['props'];
    }
}

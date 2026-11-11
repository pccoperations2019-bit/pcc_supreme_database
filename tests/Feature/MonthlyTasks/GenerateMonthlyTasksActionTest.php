<?php

namespace Tests\Feature\MonthlyTasks;

use App\Actions\MonthlyTask\GenerateMonthlyTasksAction;
use App\Models\Agency;
use App\Models\Center;
use App\Models\MonthlyTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyTasksActionTest extends TestCase
{
    use InteractsWithMonthlyTasks;
    use RefreshDatabase;

    private Agency $agency;

    private Center $first;

    private Center $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = $this->agency();
        $this->first = $this->center($this->agency, 'CLSU');
        $this->second = $this->center($this->agency, 'MMSU');
    }

    public function test_it_creates_a_task_per_program_product_and_type_per_center(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3])
            ->assertRedirect(route('monthly-tasks.index'));

        $this->assertSame(24, $this->monthlyTaskCount(2026, 3));
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.generate'))
            ->assertRedirect(route('monthly-tasks.index'));

        $this->assertSame(24, $this->monthlyTaskCount((int) now()->year, (int) now()->month));
    }

    public function test_rerunning_it_creates_nothing_a_second_time(): void
    {
        $admin = $this->actingAs($this->monthlyTaskPccAdmin($this->agency));

        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);
        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);

        $this->assertSame(24, $this->monthlyTaskCount(2026, 3));
    }

    public function test_rerunning_it_does_not_reset_a_no_data_report(): void
    {
        $admin = $this->actingAs($this->monthlyTaskPccAdmin($this->agency));

        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);

        $reported = $this->firstTask(2026, 3);
        $reported->markAsNoData($this->monthlyTaskCenterAdmin($this->first));

        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);

        $this->assertTrue($reported->refresh()->is_no_data);
        $this->assertNotNull($reported->no_data_by);
    }

    public function test_it_raises_both_programs_for_a_center(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);

        $programs = MonthlyTask::query()->forMonth(2026, 3)->distinct()->pluck('program')->map(fn ($p) => $p->value)->all();

        $this->assertEqualsCanonicalizing(['ih', 'cbed'], $programs);
    }

    public function test_it_raises_the_month_a_center_is_missing_without_touching_the_others(): void
    {
        $admin = $this->actingAs($this->monthlyTaskPccAdmin($this->agency));

        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3]);

        $this->monthlyTask($this->second, ['year' => 2026, 'month' => 4]);

        $admin->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 4]);

        $this->assertSame(12, $this->monthlyTaskCount(2026, 4, $this->first));
    }

    public function test_a_center_admin_cannot_generate_the_month(): void
    {
        $this->actingAs($this->monthlyTaskCenterAdmin($this->first))
            ->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 3])
            ->assertForbidden();

        $this->assertSame(0, $this->monthlyTaskCount(2026, 3));
    }

    public function test_it_refuses_a_month_outside_the_calendar(): void
    {
        $this->actingAs($this->monthlyTaskPccAdmin($this->agency))
            ->post(route('monthly-tasks.generate'), ['year' => 2026, 'month' => 13])
            ->assertSessionHasErrors('month');

        $this->assertSame(0, $this->monthlyTaskCount(2026, 13));
    }

    public function test_a_center_scoped_run_only_covers_its_own_center(): void
    {
        $this->actingAs($this->monthlyTaskCenterAdmin($this->first));

        $result = app(GenerateMonthlyTasksAction::class)->execute(2026, 5);

        $this->assertSame(12, $result['created']);
        $this->assertSame(12, $this->monthlyTaskCount(2026, 5, $this->first));
        $this->assertSame(0, $this->monthlyTaskCount(2026, 5, $this->second));
    }

    private function monthlyTaskCount(int $year, int $month, ?Center $center = null): int
    {
        return MonthlyTask::query()
            ->when($center, fn ($q) => $q->forCenter($center->id))
            ->forMonth($year, $month)
            ->count();
    }

    private function firstTask(int $year, int $month): MonthlyTask
    {
        return MonthlyTask::query()->forMonth($year, $month)->firstOrFail();
    }
}

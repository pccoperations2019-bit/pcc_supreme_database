<?php

namespace Tests\Feature\MonthlyTasks;

use App\Models\Agency;
use App\Models\Center;
use App\Models\ManureSales;
use App\Models\MilkProduction;
use App\Support\MonthlyTaskPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTaskTotalVolumeTest extends TestCase
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

    public function test_a_task_without_figures_has_no_total(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->assertNull($task->totalVolume());
        $this->assertSame('—', MonthlyTaskPresenter::describe($task)['total_formatted']);
    }

    public function test_a_production_task_totals_the_produced_volume(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        MilkProduction::query()->create([
            'center_id' => $this->center->id,
            'program' => 'ih',
            'year' => 2026,
            'month' => 1,
            'total_volume_produced' => 420.5,
        ]);

        $this->assertSame(420.5, $task->fresh()->totalVolume());
    }

    public function test_a_sales_task_totals_the_sales_volume(): void
    {
        $task = $this->monthlyTask($this->center, [
            'product_type' => 'manure',
            'task_type' => 'sales',
            'year' => 2026,
            'month' => 1,
        ]);

        ManureSales::query()->create([
            'center_id' => $this->center->id,
            'program' => 'ih',
            'year' => 2026,
            'month' => 1,
            'total_sales' => 1200.5,
        ]);

        $fresh = $task->fresh();

        $this->assertSame(1200.5, $fresh->totalVolume());
        $this->assertSame('1,200.50', MonthlyTaskPresenter::describe($fresh)['total_formatted']);
    }

    public function test_a_task_ignores_figures_from_another_period(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        ManureSales::query()->create([
            'center_id' => $this->center->id,
            'program' => 'ih',
            'year' => 2026,
            'month' => 1,
            'total_sales' => 100,
        ]);

        $this->assertSame(100.0, $task->fresh()->totalVolume());
    }
}

<?php

namespace Tests\Feature\MonthlyTasks;

use App\Models\Center;
use App\Models\Farmer;
use App\Models\ManureSales;
use App\Models\MilkProduction;
use App\Models\Partner;
use App\Support\MonthlyTaskPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The review screen has to name who entered each figure and, for CBED, which
 * partner or farmer the entry belongs to. The encoder comes from the audit
 * trail, which stores the production model under its morph alias rather than
 * its class name - querying the class name silently returned nobody.
 */
class MonthlyTaskReviewDataTest extends TestCase
{
    use InteractsWithMonthlyTasks;
    use RefreshDatabase;

    private Center $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->center = $this->center($this->agency());
    }

    public function test_the_encoder_is_read_from_the_audit_trail(): void
    {
        $encoder = $this->monthlyTaskCenterAdmin($this->center);

        $this->actingAs($encoder);
        $this->withMilkProduction($this->center, 2026, 1);

        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $this->assertTrue($task->encoder()->is($encoder));
        $this->assertSame($encoder->name, MonthlyTaskPresenter::describe($task)['encoder_name']);
    }

    public function test_cbed_rows_carry_the_partner_farmer_and_encoder(): void
    {
        $encoder = $this->monthlyTaskCenterAdmin($this->center);
        $partner = $this->partner($this->center, 'Acme Dairy Coop');
        $farmer = $this->farmer($this->center, 'Juan', 'Dela Cruz');

        $this->actingAs($encoder);
        MilkProduction::query()->create([
            'center_id' => $this->center->id,
            'program' => 'cbed',
            'year' => 2026,
            'month' => 1,
            'partner_id' => $partner->id,
            'farmer_id' => $farmer->id,
            'total_volume_produced' => 723,
        ]);

        $task = $this->monthlyTask($this->center, [
            'program' => 'cbed',
            'year' => 2026,
            'month' => 1,
        ]);

        $row = MonthlyTaskPresenter::describe($task)['production_data_rows'][0];

        $this->assertSame('Acme Dairy Coop', $row['partner_name']);
        $this->assertSame('Juan Dela Cruz', $row['farmer_name']);
        $this->assertSame($encoder->name, $row['encoder_name']);
        $this->assertSame(723.0, $row['volume']);
    }

    public function test_a_sales_row_reports_the_sales_measure_rather_than_a_guessed_column(): void
    {
        $encoder = $this->monthlyTaskCenterAdmin($this->center);
        $partner = $this->partner($this->center, 'Abaca Smile Dairy Association');

        $this->actingAs($encoder);
        ManureSales::query()->create([
            'center_id' => $this->center->id,
            'program' => 'cbed',
            'year' => 2026,
            'month' => 10,
            'partner_id' => $partner->id,
            'total_sales' => 23233,
        ]);

        $task = $this->monthlyTask($this->center, [
            'program' => 'cbed',
            'product_type' => 'manure',
            'task_type' => 'sales',
            'year' => 2026,
            'month' => 10,
        ]);

        $described = MonthlyTaskPresenter::describe($task);

        $this->assertSame(23233.0, $described['production_data_rows'][0]['volume']);
        $this->assertSame(23233.0, $described['total_volume']);
    }

    public function test_ih_rows_do_not_carry_partner_or_farmer_metadata(): void
    {
        $partner = $this->partner($this->center, 'Acme Dairy Coop');
        $farmer = $this->farmer($this->center, 'Juan', 'Dela Cruz');

        MilkProduction::query()->create([
            'center_id' => $this->center->id,
            'program' => 'ih',
            'year' => 2026,
            'month' => 1,
            'partner_id' => $partner->id,
            'farmer_id' => $farmer->id,
            'total_volume_produced' => 100,
        ]);

        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        $row = MonthlyTaskPresenter::describe($task)['production_data_rows'][0];

        $this->assertArrayNotHasKey('partner_name', $row);
        $this->assertArrayNotHasKey('farmer_name', $row);
    }

    public function test_the_presenter_exposes_the_review_note_only_while_approved(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->center->agency);

        $this->withMilkProduction($this->center, 2026, 1);

        $this->assertNull(MonthlyTaskPresenter::describe($task)['review_note']);

        $task->markAsApproved($reviewer, 'Ties out against the delivery receipts.');

        $this->assertSame(
            'Ties out against the delivery receipts.',
            MonthlyTaskPresenter::describe($task->fresh())['review_note']
        );
    }

    public function test_the_presenter_reports_who_reviewed_the_task_and_when(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $reviewer = $this->monthlyTaskPccAdmin($this->center->agency);

        $this->withMilkProduction($this->center, 2026, 1);

        $this->assertNull(MonthlyTaskPresenter::describe($task)['reviewed_by_name']);

        $task->markAsApproved($reviewer);

        $described = MonthlyTaskPresenter::describe($task->fresh());

        $this->assertSame($reviewer->name, $described['reviewed_by_name']);
        $this->assertNotNull($described['reviewed_by_role']);
        $this->assertNotNull($described['reviewed_at']);
    }

    public function test_the_production_rows_are_read_once_per_instance(): void
    {
        $this->withMilkProduction($this->center, 2026, 1);

        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);

        DB::enableQueryLog();
        $task->productionData();
        $afterFirstRead = count(DB::getQueryLog());

        $task->productionData();
        $task->totalVolume();
        $task->hasEnteredData();

        $this->assertSame(1, $afterFirstRead);
        $this->assertCount($afterFirstRead, DB::getQueryLog());
    }

    public function test_refreshing_a_task_forgets_the_cached_rows(): void
    {
        $task = $this->monthlyTask($this->center, ['year' => 2026, 'month' => 1]);
        $this->withMilkProduction($this->center, 2026, 1);

        $this->assertSame(1, $task->productionData()->count());

        $this->withMilkProduction($this->center, 2026, 1);
        $this->assertSame(1, $task->productionData()->count());

        $task->refresh();

        $this->assertSame(2, $task->productionData()->count());
    }

    private function partner(Center $center, string $name): Partner
    {
        return Partner::query()->create([
            'center_id' => $center->id,
            'name' => $name,
        ]);
    }

    private function farmer(Center $center, string $firstName, string $lastName): Farmer
    {
        return Farmer::query()->create([
            'code' => 'F-'.$firstName.$lastName,
            'center_id' => $center->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
}

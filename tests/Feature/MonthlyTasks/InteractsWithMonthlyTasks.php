<?php

namespace Tests\Feature\MonthlyTasks;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\ManureSales;
use App\Models\MeatSales;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MonthlyTask;
use App\Models\User;
use Tests\Feature\Users\InteractsWithUsers;

trait InteractsWithMonthlyTasks
{
    use InteractsWithUsers;

    protected function monthlyTaskPccAdmin(?Agency $agency = null): User
    {
        return $this->userWithRole(UserRoleEnum::AGENCY_ADMIN, $agency ?? $this->agency(), null);
    }

    protected function monthlyTaskCenterAdmin(Center $center): User
    {
        return $this->userWithRole(UserRoleEnum::CENTER_ADMIN, $center->agency, $center);
    }

    protected function monthlyTask(Center $center, array $overrides = []): MonthlyTask
    {
        $task = MonthlyTask::create(array_merge([
            'center_id' => $center->id,
            'program' => 'ih',
            'product_type' => 'milk',
            'task_type' => 'production',
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ], $overrides));

        return $task->refresh();
    }

    protected function withMilkProduction(Center $center, int $year, int $month, string $program = 'ih'): MilkProduction
    {
        return MilkProduction::query()->create([
            'center_id' => $center->id,
            'program' => $program,
            'year' => $year,
            'month' => $month,
        ]);
    }

    protected function withMeatSales(Center $center, int $year, int $month, string $program = 'ih'): MeatSales
    {
        return MeatSales::query()->create([
            'center_id' => $center->id,
            'program' => $program,
            'year' => $year,
            'month' => $month,
        ]);
    }

    protected function withMilkSale(Center $center, int $year, int $month, string $program = 'ih'): MilkSale
    {
        return MilkSale::query()->create([
            'center_id' => $center->id,
            'program' => $program,
            'year' => $year,
            'month' => $month,
        ]);
    }

    protected function withManureSales(Center $center, int $year, int $month, string $program = 'ih'): ManureSales
    {
        return ManureSales::query()->create([
            'center_id' => $center->id,
            'program' => $program,
            'year' => $year,
            'month' => $month,
        ]);
    }
}

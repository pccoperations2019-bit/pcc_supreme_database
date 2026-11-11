<?php

namespace App\Actions\MonthlyTask;

use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use App\Models\MonthlyTask;
use App\Support\Centers;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyTasksAction
{
    public function execute(?int $year = null, ?int $month = null): array
    {
        $now = now();

        $year ??= (int) $now->format('Y');
        $month ??= (int) $now->format('n');

        $centers = $this->centersToBill();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($centers, $year, $month, &$created, &$skipped) {
            $covered = MonthlyTask::query()
                ->forMonth($year, $month)
                ->get(['center_id', 'program'])
                ->mapWithKeys(fn (MonthlyTask $task) => [$task->center_id.'|'.$task->program->value => true]);

            $rows = [];

            foreach ($centers as $center) {
                foreach (ProgramEnum::cases() as $program) {
                    $key = $center->getKey().'|'.$program->value;

                    if ($covered->has($key)) {
                        $skipped++;

                        continue;
                    }

                    foreach (MonthlyTaskProductEnum::cases() as $product) {
                        foreach (MonthlyTaskTypeEnum::cases() as $taskType) {
                            $rows[] = [
                                'center_id' => $center->getKey(),
                                'program' => $program->value,
                                'product_type' => $product->value,
                                'task_type' => $taskType->value,
                                'year' => $year,
                                'month' => $month,
                                'is_completed' => false,
                                'is_no_data' => false,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $created++;
                        }
                    }

                    $covered->put($key, true);
                }
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    MonthlyTask::query()->insertOrIgnore($chunk);
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'month' => ['year' => $year, 'month' => $month],
        ];
    }

    private function centersToBill(): Collection
    {
        $tenant = resolve(TenantContext::class);
        $user = $tenant->isCenterLevel() ? auth()->user() : null;

        return Center::query()
            ->excludingHidden(Centers::alwaysHiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $user && $user->isCenterScoped(),
                fn ($q) => $q->where('id', $user->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}

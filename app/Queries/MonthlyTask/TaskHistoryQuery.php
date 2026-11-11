<?php

namespace App\Queries\MonthlyTask;

use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskStatusEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Enums\ProgramEnum;
use App\Models\MonthlyTask;
use App\Models\User;
use App\Support\MonthlyTaskPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskHistoryQuery
{
    public function execute(Request $request, ?User $viewer = null): LengthAwarePaginator
    {
        $filters = $this->filtersFrom($request);

        return $this->query($filters, $viewer)
            ->with(['center:id,name,code', 'completedBy:id,name', 'reviewedBy:id,name,roles'])
            ->paginate($request->integer('per_page', 15))
            ->through(MonthlyTaskPresenter::describeList(...))
            ->withQueryString();
    }

    private function query(array $filters, ?User $viewer): Builder
    {
        $query = MonthlyTask::query()
            ->visibleTo($viewer)
            ->when(
                $filters['center_id'],
                fn (Builder $query) => $query->where('monthly_tasks.center_id', $filters['center_id'])
            )
            ->when(
                $filters['program'],
                fn (Builder $query) => $query->where('monthly_tasks.program', $filters['program'])
            )
            ->when(
                $filters['year'] && $filters['month'],
                fn (Builder $query) => $query->where('monthly_tasks.year', $filters['year'])
                    ->where('monthly_tasks.month', $filters['month'])
            )
            ->when(
                $filters['year'] && ! $filters['month'],
                fn (Builder $query) => $query->where('monthly_tasks.year', $filters['year'])
            )
            ->when(
                $filters['product_type'],
                fn (Builder $query) => $query->where('monthly_tasks.product_type', $filters['product_type'])
            )
            ->when(
                $filters['task_type'],
                fn (Builder $query) => $query->where('monthly_tasks.task_type', $filters['task_type'])
            )
            ->when($filters['search'], fn (Builder $query) => $query->where(function (Builder $query) use ($filters) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';

                $query->whereHas('center', fn (Builder $center) => $center
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                );
            }))
            ->orderByDesc('monthly_tasks.year')
            ->orderByDesc('monthly_tasks.month')
            ->orderBy('monthly_tasks.center_id')
            ->orderBy('monthly_tasks.program')
            ->orderBy('monthly_tasks.product_type')
            ->orderBy('monthly_tasks.task_type');

        $derivedStatus = $filters['status'] ?? ($filters['overdue'] === true ? 'overdue' : null);

        if ($derivedStatus !== null) {
            $year = $filters['year'];
            $month = $filters['month'];

            $joinArgs = ($year !== null && $month !== null)
                ? [$year, $month, $year, $month]
                : [];

            $query = SubmittedFigures::whereStatus(SubmittedFigures::join($query, ...$joinArgs), $derivedStatus);

            $query->select('monthly_tasks.*');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFrom(Request $request): array
    {
        $filter = fn (string $key) => $request->input('filter.'.$key);

        $year = $filter('year');
        $month = $filter('month');
        $centerId = $filter('center_id');
        $search = $filter('search');

        return [
            'center_id' => is_numeric($centerId) ? (int) $centerId : null,
            'program' => $this->oneOf($filter('program'), ProgramEnum::values()),
            'year' => is_numeric($year) ? (int) $year : null,
            'month' => is_numeric($month) && $month >= 1 && $month <= 12 ? (int) $month : null,
            'product_type' => $this->oneOf($filter('product_type'), MonthlyTaskProductEnum::values()),
            'task_type' => $this->oneOf($filter('task_type'), MonthlyTaskTypeEnum::values()),
            'status' => $this->oneOf($filter('status'), MonthlyTaskStatusEnum::values()),
            'overdue' => $filter('overdue') === '1' || $filter('overdue') === 'true' ? true : null,
            'search' => is_string($search) && trim($search) !== '' ? trim($search) : null,
        ];
    }

    private function oneOf(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}

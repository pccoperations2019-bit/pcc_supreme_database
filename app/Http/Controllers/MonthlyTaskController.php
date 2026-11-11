<?php

namespace App\Http\Controllers;

use App\Actions\MonthlyTask\GenerateMonthlyTasksAction;
use App\Actions\MonthlyTask\MarkTaskCompleteAction;
use App\Actions\MonthlyTask\ReviewTaskAction;
use App\Actions\MonthlyTask\SendTaskReminderAction;
use App\Enums\MonthEnum;
use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskStatusEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\StoreMonthlyTaskRequest;
use App\Http\Requests\UpdateMonthlyTaskRequest;
use App\Models\Center;
use App\Models\MonthlyTask;
use App\Models\User;
use App\Queries\MonthlyTask\ProductionSalesTotalsQuery;
use App\Queries\MonthlyTask\TaskCompletionRateQuery;
use App\Queries\MonthlyTask\TaskHistoryQuery;
use App\Support\Centers;
use App\Support\MonthlyTaskPresenter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class MonthlyTaskController extends Controller
{
    public function __construct(
        private readonly TaskHistoryQuery $taskHistory,
        private readonly TaskCompletionRateQuery $completionRates,
        private readonly ProductionSalesTotalsQuery $totals,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $this->authorize('viewAny', MonthlyTask::class);

        $tasks = $this->taskHistory->execute($request, $viewer);

        return Inertia::render('MonthlyTasks/Index', [
            'tasks' => $tasks,
            'centers' => $this->centerOptions($viewer),
            'filters' => $this->filterOptions($viewer),
            'summary' => $this->completionRates->summaryFor($this->taskHistory->filtersFrom($request), $viewer),
            'entryRoutes' => $this->entryRouteMap(),
        ]);
    }

    public function show(Request $request, MonthlyTask $monthlyTask): Response
    {
        $this->authorize('view', $monthlyTask);

        $monthlyTask->load(['center', 'completedBy', 'reviewedBy']);

        return Inertia::render('MonthlyTasks/Show', [
            'monthlyTask' => MonthlyTaskPresenter::describe($monthlyTask),
            'centers' => $this->centerOptions($request->user()),
            'enums' => $this->enums($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', MonthlyTask::class);

        $viewer = $request->user();

        return Inertia::render('MonthlyTasks/Create', [
            'monthlyTask' => $this->blankTask(),
            'centers' => $this->centerOptions($viewer),
            'enums' => $this->enums($viewer),
        ]);
    }

    public function edit(Request $request, MonthlyTask $monthlyTask): Response
    {
        $this->authorize('update', $monthlyTask);

        $monthlyTask->load(['center', 'completedBy', 'reviewedBy']);

        $viewer = $request->user();

        return Inertia::render('MonthlyTasks/Edit', [
            'monthlyTask' => MonthlyTaskPresenter::describe($monthlyTask),
            'centers' => $this->centerOptions($viewer),
            'enums' => $this->enums($viewer),
        ]);
    }

    public function store(StoreMonthlyTaskRequest $request): RedirectResponse
    {
        $this->authorize('create', MonthlyTask::class);

        $task = MonthlyTask::create($request->validated());

        return to_route('monthly-tasks.show', $task)
            ->with('success', 'Monthly task created.');
    }

    public function update(
        UpdateMonthlyTaskRequest $request,
        MonthlyTask $monthlyTask,
        MarkTaskCompleteAction $markTaskComplete,
    ): RedirectResponse {
        $this->authorize('update', $monthlyTask);

        $validated = $request->validated();

        if (array_key_exists('notes', $validated)) {
            $monthlyTask->update(['notes' => $validated['notes']]);
        }

        if (array_key_exists('no_data_reported', $validated)) {
            $monthlyTask = $markTaskComplete->execute(
                $monthlyTask,
                (bool) $validated['no_data_reported'],
                $request->user(),
            );
        }

        return to_route('monthly-tasks.show', $monthlyTask)
            ->with('success', $this->reportMessage($monthlyTask));
    }

    public function toggleStatus(
        Request $request,
        MonthlyTask $monthlyTask,
        MarkTaskCompleteAction $markTaskComplete,
    ): RedirectResponse {
        $this->authorize('update', $monthlyTask);

        $task = $markTaskComplete->execute(
            $monthlyTask,
            $request->boolean('no_data_reported'),
            $request->user(),
        );

        return to_route('monthly-tasks.index')->with('success', $this->reportMessage($task));
    }

    private function reportMessage(MonthlyTask $task): string
    {
        return $task->is_no_data
            ? 'Recorded: nothing to report for this period.'
            : 'No-data report withdrawn.';
    }

    public function destroy(MonthlyTask $monthlyTask): RedirectResponse
    {
        $this->authorize('delete', $monthlyTask);

        $monthlyTask->delete();

        return to_route('monthly-tasks.index')
            ->with('success', 'Monthly task deleted.');
    }

    public function generateTasks(
        Request $request,
        GenerateMonthlyTasksAction $generateMonthlyTasks,
    ): RedirectResponse {
        $this->authorize('create', MonthlyTask::class);

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:'.(date('Y') + 1)],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $result = $generateMonthlyTasks->execute(
            $validated['year'] ?? null,
            $validated['month'] ?? null,
        );

        $period = MonthEnum::from($result['month']['month'])->label().' '.$result['month']['year'];

        return to_route('monthly-tasks.index')->with(
            'success',
            $result['created'] > 0
                ? "Generated {$result['created']} tasks for {$period}."
                : "Tasks for {$period} already exist; nothing was generated.",
        );
    }

    public function remind(
        Request $request,
        SendTaskReminderAction $sendTaskReminder,
    ): JsonResponse {
        $viewer = $request->user();

        $this->authorize('review', MonthlyTask::class);

        $validated = $request->validate([
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        $reminders = $sendTaskReminder->execute($validated['center_id'] ?? null, $viewer);

        return response()->json([
            'centers' => $reminders,
            'center_count' => $reminders->count(),
            'recipient_count' => $reminders->sum(fn (array $entry) => $entry['recipients']->count()),
            'overdue_count' => $reminders->sum(fn (array $entry) => $entry['overdue_count']),
        ]);
    }

    public function completionRateReport(Request $request): Response|RedirectResponse|JsonResponse
    {
        $viewer = $request->user();

        $this->authorize('viewReports', MonthlyTask::class);

        $validated = $request->validate([
            'from_year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'from_month' => ['nullable', 'integer', 'between:1,12'],
            'to_year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'to_month' => ['nullable', 'integer', 'between:1,12'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        $fromYear = (int) ($validated['from_year'] ?? now()->year);
        $fromMonth = (int) ($validated['from_month'] ?? 1);
        $toYear = (int) ($validated['to_year'] ?? $fromYear);
        $toMonth = (int) ($validated['to_month'] ?? 12);

        if (($toYear * 100 + $toMonth) < ($fromYear * 100 + $fromMonth)) {
            return back()->withErrors(['to_month' => 'The end of the range must not be before its start.']);
        }

        $centerId = $validated['center_id'] ?? null;
        $rates = $this->completionRates->execute($fromYear, $fromMonth, $toYear, $toMonth, $centerId ? (int) $centerId : null, $viewer);

        $centers = Center::query()
            ->whereIn('id', $rates->pluck('center_id'))
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        return response()->json([
            'period' => [
                'from' => ['year' => $fromYear, 'month' => $fromMonth],
                'to' => ['year' => $toYear, 'month' => $toMonth],
            ],
            'by_center' => $rates->map(fn (array $rate) => [
                ...$rate,
                'center_name' => $centers[$rate['center_id']]->name ?? 'Center #'.$rate['center_id'],
                'center_code' => $centers[$rate['center_id']]->code ?? null,
            ])->values(),
            'by_product' => $this->completionRates->executeByProduct($fromYear, $fromMonth, $toYear, $toMonth, $viewer),
            'trend' => $this->completionRates->executeWithTrend($fromYear, $fromMonth, $toYear, $toMonth, $viewer)['trend'],
        ]);
    }

    public function totalsReport(Request $request): Response|JsonResponse
    {
        $viewer = $request->user();

        $this->authorize('viewAny', MonthlyTask::class);

        $validated = $request->validate([
            'from_year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'from_month' => ['nullable', 'integer', 'between:1,12'],
            'to_year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'to_month' => ['nullable', 'integer', 'between:1,12'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        $fromYear = (int) ($validated['from_year'] ?? now()->year);
        $fromMonth = (int) ($validated['from_month'] ?? 1);
        $toYear = (int) ($validated['to_year'] ?? $fromYear);
        $toMonth = (int) ($validated['to_month'] ?? 12);

        if (($toYear * 100 + $toMonth) < ($fromYear * 100 + $fromMonth)) {
            return response()->json([
                'message' => 'The end of the range must not be before its start.',
                'errors' => ['to_month' => ['The end of the range must not be before its start.']],
            ], 422);
        }

        $centerId = isset($validated['center_id']) ? (int) $validated['center_id'] : null;

        $byCenter = $this->totals->executeByCenter($fromYear, $fromMonth, $toYear, $toMonth, $centerId, $viewer);
        $byProduct = $this->totals->execute($fromYear, $fromMonth, $toYear, $toMonth, $centerId, $viewer);

        $centerIds = collect($byCenter)->pluck('center_id')->merge(
            collect($byProduct)->pluck('center_id')
        )->unique()->filter();

        $centers = Center::query()
            ->whereIn('id', $centerIds)
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $name = fn (?int $id) => $centers[$id]->name ?? 'Center #'.$id;

        if ($request->expectsJson()) {
            return response()->json([
                'period' => [
                    'from' => ['year' => $fromYear, 'month' => $fromMonth],
                    'to' => ['year' => $toYear, 'month' => $toMonth],
                ],
                'by_center' => collect($byCenter)
                    ->map(fn (array $row) => [...$row, 'center_name' => $name($row['center_id'])])
                    ->values(),
                'by_product' => collect($byProduct)
                    ->map(fn (array $row) => [...$row, 'center_name' => $name($row['center_id'])])
                    ->values(),
            ]);
        }

        return Inertia::render('MonthlyTasks/Totals', [
            'period' => [
                'from' => ['year' => $fromYear, 'month' => $fromMonth],
                'to' => ['year' => $toYear, 'month' => $toMonth],
            ],
            'years' => $this->availableYears($viewer),
            'byCenter' => collect($byCenter)
                ->map(fn (array $row) => [
                    ...$row,
                    'center_name' => $name($row['center_id']),
                    'center_code' => $centers[$row['center_id']]->code ?? null,
                    'program_label' => ProgramEnum::tryFrom($row['program'])?->label() ?? $row['program'],
                    'period_label' => MonthEnum::from((int) $row['month'])->label().' '.$row['year'],
                    'production_formatted' => number_format((float) $row['production_volume'], 2),
                    'sales_formatted' => number_format((float) $row['sales_volume'], 2),
                ])
                ->values(),
            'byProduct' => collect($byProduct)
                ->map(fn (array $row) => [
                    ...$row,
                    'center_name' => $name($row['center_id']),
                    'product_label' => MonthlyTaskProductEnum::tryFrom($row['product_type'])?->label() ?? $row['product_type'],
                    'task_type_label' => MonthlyTaskTypeEnum::tryFrom($row['task_type'])?->label() ?? $row['task_type'],
                    'program_label' => ProgramEnum::tryFrom($row['program'])?->label() ?? $row['program'],
                    'period_label' => MonthEnum::from((int) $row['month'])->label().' '.$row['year'],
                    'volume_formatted' => number_format((float) $row['volume'], 2),
                ])
                ->values(),
            'centers' => $this->centerOptions($viewer),
            'months' => MonthEnum::labels(),
            'totals' => [
                'production' => number_format(collect($byCenter)->sum('production_volume'), 2),
                'sales' => number_format(collect($byCenter)->sum('sales_volume'), 2),
                'rows' => collect($byProduct)->sum('row_count'),
            ],
        ]);
    }

    private function centerOptions(?User $viewer): Collection
    {
        $tenant = resolve(TenantContext::class);

        return Center::query()
            ->excludingHidden(Centers::alwaysHiddenCenters())
            ->when($tenant->agencyId(), fn ($q) => $q->where('agency_id', $tenant->agencyId()))
            ->when(
                $viewer && $viewer->isCenterScoped(),
                fn ($q) => $q->where('id', $viewer->center_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function filterOptions(?User $viewer): array
    {
        return [
            'products' => $this->options(MonthlyTaskProductEnum::values(), MonthlyTaskProductEnum::labels()),
            'task_types' => $this->options(MonthlyTaskTypeEnum::values(), MonthlyTaskTypeEnum::labels()),
            'months' => MonthEnum::labels(),
            'years' => $this->availableYears($viewer),
            'programs' => $this->options(ProgramEnum::values(), ProgramEnum::labels()),
            'statuses' => $this->options(MonthlyTaskStatusEnum::values(), MonthlyTaskStatusEnum::labels()),
        ];
    }

    private function enums(?User $viewer = null): array
    {
        return [
            'products' => $this->options(MonthlyTaskProductEnum::values(), MonthlyTaskProductEnum::labels()),
            'taskTypes' => $this->options(MonthlyTaskTypeEnum::values(), MonthlyTaskTypeEnum::labels()),
            'programs' => $this->options(ProgramEnum::values(), ProgramEnum::labels()),
            'statuses' => $this->options(MonthlyTaskStatusEnum::values(), MonthlyTaskStatusEnum::labels()),
            'months' => MonthEnum::labels(),
            'availableYears' => $this->availableYears($viewer),
        ];
    }

    private function blankTask(): array
    {
        return [
            'id' => null,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'program' => ProgramEnum::DEFAULT->value,
            'product_type' => MonthlyTaskProductEnum::MILK->value,
            'task_type' => MonthlyTaskTypeEnum::PRODUCTION->value,
            'is_completed' => false,
            'notes' => null,
        ];
    }

    private function availableYears(?User $viewer = null): array
    {
        $recorded = MonthlyTask::query()
            ->visibleTo($viewer)
            ->distinct()
            ->pluck('year')
            ->all();

        return collect(array_merge($recorded, [(int) now()->year]))
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->all();
    }

    private function options(array $values, array $labels): array
    {
        return array_map(
            fn (string $value) => ['value' => $value, 'label' => $labels[$value] ?? $value],
            $values,
        );
    }

    private function entryRouteMap(): array
    {
        return [
            'milk:production' => ['route' => 'milk-productions.create', 'label' => 'Open the milk production form', 'prefilled' => true],
            'milk:sales' => ['route' => 'milk-sales.create', 'label' => 'Open the milk sales form', 'prefilled' => true],
            'meat:production' => ['route' => 'meat-productions.create', 'label' => 'Open the meat production form', 'prefilled' => true],
            'meat:sales' => ['route' => 'meat-sales.create', 'label' => 'Open the meat sales form', 'prefilled' => true],
            'manure:production' => ['route' => 'manure-productions.create', 'label' => 'Open the manure production form', 'prefilled' => true],
            'manure:sales' => ['route' => 'manure-sales.create', 'label' => 'Open the manure sales form', 'prefilled' => true],
        ];
    }

    public function approve(
        Request $request,
        MonthlyTask $monthlyTask,
        ReviewTaskAction $reviewTask,
    ): RedirectResponse {
        $this->authorize('review', $monthlyTask);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $task = $reviewTask->approve($monthlyTask, $request->user(), $validated['note'] ?? null);

        return back()->with('success', "Approved the figures for {$task->period()}.");
    }

    public function returnFigures(
        Request $request,
        MonthlyTask $monthlyTask,
        ReviewTaskAction $reviewTask,
    ): RedirectResponse {
        $this->authorize('review', $monthlyTask);

        $validated = $request->validate([
            'return_reason' => ['required', 'string', 'max:1000'],
        ]);

        $task = $reviewTask->returnFigures($monthlyTask, $request->user(), $validated['return_reason']);

        return back()->with('success', "Returned the {$task->period()} figures to the center.");
    }
}

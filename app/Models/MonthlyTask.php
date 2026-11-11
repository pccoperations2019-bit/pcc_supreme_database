<?php

namespace App\Models;

use App\Enums\MonthEnum;
use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskReviewStatusEnum;
use App\Enums\MonthlyTaskStatusEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Enums\ProgramEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class MonthlyTask extends Model
{
    use LogsAuditTrail;
    use ScopedToCenter;

    public const GRACE_DAYS = 7;

    /**
     * The production/sales rows for this task's period, read once per instance.
     *
     * The review screen asks for the same rows from several angles (status,
     * encoder, totals, the entry list), and each accessor used to re-run the
     * query. `$productionDataLoaded` distinguishes "not looked up yet" from
     * "looked up and found nothing", which a nullable property alone cannot.
     */
    private bool $productionDataLoaded = false;

    private ?Collection $productionDataCache = null;

    protected $guarded = [
        'id',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function scopeForCenter(Builder $query, int $centerId): Builder
    {
        return $query->where('center_id', $centerId);
    }

    protected function scopeForProgram(Builder $query, string $program): Builder
    {
        return $query->where('program', $program);
    }

    protected function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }

    protected function scopeForProduct(Builder $query, string $productType): Builder
    {
        return $query->where('product_type', $productType);
    }

    protected function scopeForTaskType(Builder $query, string $taskType): Builder
    {
        return $query->where('task_type', $taskType);
    }

    /**
     * The period (year*100+month) a task must fall before to be overdue.
     *
     * A period is overdue once the grace window after its final day has
     * elapsed, so this is the SQL-side twin of {@see isPastDue()}: both count
     * GRACE_DAYS past the end of the task's month. Kept as a single threshold
     * so the reports and the per-row badge can never drift apart.
     */
    public static function overdueBeforePeriod(?Carbon $asOf = null): int
    {
        $reference = ($asOf ?? now())->subDays(self::GRACE_DAYS);

        return (int) $reference->format('Ym');
    }

    public function markAsNoData(?User $user = null): void
    {
        $this->forceFill([
            'is_no_data' => true,
            'no_data_at' => now(),
            'no_data_by' => $user?->getKey() ?? auth()->id(),
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ])->save();
    }

    public function clearNoDataReport(): void
    {
        $this->forceFill([
            'is_no_data' => false,
            'no_data_at' => null,
            'no_data_by' => null,
        ])->save();
    }

    public function clearCompletion(): void
    {
        $this->forceFill([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'review_status' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'return_reason' => null,
            'review_note' => null,
        ])->save();
    }

    public function markAsSubmitted(?User $user = null): void
    {
        $this->forceFill([
            'review_status' => MonthlyTaskReviewStatusEnum::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $user?->getKey() ?? auth()->id(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'return_reason' => null,
            'review_note' => null,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ])->save();
    }

    public function markAsApproved(?User $reviewer = null, ?string $note = null): void
    {
        $this->forceFill([
            'review_status' => MonthlyTaskReviewStatusEnum::APPROVED->value,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer?->getKey() ?? auth()->id(),
            'return_reason' => null,
            'review_note' => $note,
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $reviewer?->getKey() ?? auth()->id(),
        ])->save();
    }

    public function markAsReturned(?User $reviewer = null, string $reason = ''): void
    {
        $this->forceFill([
            'review_status' => MonthlyTaskReviewStatusEnum::RETURNED->value,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer?->getKey() ?? auth()->id(),
            'return_reason' => $reason,
            'review_note' => null,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ])->save();
    }

    public function reviewStatus(): ?MonthlyTaskReviewStatusEnum
    {
        $stored = is_string($this->review_status)
            ? MonthlyTaskReviewStatusEnum::tryFrom($this->review_status)
            : null;

        if ($stored === MonthlyTaskReviewStatusEnum::SUBMITTED && ! $this->hasEnteredData()) {
            return null;
        }

        if ($stored !== null) {
            return $stored;
        }

        return $this->hasEnteredData()
            ? MonthlyTaskReviewStatusEnum::SUBMITTED
            : null;
    }

    public function isAwaitingReview(): bool
    {
        return $this->reviewStatus()?->isAwaitingReview() ?? false;
    }

    public function isApproved(): bool
    {
        return $this->hasEnteredData() && ($this->reviewStatus()?->isResolved() ?? false);
    }

    public static function syncSubmissionFor(Model $record, string $productType, string $taskType): void
    {
        $task = static::query()
            ->where('center_id', $record->center_id)
            ->where('program', is_string($record->program) ? $record->program : $record->program->value)
            ->where('product_type', $productType)
            ->where('task_type', $taskType)
            ->where('year', $record->year)
            ->where('month', $record->month)
            ->lockForUpdate()
            ->first();

        if ($task && ($task->isAwaitingReview() || ! $record->wasRecentlyCreated && ! $record->wasChanged())) {
            return;
        }

        $task?->markAsSubmitted();
    }

    public static function syncCompletionAfterDelete(Model $record, string $productType, string $taskType): void
    {
        $task = static::query()
            ->where('center_id', $record->center_id)
            ->where('program', is_string($record->program) ? $record->program : $record->program->value)
            ->where('product_type', $productType)
            ->where('task_type', $taskType)
            ->where('year', $record->year)
            ->where('month', $record->month)
            ->first();

        if ($task && ! $task->hasEnteredData()) {
            $task->clearCompletion();
        }
    }

    public function dueDate(): Carbon
    {
        return Date::create($this->year, $this->month, 1)
            ->endOfMonth()
            ->addDays(self::GRACE_DAYS);
    }

    public function daysUntilDue(): int
    {
        return (int) today()->diffInDays($this->dueDate()->startOfDay(), false);
    }

    public function period(): string
    {
        return MonthEnum::from($this->month)->label().' '.$this->year;
    }

    public function status(): MonthlyTaskStatusEnum
    {
        if (! $this->hasEnteredData()) {
            if ($this->is_no_data) {
                return MonthlyTaskStatusEnum::NO_DATA;
            }

            return ($this->is_completed && ! $this->wasReviewed())
                ? MonthlyTaskStatusEnum::COMPLETED
                : ($this->isPastDue()
                    ? MonthlyTaskStatusEnum::OVERDUE
                    : MonthlyTaskStatusEnum::PENDING);
        }

        return $this->isApproved()
            ? MonthlyTaskStatusEnum::COMPLETED
            : MonthlyTaskStatusEnum::UNDER_REVIEW;
    }

    public function wasReviewed(): bool
    {
        return in_array(
            $this->review_status,
            [
                MonthlyTaskReviewStatusEnum::APPROVED->value,
                MonthlyTaskReviewStatusEnum::RETURNED->value,
            ],
            true,
        );
    }

    public function isResolved(): bool
    {
        return $this->status()->isResolved();
    }

    /**
     * Per-row twin of {@see overdueBeforePeriod()}; both apply the same
     * GRACE_DAYS window, one via the due date and one as a SQL threshold.
     */
    public function isPastDue(): bool
    {
        return now()->isAfter($this->dueDate());
    }

    public function hasEnteredData(): bool
    {
        return $this->productionData() instanceof Collection;
    }

    public function productionData(): ?Collection
    {
        if ($this->productionDataLoaded) {
            return $this->productionDataCache;
        }

        $this->productionDataLoaded = true;

        $product = is_string($this->product_type)
            ? ($this->product_type === '' ? null : MonthlyTaskProductEnum::tryFrom($this->product_type))
            : $this->product_type;
        $taskType = is_string($this->task_type) ? $this->task_type : $this->task_type->value;
        $program = is_string($this->program) ? $this->program : $this->program->value;

        if (! $product instanceof MonthlyTaskProductEnum) {
            Log::warning('productionData: invalid product', ['product_type' => $this->product_type]);

            return $this->productionDataCache = null;
        }

        $table = $product->taskTable($taskType);

        if ($table === null) {
            Log::warning('productionData: no table for product/task type', [
                'product' => $product->value,
                'task_type' => $taskType,
            ]);

            return $this->productionDataCache = null;
        }

        $rows = DB::table($table)
            ->where('center_id', $this->center_id)
            ->where('program', $program)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->get();

        return $this->productionDataCache = $rows->isEmpty() ? null : $rows;
    }

    /**
     * Reload the model and forget any rows cached on this instance.
     */
    public function refresh()
    {
        $this->productionDataLoaded = false;
        $this->productionDataCache = null;

        return parent::refresh();
    }

    public function totalVolume(): ?float
    {
        $data = $this->productionData();
        $measureColumn = $this->measureColumn();

        if (! $data instanceof Collection || $measureColumn === null) {
            return null;
        }

        return (float) $data->sum($measureColumn);
    }

    public function encoder(): ?User
    {
        $data = $this->productionData();

        if (! $data instanceof Collection || $data->isEmpty()) {
            return null;
        }

        // The activity log stores the morph alias ('milk_production'), not the
        // model class, so querying by the class name never matched and every
        // task read as entered by "—".
        $modelClass = $this->getProductionModelClass();
        $morphClass = (new $modelClass)->getMorphClass();

        $firstRowId = $data->first()->id;

        $activity = Activity::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $firstRowId)
            ->where('event', 'created')
            ->latest('created_at')
            ->first();

        if ($activity && $activity->causer) {
            return $activity->causer;
        }

        $activity = Activity::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $firstRowId)
            ->whereIn('event', ['created', 'updated'])
            ->latest('created_at')
            ->first();

        if ($activity && $activity->causer) {
            return $activity->causer;
        }

        return null;
    }

    public function isCbed(): bool
    {
        $program = is_string($this->program) ? $this->program : $this->program->value;

        return $program === ProgramEnum::CBED->value;
    }

    /**
     * The entries the review screen lists.
     *
     * A CBED row names the partner or farmer it belongs to and is logged by
     * whoever actually entered it, so for that program every row is decorated
     * with those names and the encoder read from the audit trail. Other
     * programs carry a single task-level encoder and no partner/farmer, so
     * their rows are returned untouched.
     */
    public function reviewProductionData(): Collection
    {
        $data = $this->productionData();

        if (! $data instanceof Collection) {
            return collect();
        }

        $rows = $data->map(fn ($row) => (array) $row)->values();

        $measureColumn = $this->measureColumn();

        if ($measureColumn !== null) {
            $rows = $rows->map(function (array $row) use ($measureColumn) {
                $row['volume'] = (float) ($row[$measureColumn] ?? 0);

                return $row;
            });
        }

        if (! $this->isCbed()) {
            return $rows;
        }

        $partnerNames = $this->partnerNamesFor($rows);
        $farmerNames = $this->farmerNamesFor($rows);
        $encoders = $this->encodersByRowId($rows->pluck('id')->filter()->all());

        return $rows->map(fn (array $row) => [
            ...$row,
            'partner_name' => filled($row['partner_id'] ?? null)
                ? ($partnerNames[(int) $row['partner_id']] ?? null)
                : null,
            'farmer_name' => filled($row['farmer_id'] ?? null)
                ? ($farmerNames[(int) $row['farmer_id']] ?? null)
                : null,
            'encoder_name' => filled($row['id'] ?? null)
                ? ($encoders[(int) $row['id']] ?? null)
                : null,
        ]);
    }

    /**
     * The name of whoever logged each production row, from the audit trail.
     *
     * @param  list<int|string>  $rowIds
     * @return array<int, string|null>
     */
    private function encodersByRowId(array $rowIds): array
    {
        if ($rowIds === []) {
            return [];
        }

        $modelClass = $this->getProductionModelClass();

        return Activity::query()
            ->where('subject_type', (new $modelClass)->getMorphClass())
            ->whereIn('subject_id', $rowIds)
            ->where('event', 'created')
            ->with('causer')
            ->latest('created_at')
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $activities) => $activities->first()->causer?->name)
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, string>
     */
    private function partnerNamesFor(Collection $rows): Collection
    {
        $ids = $rows->pluck('partner_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Partner::query()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, string>
     */
    private function farmerNamesFor(Collection $rows): Collection
    {
        $ids = $rows->pluck('farmer_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Farmer::query()
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (Farmer $farmer) => [
                $farmer->id => trim($farmer->first_name.' '.$farmer->last_name),
            ]);
    }

    private function measureColumn(): ?string
    {
        $product = $this->product_type instanceof MonthlyTaskProductEnum
            ? $this->product_type
            : MonthlyTaskProductEnum::tryFrom((string) $this->product_type);

        $taskType = is_string($this->task_type) ? $this->task_type : $this->task_type->value;

        return $product?->measureColumn($taskType);
    }

    private function getProductionModelClass(): string
    {
        $product = is_string($this->product_type) ? $this->product_type : $this->product_type->value;
        $taskType = is_string($this->task_type) ? $this->task_type : $this->task_type->value;

        return match ($product) {
            'milk' => $taskType === 'production' ? MilkProduction::class : MilkSale::class,
            'meat' => $taskType === 'production' ? MeatProduction::class : MeatSales::class,
            'manure' => $taskType === 'production' ? ManureProduction::class : ManureSales::class,
            default => throw new \InvalidArgumentException("Unknown product type: {$product}"),
        };
    }

    protected function casts(): array
    {
        return [
            'program' => ProgramEnum::class,
            'product_type' => MonthlyTaskProductEnum::class,
            'task_type' => MonthlyTaskTypeEnum::class,
            'year' => 'integer',
            'month' => 'integer',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'is_no_data' => 'boolean',
            'no_data_at' => 'datetime',
            'review_status' => 'string',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}

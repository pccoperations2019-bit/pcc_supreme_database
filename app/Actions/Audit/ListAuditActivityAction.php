<?php

namespace App\Actions\Audit;

use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\CenterSalesGoal;
use App\Models\DairyBox;
use App\Models\DairyBoxSale;
use App\Models\Document;
use App\Models\EpahpCapacityBuilding;
use App\Models\EpahpGatasangKalabaw;
use App\Models\Farmer;
use App\Models\ManureProduction;
use App\Models\ManureSales;
use App\Models\MeatProduction;
use App\Models\MeatSales;
use App\Models\MilkConsolidation;
use App\Models\MilkCost;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MilkTraded;
use App\Models\MilkUsage;
use App\Models\MilkUtilizationMonthlySummary;
use App\Models\MilkWastage;
use App\Models\MonthlyTask;
use App\Models\Partner;
use App\Models\Pl480;
use App\Models\ProjectSite;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Builds the system-wide audit listing.
 *
 * The activity log is the only audit store in Supreme CBED, so this reads it
 * directly rather than keeping a parallel audit table in step. Category and
 * action are derived from each row's subject morph type and wording (see
 * AuditCategoryEnum / AuditActionEnum), which means a workflow transition that
 * nobody declared here still shows up - it just filters under System until it is
 * given a subject type the enums recognise.
 */
class ListAuditActivityAction
{
    /**
     * Audited models the center filter can reach through their own `center_id`.
     *
     * @var list<class-string<Model>>
     */
    private const CENTER_SCOPED_MODELS = [
        Partner::class,
        Farmer::class,
        DairyBox::class,
        CenterProductionGoal::class,
        CenterSalesGoal::class,
        ProjectSite::class,
        MilkProduction::class,
        MilkSale::class,
        MilkTraded::class,
        MilkConsolidation::class,
        MeatProduction::class,
        MeatSales::class,
        ManureProduction::class,
        ManureSales::class,
        MonthlyTask::class,
        ServiceRecord::class,
        DairyBoxSale::class,
        MilkCost::class,
        MilkUsage::class,
        MilkWastage::class,
        Pl480::class,
        MilkUtilizationMonthlySummary::class,
        EpahpGatasangKalabaw::class,
        EpahpCapacityBuilding::class,
    ];

    /**
     * @var list<class-string<Model>>|null
     */
    private ?array $centerScopedModels = null;

    public function execute(): QueryBuilder
    {
        return $this->buildQuery();
    }

    public function executeForCenter(?Center $center): QueryBuilder
    {
        $query = $this->baseQuery();

        if (! $center instanceof Center) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(fn (Builder $scoped) => $this->scopeToCenter($scoped, $center));
        }

        return $this->buildQuery($query);
    }

    /**
     * Wrap the activity query in Spatie\QueryBuilder with this page's filter and
     * sort surface. The scope, if any, is applied to the Eloquent query first so
     * it survives into the built listing.
     */
    private function buildQuery(?Builder $query = null): QueryBuilder
    {
        return QueryBuilder::for($query ?? $this->baseQuery())
            ->allowedFilters($this->filters())
            ->allowedSorts(['created_at'])
            ->defaultSort('-created_at');
    }

    private function baseQuery(): Builder
    {
        // `subject` is a polymorphic relation, so eager loading a document
        // relation off it directly would ask every subject type for a relation
        // it does not have - the page then 500s as soon as a profile is logged.
        // `morphWith` attaches the document-only relations to Document alone,
        // and newly logged subject types simply load with no extras.
        return Activity::query()->with([
            'causer.roles',
            'causer.center',
            'subject' => fn (MorphTo $subject) => $subject->morphWith([
                Document::class => ['tags', 'file.uploadedBy.center'],
            ]),
        ]);
    }

    /**
     * The filter surface. `search` covers the description and the causer, which
     * are the two things a reviewer types when they are looking for "the thing
     * that happened yesterday".
     *
     * @return list<AllowedFilter>
     */
    private function filters(): array
    {
        return [
            AllowedFilter::callback('search', function (Builder $query, $value) {
                $term = trim((string) $value);

                if ($term === '') {
                    return;
                }

                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $query->where(function (Builder $query) use ($like) {
                    $query->where('description', 'like', $like)
                        ->orWhereHas('causer', fn (Builder $causer) => $causer->where('name', 'like', $like));
                });
            }),

            AllowedFilter::callback('category', function (Builder $query, $value) {
                $category = AuditCategoryEnum::tryFrom((string) $value);

                if ($category === null) {
                    return;
                }

                // System is the fallback for everything unrecognised, so it is
                // expressed as the absence of the other four categories rather
                // than as a fixed subject type list that new models would fall
                // out of.
                if ($category === AuditCategoryEnum::SYSTEM) {
                    $recognised = collect(AuditCategoryEnum::filterable())
                        ->reject(fn (string $name) => $name === AuditCategoryEnum::SYSTEM->value)
                        ->flatMap(fn (string $name) => AuditCategoryEnum::subjectTypes(AuditCategoryEnum::from($name)))
                        ->unique()
                        ->values();

                    $query->where(function (Builder $query) use ($recognised) {
                        $query->whereNull('subject_type')
                            ->orWhereNotIn('subject_type', $recognised);
                    });

                    return;
                }

                $types = AuditCategoryEnum::subjectTypes($category);

                if ($types === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('subject_type', $types);
            }),

            AllowedFilter::callback('action', function (Builder $query, $value) {
                $action = AuditActionEnum::tryFrom((string) $value);

                if ($action === null) {
                    return;
                }

                $events = $this->eventsForAction($action);

                if ($events === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('event', $events);
            }),

            AllowedFilter::callback('causer', function (Builder $query, $value) {
                $query->where('causer_type', (new User)->getMorphClass())
                    ->where('causer_id', (int) $value);
            }),

            AllowedFilter::callback('center', function (Builder $query, $value) {
                $center = Center::find((int) $value);

                if ($center === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $this->scopeToCenter($query, $center);
            }),

            AllowedFilter::callback('from', function (Builder $query, $value) {
                $query->where('created_at', '>=', $this->startOfDay($value));
            }),

            AllowedFilter::callback('to', function (Builder $query, $value) {
                $query->where('created_at', '<=', $this->endOfDay($value));
            }),
        ];
    }

    /**
     * A center filter has to consider every place a center can be implied: who
     * did it, whose records it was done to, and - for documents, which carry no
     * center_id of their own - which center the document is tagged with or was
     * uploaded for.
     *
     * `whereHasMorph` is what makes the mixed subject types work: `whereHas` on
     * a morphTo relation resolves against every morph type at once, which would
     * look for `center_id` on tables that do not have one.
     */
    private function scopeToCenter(Builder $query, Center $center): void
    {
        $query->where(function (Builder $query) use ($center) {
            $query->whereHas('causer', fn (Builder $causer) => $causer->where('center_id', $center->id))
                ->orWhereHasMorph(
                    'subject',
                    $this->centerScopedModels(),
                    fn (Builder $subject) => $subject->where('center_id', $center->id),
                )
                ->orWhere(function (Builder $query) use ($center) {
                    // A center is its own center, and Center keys it on `id`
                    // rather than `center_id`, so it cannot go through the
                    // clause above.
                    $query->where('subject_type', 'center')
                        ->where('subject_id', $center->id);
                })
                ->orWhereHasMorph(
                    'subject',
                    [Document::class],
                    fn (Builder $document) => $document
                        ->whereHas('tags', fn (Builder $tags) => $tags->where('key', 'center')->where('value', $center->code))
                        ->orWhereHas('file.uploadedBy', fn (Builder $uploader) => $uploader->where('center_id', $center->id)),
                );
        });
    }

    /**
     * The models a center filter can actually query.
     *
     * The data models are not uniform: a milk cost, a usage figure and a wastage
     * figure hang off the production row they belong to rather than off a center,
     * so their tables have no `center_id`. `whereHasMorph` against a table that
     * lacks the column is a SQL error rather than an empty result, so the list is
     * filtered against the schema once per request.
     *
     * A row of that kind is still reviewable - it sits under the production entry
     * it belongs to - it just does not answer the center filter on its own.
     *
     * @return list<class-string<Model>>
     */
    private function centerScopedModels(): array
    {
        return $this->centerScopedModels ??= array_values(array_filter(
            self::CENTER_SCOPED_MODELS,
            fn (string $model) => Schema::hasColumn((new $model)->getTable(), 'center_id'),
        ));
    }

    /**
     * The event slugs already in the log that resolve to an action.
     *
     * Reading them from the table rather than from a constant list is what makes
     * the filter self-maintaining: a workflow that starts logging a new
     * transition is filterable as soon as the row exists, with no code change
     * and no risk of the filter silently disagreeing with the table.
     *
     * @return list<string>
     */
    private function eventsForAction(AuditActionEnum $action): array
    {
        return Activity::query()
            ->select('event')
            ->distinct()
            ->pluck('event')
            ->filter(fn (?string $event) => $event !== null)
            ->filter(fn (string $event) => AuditActionEnum::resolve(null, $event) === $action)
            ->values()
            ->all();
    }

    private function startOfDay(string $value): string
    {
        return now()->parse($value)->startOfDay()->toDateTimeString();
    }

    private function endOfDay(string $value): string
    {
        return now()->parse($value)->endOfDay()->toDateTimeString();
    }

    /**
     * Users who can be picked in the "User" filter. Only users who can already
     * sign in are listed - an activity from a deleted account should not offer
     * that account as a filter target, it just falls under "System".
     *
     * A center-scoped viewer only gets the accounts inside its own center, so the
     * filter cannot be used to discover who works elsewhere.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function userOptions(?Center $center = null): Collection
    {
        return User::query()
            ->when(
                $center instanceof Center,
                fn (Builder $query) => $query->where('center_id', $center->id)
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'label' => $user->name]);
    }
}

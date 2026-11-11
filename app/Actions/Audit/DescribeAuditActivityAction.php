<?php

namespace App\Actions\Audit;

use App\Actions\Document\DescribeDocumentAction;
use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Enums\DocumentStatusEnum;
use App\Enums\HideProductTypeEnum;
use App\Enums\ManureProductTypeEnum;
use App\Enums\MeatProductTypeEnum;
use App\Enums\MilkProductTypeEnum;
use App\Enums\MonthEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\CenterSalesGoal;
use App\Models\DairyBox;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Herd;
use App\Models\MilkFeedingProgram;
use App\Models\MilkUtilizationMonthlySummary;
use App\Models\Partner;
use App\Models\Pl480;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Support\Audits\ValueLabels;
use App\Support\ServiceRecordSections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns an activity row into the shape the Audits page renders.
 *
 * Everything a reviewer sees is resolved to a sentence, a person's name or a
 * record's own label here, so the page never has to show an event slug, a
 * morph alias or a raw properties blob. A row the log cannot describe still
 * renders - it falls back to a readable headline rather than disappearing.
 */
class DescribeAuditActivityAction
{
    /**
     * Property keys that describe a status transition instead of prose, so they
     * are rendered as a from/to pair rather than as two loose fields.
     */
    private const STATUS_KEYS = ['old_status', 'new_status'];

    /**
     * Columns holding a foreign key, mapped to the record they point at.
     *
     * A stored id means nothing to the person reading the trail. "Center Id: 4"
     * is not a fact about the record, it is a database detail, and it sits in the
     * one place where a reviewer is meant to be able to trust what they read - so
     * the key is resolved to whatever the related record calls itself, using the
     * same labelling the subject line uses.
     *
     * @var array<string, class-string<Model>>
     */
    private const FOREIGN_KEYS = [
        'agency_id' => Agency::class,
        'center_id' => Center::class,
        'partner_id' => Partner::class,
        'production_partner_id' => Partner::class,
        'processing_partner_id' => Partner::class,
        'farmer_id' => Farmer::class,
        'project_id' => Project::class,
        'project_site_id' => ProjectSite::class,
        'service_record_id' => ServiceRecord::class,
        'dairy_box_id' => DairyBox::class,
        'herd_id' => Herd::class,
        'milk_feeding_program_id' => MilkFeedingProgram::class,
    ];

    /**
     * @var array<string, Center>|null Lazily filled code => center lookup.
     */
    private ?array $centersByCode = null;

    /**
     * @var array<int, Center>|null Lazily filled id => center lookup.
     */
    private ?array $centersById = null;

    /**
     * @var array<int, Service>|null Lazily filled id => service lookup.
     */
    private ?array $servicesById = null;

    public function execute(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'activity' => $this->activityLabel($activity),
            'category' => AuditCategoryEnum::resolve(
                $activity->subject_type,
                $activity->event,
                $activity->description,
            )->value,
            'action' => AuditActionEnum::resolve($activity->description, $activity->event)?->value,
            'user' => $this->user($activity),
            'subject' => $this->subject($activity),
            'organization' => $this->organization($activity),
            'occurred_at' => $activity->created_at?->toIso8601String(),
            'changes' => $this->changes($activity),
            'notes' => $this->notes($activity),
        ];
    }

    /**
     * The activity's own wording.
     *
     * Transitions are logged with a sentence ("Document Approved"), which is
     * already what a non-technical reader needs. Rows written with only an
     * event slug fall back to a headline of that slug, so `document-approved`
     * reads as "Document Approved" instead of leaking into the UI.
     */
    private function activityLabel(Activity $activity): string
    {
        if (filled($activity->description)) {
            return $activity->description;
        }

        return filled($activity->event) ? Str::headline($activity->event) : 'Activity';
    }

    /**
     * Who performed the activity, or null when the system did. Callers render
     * null as "System" rather than inventing an actor.
     *
     * @return array{name: string, role: string|null}|null
     */
    private function user(Activity $activity): ?array
    {
        $causer = $activity->causer;

        if (! $causer instanceof User) {
            return null;
        }

        return [
            'name' => $causer->name,
            'role' => $this->roleLabel($causer),
        ];
    }

    private function roleLabel(User $user): ?string
    {
        $role = $user->roles->first();

        if ($role === null) {
            return null;
        }

        return UserRoleEnum::tryFrom($role->name)?->label() ?? $role->name;
    }

    /**
     * What the activity was performed on. The type is the model's own name so
     * new morphed models describe themselves, and the label prefers whatever
     * the record calls itself over a database id.
     *
     * A deleted record no longer resolves through the morph relation, so the
     * type and label are rebuilt from the subject type and the pre-delete
     * attributes spatie recorded. Without this a deletion reads as "Partner
     * Deleted" with no partner named - the one edit most worth reviewing.
     *
     * @return array{type: string|null, label: string|null}|null
     */
    private function subject(Activity $activity): ?array
    {
        $subject = $activity->subject;

        if ($subject instanceof Model) {
            return [
                'type' => class_basename($subject),
                'label' => $this->subjectLabel($subject),
            ];
        }

        return $this->deletedSubject($activity);
    }

    /**
     * @return array{type: string|null, label: string|null}|null
     */
    private function deletedSubject(Activity $activity): ?array
    {
        $type = $activity->subject_type;

        if (blank($type)) {
            return null;
        }

        $attributes = $this->recordedState($activity);

        return [
            'type' => $this->subjectTypeLabel($type),
            // A creation has no prior state to name it by here, and never needed
            // one, because the row is still there to be read.
            'label' => $this->subjectLabelFromAttributes($type, $attributes),
        ];
    }

    /**
     * The record's own column values as the log stored them.
     *
     * Spatie files an edit under `attributes` (post-save) beside `old`
     * (pre-save), but a deletion only has the `old` half - there is no "after" to
     * record once the row is gone. Reading both keys is what lets a deleted
     * record still be named.
     *
     * @return array<string, mixed>
     */
    private function recordedState(Activity $activity): array
    {
        $properties = $this->properties($activity);

        $state = $properties['attributes'] ?? $properties['old'] ?? [];

        return is_array($state) ? $state : [];
    }

    private function subjectTypeLabel(string $subjectType): string
    {
        // Morph aliases read better than table names; a type stored as a full
        // class name (User is deliberately outside the morph map) is reduced to
        // its basename.
        return class_basename(Relation::getMorphedModel($subjectType) ?? $subjectType);
    }

    private function subjectLabel(Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Document => $subject->title,
            $subject instanceof MilkUtilizationMonthlySummary => $this->periodLabel($this->centerCodeById($subject->center_id), $subject->year),
            // A farmer is stored as separate name columns rather than one name,
            // so there is no single attribute to read.
            $subject instanceof Farmer => trim($subject->first_name.' '.$subject->last_name) ?: null,
            // A dairy box is named by the business it runs, not by a `name`
            // column, so without this a dairy box edit shows as "—".
            $subject instanceof DairyBox => $subject->business_name,
            // A goal is a row of targets rather than a named record, so it is
            // identified by the center and year it applies to.
            $subject instanceof CenterProductionGoal => $this->goalLabel($subject->center?->code, $subject->year),
            $subject instanceof CenterSalesGoal => $this->goalLabel($subject->center?->code, $subject->year),
            // A product is named by its type, which is stored as the enum's raw
            // value, so it is resolved to "Fresh Milk" rather than "fresh_milk".
            $subject instanceof Product => $this->productLabel($subject->name, $subject->category?->value),
            // A site is identified by its own code rather than a name.
            $subject instanceof ProjectSite => $subject->project_site_code,
            // A PL480 row is one province's allocation for one year. It has no name
            // and usually no center, so it is named by the province and the year -
            // without this it falls through to the generic "measurement" label and
            // shows as a bare year.
            $subject instanceof Pl480 => $this->periodLabel($subject->province, $subject->year),
            // All five service-record forms write the same row, so a record
            // carries no name of its own: what it is called is the service it
            // recorded - "Production Support", "IEC". Without this every one of
            // them reads as a bare "ServiceRecord".
            $subject instanceof ServiceRecord => ServiceRecordSections::labelFor($subject->service?->simplified_code),
            // A production or sales row is a single month of numbers for one
            // center, so it is named by those coordinates.
            default => $this->measurementLabel($subject) ?? ($subject->name ?? $subject->title ?? null),
        };
    }

    /**
     * How a measurement row is named in the trail.
     *
     * A milk/meat/manure production or sale row carries no name - it is one
     * center's figures for one period - so it is labelled by the coordinates
     * that identify it: the center, the year and the month. A row that carries
     * its own code (a production code) is named by that instead.
     */
    private function measurementLabel(Model $subject): ?string
    {
        if ($subject->getAttribute('month') === null && $subject->getAttribute('year') === null) {
            return null;
        }

        $parts = [];

        $centerCode = $subject->getAttribute('center_id')
            ? $this->centerCodeById($subject->getAttribute('center_id'))
            : null;

        if (filled($centerCode)) {
            $parts[] = $centerCode;
        }

        $year = $subject->getAttribute('year');

        if (filled($year)) {
            $parts[] = (string) $year;
        }

        $month = $subject->getAttribute('month');

        if (filled($month)) {
            $parts[] = MonthEnum::tryFrom((int) $month)?->label() ?? (string) $month;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The product-type labels for the category a product belongs to. A product's
     * type is meaningless without its category - "fresh_milk" is a milk product,
     * "hide" is not - so the category picks the enum.
     */
    private function productLabel(?string $name, ?string $category): ?string
    {
        if (blank($name)) {
            return null;
        }

        $labels = match ($category) {
            ProductCategoryEnum::MILK->value => MilkProductTypeEnum::labels(),
            ProductCategoryEnum::MEAT->value => MeatProductTypeEnum::labels(),
            ProductCategoryEnum::MANURE->value => ManureProductTypeEnum::labels(),
            ProductCategoryEnum::HIDE->value => HideProductTypeEnum::labels(),
            default => [],
        };

        // A product typed free-hand outside the enum is still shown as typed.
        return $labels[$name] ?? $name;
    }

    /**
     * The measurement label built from a deleted row's recorded attributes,
     * mirroring measurementLabel().
     *
     * @param  array<string, mixed>  $attributes
     */
    private function measurementLabelFromAttributes(array $attributes): ?string
    {
        if (($attributes['month'] ?? null) === null && ($attributes['year'] ?? null) === null) {
            return null;
        }

        $parts = [];

        $centerCode = filled($attributes['center_id'] ?? null)
            ? $this->centerCodeById($attributes['center_id'])
            : null;

        if (filled($centerCode)) {
            $parts[] = $centerCode;
        }

        if (filled($attributes['year'] ?? null)) {
            $parts[] = (string) $attributes['year'];
        }

        if (filled($attributes['month'] ?? null)) {
            $month = (int) $attributes['month'];
            $parts[] = MonthEnum::tryFrom($month)?->label() ?? (string) $month;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The same labelling rules as subjectLabel(), applied to a deleted record's
     * recorded attributes rather than to a live model.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function subjectLabelFromAttributes(string $subjectType, array $attributes): ?string
    {
        $model = Relation::getMorphedModel($subjectType);

        $label = match (true) {
            $model === Document::class => $attributes['title'] ?? null,
            $model === MilkUtilizationMonthlySummary::class => $this->periodLabel($this->centerCodeById($attributes['center_id'] ?? null), $attributes['year'] ?? null),
            $model === Farmer::class => trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? '')) ?: null,
            $model === DairyBox::class => $attributes['business_name'] ?? null,
            $model === CenterProductionGoal::class => $this->goalLabel($this->centerCodeById($attributes['center_id'] ?? null), $attributes['year'] ?? null),
            $model === CenterSalesGoal::class => $this->goalLabel($this->centerCodeById($attributes['center_id'] ?? null), $attributes['year'] ?? null),
            $model === Product::class => $this->productLabel($attributes['name'] ?? null, $attributes['category'] ?? null),
            $model === ProjectSite::class => $attributes['project_site_code'] ?? null,
            // A dairy box is named by the business it runs, not by a `name` column,
            // so without this a deleted one reads as "—" - the entry most worth
            // reading.
            $model === DairyBox::class => $attributes['business_name'] ?? null,
            $model === Pl480::class => $this->periodLabel($attributes['province'] ?? null, $attributes['year'] ?? null),
            // As above: a deleted record is named by the service it recorded,
            // resolved from the service_id spatie recorded alongside the delete.
            $model === ServiceRecord::class => ServiceRecordSections::labelFor($this->serviceCodeById($attributes['service_id'] ?? null)),
            default => $this->measurementLabelFromAttributes($attributes) ?? ($attributes['name'] ?? $attributes['title'] ?? null),
        };

        return filled($label) ? (string) $label : null;
    }

    /**
     * A row identified by what it covers rather than by a name of its own -
     * "Cagayan · 2026" for a PL480 allocation.
     */
    private function periodLabel(mixed $subject, mixed $year): ?string
    {
        $parts = array_values(array_filter([$subject, $year], fn ($part) => filled($part)));

        return $parts === [] ? null : implode(' · ', array_map('strval', $parts));
    }

    /**
     * A goal is named by what it is a target for - "CLSU · 2026" - because a row
     * of milk/meat/manure/hide numbers has no name of its own.
     */
    private function goalLabel(?string $centerCode, mixed $year): string
    {
        $center = filled($centerCode) ? $centerCode : 'Center';
        $year = filled($year) ? (string) $year : null;

        return $year === null ? $center : $center.' · '.$year;
    }

    private function centerCodeById(mixed $centerId): ?string
    {
        if ($centerId === null) {
            return null;
        }

        return $this->centersById()[(int) $centerId]->code ?? null;
    }

    /**
     * The `simplified_code` of a service, by id.
     */
    private function serviceCodeById(mixed $serviceId): ?string
    {
        if ($serviceId === null) {
            return null;
        }

        return $this->servicesById()[(int) $serviceId]->simplified_code ?? null;
    }

    /**
     * The center the activity belongs to.
     *
     * A document carries no center_id of its own - it is tagged with whatever it
     * belongs to - so a document's center is resolved from its tags first (the
     * center it carries, then the center of the partner or farmer it hangs off),
     * and only then from whoever uploaded or acted on it. A partner or farmer
     * document carries no center tag of its own, so without the second step a
     * document uploaded by a PCC officer would show no organization at all, even
     * though the partner it belongs to sits in exactly one center.
     *
     * @return array{name: string, code: string}|null
     */
    private function organization(Activity $activity): ?array
    {
        $center = $this->centerFor($activity);

        return $center instanceof Center ? ['name' => $center->name, 'code' => $center->code] : null;
    }

    private function centerFor(Activity $activity): ?Center
    {
        $subject = $activity->subject;

        if ($subject instanceof Document) {
            $center = $this->documentCenter($subject);

            if ($center instanceof Center) {
                return $center;
            }

            $uploaderCenter = $subject->file?->uploadedBy?->center;

            if ($uploaderCenter instanceof Center) {
                return $uploaderCenter;
            }
        }

        // A profile carries its own center, which is the one the reviewer is
        // asking about - a PCC officer editing a farmer belongs to no center, so
        // the causer fallback below would attribute the edit to the wrong place.
        if ($subject instanceof Center) {
            return $subject;
        }

        if ($subject instanceof Product) {
            $center = $this->centerOfProduct($subject);

            if ($center instanceof Center) {
                return $center;
            }
        }

        if ($subject !== null && $subject->getAttribute('center_id') !== null) {
            $center = $subject->center ?? null;

            if ($center instanceof Center) {
                return $center;
            }
        }

        // A project site can sit under a partner rather than a center directly,
        // so the partner is asked for its center next.
        if ($subject !== null && $subject->getAttribute('partner_id') !== null) {
            $center = $subject->partner?->center ?? null;

            if ($center instanceof Center) {
                return $center;
            }
        }

        // A deleted profile keeps its center_id among the values the log
        // recorded, so the center can still be named even though the profile is
        // gone.
        $recordedCenterId = $this->recordedState($activity)['center_id'] ?? null;

        if ($recordedCenterId !== null) {
            $center = Center::find($recordedCenterId);

            if ($center instanceof Center) {
                return $center;
            }
        }

        return $activity->causer?->center;
    }

    /**
     * A document's center, taken from the tags it carries.
     *
     * The owner tags are written inconsistently - a partner is sometimes tagged
     * by code and sometimes by id, depending on which form uploaded it - so both
     * are accepted. A tag that matches neither is skipped rather than treated as
     * a miss, letting the remaining steps try.
     */
    private function documentCenter(Document $document): ?Center
    {
        $tags = $document->tags;

        $code = $tags->firstWhere('key', 'center')?->value;

        if (filled($code)) {
            $center = $this->centersByCode()[$code] ?? null;

            if ($center instanceof Center) {
                return $center;
            }
        }

        foreach (['partner', 'farmer', 'dairy_box'] as $key) {
            $owner = $this->centerOfOwner($key, $tags->firstWhere('key', $key)?->value);

            if ($owner instanceof Center) {
                return $owner;
            }
        }

        return null;
    }

    private function centerOfOwner(string $key, mixed $value): ?Center
    {
        if (blank($value)) {
            return null;
        }

        $owner = match ($key) {
            'partner' => Partner::query()
                ->where('code', $value)
                // Only an id-like tag is worth matching against the key; sending
                // a code to an integer column would scan for nothing.
                ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value))
                ->first(),
            'farmer' => Farmer::query()->where('id', $value)->first(),
            // A dairy box carries no center tag of its own, so without this a
            // compliance document uploaded against one showed no organization at
            // all - even though the box sits in exactly one center.
            'dairy_box' => DairyBox::query()->find((int) $value),
            default => null,
        };

        return $owner?->center;
    }

    /**
     * A product belongs to its center or to a partner through a pivot, not
     * through a `center_id` column, so its center has to be reached through one
     * of those two relationships.
     */
    private function centerOfProduct(Product $product): ?Center
    {
        $center = $product->centers()->first();

        if ($center instanceof Center) {
            return $center;
        }

        return $product->partners()->first()?->center;
    }

    /**
     * @return array<string, Center>
     */
    private function centersByCode(): array
    {
        return $this->centersByCode ??= Center::query()
            ->get(['id', 'name', 'code'])
            ->keyBy('code')
            ->all();
    }

    /**
     * @return array<int, Center>
     */
    private function centersById(): array
    {
        return $this->centersById ??= Center::query()
            ->get(['id', 'name', 'code'])
            ->keyBy('id')
            ->all();
    }

    /**
     * @return array<int, Service>
     */
    private function servicesById(): array
    {
        return $this->servicesById ??= Service::query()
            ->get(['id', 'simplified_code'])
            ->keyBy('id')
            ->all();
    }

    /**
     * Fields whose value moved, oldest to newest.
     *
     * Two shapes are supported: the status pair written by the document
     * workflow, and the `old`/`attributes` pair spatie records when a model logs
     * its own edits. Anything else belongs in `notes()`.
     *
     * @return list<array{label: string, from: string|null, to: string|null}>
     */
    private function changes(Activity $activity): array
    {
        $properties = $this->properties($activity);
        $changes = [];

        $old = $properties['old_status'] ?? null;
        $new = $properties['new_status'] ?? null;

        // Only when there is a status to have moved *from*. An upload records the
        // state it started in and nothing before it, so reporting it as a change
        // read "-> Draft" on an entry whose whole point was what was uploaded.
        // A transition still reports, and that is the fact a reviewer is there for:
        // "Draft -> Rejected" says more than the entry's title does.
        if ($old !== null) {
            $changes[] = [
                'label' => 'Status',
                'from' => $this->statusLabel($old),
                'to' => $new === null ? null : $this->statusLabel($new),
            ];
        }

        foreach ($this->recordedFields($properties, $activity->subject) as $field => $values) {
            if ($field === 'month' && $activity->subject_type === (new MilkUtilizationMonthlySummary)->getMorphClass()) {
                continue;
            }

            foreach ($this->fieldChanges($this->fieldLabel($field), $values['old'], $values['new']) as $change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * One entry per value that moved within a field.
     *
     * A JSON column is a bag of unrelated form fields - a partner's `metadata`
     * alone holds fifty - so treating it as a single value renders every value it
     * contains joined into one line, which buries the one key the reviewer cares
     * about. An array-valued field is therefore expanded into one entry per key
     * that actually changed.
     *
     * @return list<array{label: string, from: string|null, to: string|null}>
     */
    private function fieldChanges(string $label, mixed $old, mixed $new): array
    {
        if (! is_array($old) && ! is_array($new)) {
            return [$this->change($label, $old, $new)];
        }

        $from = is_array($old) ? $old : [];
        $to = is_array($new) ? $new : [];

        // A JSON column holds one of two things, and they read differently.
        //
        // A *bag* - a partner's `metadata` - holds unrelated values under keys that
        // name them, so each key is a field of its own and expanding it is what
        // makes "main_business: null -> Pasteurized milk" readable.
        //
        // A *set* - a project's `project_involvement` - holds values whose position
        // means nothing. Expanded by index it reported "Project Involvement 8:
        // null -> CBIN-2026", which describes the ninth element rather than the
        // change: adding one value to a set of nine is one field, before and after.
        // Non-empty on purpose: array_is_list([]) is true, and an emptied `metadata`
        // bag has to keep expanding by key - "Metadata Main Business: x -> null"
        // says what was removed, "Metadata: main_business: x -> null" does not.
        if (($from !== [] && array_is_list($from)) || ($to !== [] && array_is_list($to))) {
            return [$this->change($label, $from === [] ? null : $from, $to === [] ? null : $to)];
        }

        $changes = [];

        // Union rather than array_keys($to): a key that was removed is a change
        // too, and it only exists on the left.
        foreach (array_keys($from + $to) as $key) {
            $before = $from[$key] ?? null;
            $after = $to[$key] ?? null;

            if ($before === $after) {
                continue;
            }

            $changes[] = $this->change(trim($label.' '.Str::headline($key)), $before, $after);
        }

        return $changes;
    }

    /**
     * How a logged column is named in the from/to list. A foreign key reads as the
     * record it points at - "Center", not "Center Id" - because the value beside it
     * is a name rather than a number.
     */
    private function fieldLabel(string $field): string
    {
        return Str::headline(str_ends_with($field, '_id') ? substr($field, 0, -3) : $field);
    }

    /**
     * @return array{label: string, from: string|null, to: string|null}
     */
    private function change(string $label, mixed $from, mixed $to): array
    {
        return [
            'label' => $label,
            'from' => $this->scalar($from),
            'to' => $this->scalar($to),
        ];
    }

    /**
     * The fields an entry reports, each paired with the value it had before.
     *
     * Spatie writes the pre-save values under `old` and the post-save values under
     * `attributes`. An edit is the difference between the two, so only the fields
     * that moved are reported - an untouched column appearing as a "change" with the
     * same value on both sides says nothing and buries the edit that does.
     *
     * A creation and a deletion have only one of the halves: there is no earlier
     * value to compare a creation against, and no later one to compare a deletion
     * against. Reporting nothing there left a reviewer looking at "Epahp Gatasang
     * Kalabaw Created" with no idea what was created, so the values that *were*
     * recorded are reported instead - as a from/to pair with one side empty, which
     * is exactly what happened to them. A column recorded as null is left out: a
     * pair with nothing on either side says nothing, and there are a dozen of them
     * on any record with foreign keys.
     *
     * @param  array<string, mixed>  $properties
     * @return Collection<string, array{old: mixed, new: mixed}>
     */
    private function recordedFields(array $properties, ?Model $subject = null): Collection
    {
        $old = $properties['old'] ?? null;
        $new = $properties['attributes'] ?? null;

        if (! is_array($old) && is_array($new)) {
            return $this->labelValues($this->resolveForeignKeys(
                collect($new)
                    ->filter(fn (mixed $value) => $value !== null)
                    ->map(fn (mixed $value) => ['old' => null, 'new' => $value])
            ), $subject);
        }

        if (is_array($old) && ! is_array($new)) {
            return $this->labelValues($this->resolveForeignKeys(
                collect($old)
                    ->filter(fn (mixed $value) => $value !== null)
                    ->map(fn (mixed $value) => ['old' => $value, 'new' => null])
            ), $subject);
        }

        if (! is_array($old) || ! is_array($new)) {
            return collect();
        }

        return $this->labelValues($this->resolveForeignKeys(
            collect($new)
                ->filter(fn ($value, string $field) => array_key_exists($field, $old) && $old[$field] !== $value)
                ->map(fn ($value, string $field) => ['old' => $old[$field], 'new' => $value])
        ), $subject);
    }

    /**
     * Replace a stored foreign key with the name of the record it points at.
     *
     * @param  Collection<string, array{old: mixed, new: mixed}>  $fields
     * @return Collection<string, array{old: mixed, new: mixed}>
     */
    private function resolveForeignKeys(Collection $fields): Collection
    {
        return $fields->map(function (array $values, string $field): array {
            $model = self::FOREIGN_KEYS[$field] ?? null;

            if ($model === null) {
                return $values;
            }

            foreach (['old', 'new'] as $side) {
                if (is_numeric($values[$side])) {
                    $values[$side] = $this->foreignLabel($model, (int) $values[$side]);
                }
            }

            return $values;
        });
    }

    /**
     * Replace a stored value with the wording a reviewer should read.
     *
     * A model's own columns are logged by the trait, which stores what the database
     * holds - an enum's value, a PSGC code. That is right for diffing and useless
     * for reading, so `land_ownership` would show as "lgu -> suc" instead of
     * "Local Government Unit -> SUC".
     *
     * @param  Collection<string, array{old: mixed, new: mixed}>  $fields
     * @return Collection<string, array{old: mixed, new: mixed}>
     */
    private function labelValues(Collection $fields, ?Model $subject = null): Collection
    {
        return $fields->map(function (array $values, string $field) use ($subject): array {
            $enum = $subject instanceof Model ? $this->enumFor($subject, $field) : null;

            $resolve = fn (mixed $value) => $enum === null
                ? ValueLabels::for($field, $value)
                : ValueLabels::fromEnum($enum, $value);

            return ['old' => $resolve($values['old']), 'new' => $resolve($values['new'])];
        });
    }

    /**
     * The enum a model's own column is cast to, if any.
     *
     * Asked of the subject rather than looked up by column name, because the same
     * column name means different things on different records: `status` is a
     * `ProfileStatusEnum` on a partner and a `DairyBoxEnum` on a dairy box, and
     * guessing would label one of them wrongly.
     *
     * @return class-string|null
     */
    private function enumFor(Model $subject, string $column): ?string
    {
        $cast = $subject->getCasts()[$column] ?? null;

        if (! is_string($cast)) {
            return null;
        }

        $parts = explode(':', $cast);
        $enum = trim(end($parts), '\\');

        return enum_exists($enum) ? $enum : null;
    }

    /**
     * What the related record calls itself, or null when it has since been deleted.
     *
     * @param  class-string<Model>  $model
     */
    private function foreignLabel(string $model, int $id): ?string
    {
        $related = $model::query()->find($id);

        return $related instanceof Model ? $this->subjectLabel($related) : null;
    }

    /**
     * Everything else the activity recorded: the reason a document came back,
     * the error a processing run hit, or any other property, each as a readable
     * label rather than a raw key.
     *
     * @return list<array{label: string, value: string}>
     */
    private function notes(Activity $activity): array
    {
        // `old`/`attributes` are already rendered field by field in changes();
        // repeating them here would show the reviewer the same edit twice, once
        // readably and once as a dump.
        $skip = array_merge(self::STATUS_KEYS, ['old', 'attributes']);
        $labels = [
            'rejection_reason' => 'Reason',
            'error' => 'Error',
        ];

        $notes = [];

        foreach ($this->properties($activity) as $key => $value) {
            if (in_array($key, $skip, true) || $value === null || $value === '') {
                continue;
            }

            $notes[] = [
                'label' => $labels[$key] ?? Str::headline($key),
                'value' => (string) $this->scalar($value),
            ];
        }

        return array_merge($notes, $this->subjectDetails($activity));
    }

    /**
     * What the subject itself says about the activity, for records whose own entry
     * carries nothing but a status transition.
     *
     * A document upload logs one thing: the status it started in. Everything a
     * reviewer actually wants - which kind of document, what it is attached to, its
     * registration number and validity - lives on the document and on its tags, so
     * without this an entry read "Document Uploaded / Draft" and nothing more.
     *
     * Derived here rather than written into the activity, so the entries already in
     * the log gain their detail too and no stored row has to be rewritten.
     *
     * @return list<array{label: string, value: string}>
     */
    private function subjectDetails(Activity $activity): array
    {
        $subject = $activity->subject;

        if (! $subject instanceof Document) {
            return [];
        }

        // Shared with the Documents page, so an upload reads the same way in both
        // places and a fix to one shows up in the other.
        return app(DescribeDocumentAction::class)->details($subject);
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(Activity $activity): array
    {
        return $activity->properties?->toArray() ?? [];
    }

    private function statusLabel(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return DocumentStatusEnum::tryFrom($value)?->label() ?? Str::headline($value);
    }

    /**
     * A single-line rendering of a property value. Arrays are reduced to
     * `key: value` pairs rather than dumped, so the page never shows a JSON blob
     * and never shows a bare list of values with no idea which key each belongs
     * to.
     */
    private function scalar(mixed $value): ?string
    {
        // Strict comparison: 0 and false are real values, not "empty".
        if (in_array($value, [null, [], ''], true)) {
            return null;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item, $key) => is_string($key)
                    ? $key.': '.$this->scalar($item)
                    : $this->scalar($item))
                ->filter()
                ->implode(', ');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}

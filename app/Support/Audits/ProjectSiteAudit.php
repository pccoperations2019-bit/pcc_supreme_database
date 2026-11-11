<?php

declare(strict_types=1);

namespace App\Support\Audits;

use App\Models\ProjectSite;
use App\Services\ProjectSiteAddressService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Records what a project site save wrote *beside* the site itself.
 *
 * A CBIN or CCDP form is one submission that lands in a dozen tables: the site's
 * own columns, its address, and nine groups of nested records. `ProjectSite` logs
 * its own columns and nothing else, so the nested groups are invisible to the
 * audit trail - and because a save that touches only a nested group moves no site
 * column, it produces no entry at all. A reviewer is left with a "Project Site
 * Updated" entry that does not mention the productions they just edited, or with
 * nothing whatsoever.
 *
 * Each group is reported as one field saying how its rows moved, rather than one
 * field per cell. A training record is 34 columns wide; a cell-by-cell dump would
 * bury the entry under the very data the reviewer opened the site's own page to
 * read. The site's own columns and its address still report field by field,
 * because those are the fields a reviewer expects to read one at a time.
 */
class ProjectSiteAudit
{
    /**
     * The nested record groups a CBIN/CCDP form carries, as
     * `ProjectSite` relation method => reviewer-facing label.
     *
     * @var array<string, string>
     */
    private const RELATIONS = [
        'productions' => 'Productions',
        'infrastructure' => 'Processing Infrastructure',
        'equipment' => 'Equipment',
        'processingStaff' => 'Processing Staff',
        'marketingStaff' => 'Marketing Staff',
        'marketingInfrastructure' => 'Marketing Infrastructure',
        'trainingRecords' => 'Training Records',
        'budgetUtilization' => 'Budget Utilization',
        'documents' => 'Legal Documents',
        // Staff nested under an infrastructure row hangs off that row rather than
        // off the site, so it is captured through its parent or an edit to it
        // would never be reported.
        'infrastructureProcessingStaff' => 'Processing Infrastructure Staff',
        'infrastructureMarketingStaff' => 'Marketing Infrastructure Staff',
    ];

    /**
     * Groups that are deleted and recreated on every save, so their row ids change
     * even when none of their values do. Those are compared by value instead, or
     * every save would report the group as replaced.
     *
     * @var list<string>
     */
    private const REPLACED_ON_SAVE = ['budgetUtilization'];

    /**
     * Columns that say nothing about what a row holds: two saves of the same row
     * differ in these even when the data is identical.
     *
     * @var list<string>
     */
    private const IGNORED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * Read the state the address and the nested records are currently in.
     *
     * This has to run *before* the save, because the groups are replaced on write:
     * once the form has been persisted there is no way back to what was there.
     *
     * @return array{address: array<string, mixed>, relations: array<string, array<int|string, array<string, mixed>>>}
     */
    public function capture(ProjectSite $site): array
    {
        $relations = [];

        foreach (array_keys(self::RELATIONS) as $key) {
            $relations[$key] = $this->rowsFor($site, $key);
        }

        return [
            'address' => $site->address?->only(ProjectSiteAddressService::FIELDS) ?? [],
            'relations' => $relations,
        ];
    }

    /**
     * Write one entry naming the address fields and record groups the save moved,
     * and nothing at all when none of them did.
     *
     * An empty `$before` means the site had no address and no records yet - a
     * creation - so every field in the entry reads as added.
     *
     * @param  array{address?: array<string, mixed>, relations?: array<string, array<int|string, array<string, mixed>>>}|array{}  $before
     * @param  string  $event  The slug the Audits page resolves the action from.
     */
    public function record(ProjectSite $site, array $before, string $description, ?Model $causer, string $event = 'updated'): bool
    {
        $after = $this->capture($site);

        $old = [];
        $attributes = [];

        foreach ($this->fields($before, $after, notes: $before !== []) as $field => $pair) {
            $old[$field] = $pair['before'];
            $attributes[$field] = $pair['after'];
        }

        return AuditTrail::logChanges(
            subject: $site,
            causer: $causer,
            description: $description,
            before: $before === [] ? [] : $old,
            after: $attributes,
            event: $event,
        );
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function rowsFor(ProjectSite $site, string $key): array
    {
        return match ($key) {
            'infrastructureProcessingStaff' => $this->rowsThrough($site->infrastructure(), 'processingStaff'),
            'infrastructureMarketingStaff' => $this->rowsThrough($site->marketingInfrastructure(), 'marketingStaff'),
            default => $this->rows($site->{$key}()),
        };
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function rows(HasMany $relation): array
    {
        $rows = [];

        foreach ($relation->get() as $row) {
            $rows[$row->getKey()] = $this->values($row);
        }

        return $rows;
    }

    /**
     * The same, for a group that hangs one level further down. A nested row is
     * keyed by its own id prefixed with its parent's, so two infrastructures that
     * each have a staff row stay two rows rather than colliding on one key.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function rowsThrough(HasMany $relation, string $nested): array
    {
        $rows = [];

        foreach ($relation->get() as $parent) {
            foreach ($parent->{$nested}()->get() as $row) {
                $rows[$parent->getKey().'-'.$row->getKey()] = $this->values($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Model $row): array
    {
        return array_diff_key($row->getAttributes(), array_flip(self::IGNORED_COLUMNS));
    }

    /**
     * Every field the entry can carry, as `field => [value before, value after]`.
     *
     * A record group is reported by how many rows it holds, with the movement
     * spelled out on the newer side. The movement has to be part of the value
     * rather than a separate note, because the audit trail compares the two sides
     * as text: a row edited in place leaves the row count untouched, so "2 rows"
     * on both sides would read as no change at all.
     *
     * @param  array{address?: array<string, mixed>, relations?: array<string, array<int|string, array<string, mixed>>>}  $before
     * @param  array{address?: array<string, mixed>, relations?: array<string, array<int|string, array<string, mixed>>>}  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function fields(array $before, array $after, bool $notes): array
    {
        $fields = [];

        foreach (array_keys($before['address'] ?? []) + array_keys($after['address'] ?? []) as $field) {
            $fields[$field] = [
                'before' => $before['address'][$field] ?? null,
                'after' => $after['address'][$field] ?? null,
            ];
        }

        foreach (self::RELATIONS as $key => $label) {
            $rowsBefore = $before['relations'][$key] ?? [];
            $rowsAfter = $after['relations'][$key] ?? [];

            $moved = $notes
                ? $this->movement($rowsBefore, $rowsAfter, in_array($key, self::REPLACED_ON_SAVE, true))
                : [];

            $summary = $this->rowCount($rowsAfter);

            $fields[$label] = [
                'before' => $this->rowCount($rowsBefore),
                'after' => $moved === [] || $summary === null
                    ? $summary
                    : $summary.' ('.implode(', ', $moved).')',
            ];
        }

        return $fields;
    }

    /**
     * How many rows a group holds, or null when it holds none.
     *
     * An empty group is worth saying nothing about: naming all eleven of them on
     * a creation that filled in two is the noise the trail exists to avoid. The
     * one case that needs it is the group that *had* rows and no longer does, and
     * there the "N rows" side of the pair carries the story.
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private function rowCount(array $rows): ?string
    {
        return $rows === [] ? null : count($rows).' '.Str::plural('row', count($rows));
    }

    /**
     * What happened to a group, in the words a reviewer reads - "1 added",
     * "Delivered edited" - or nothing when it holds what it held.
     *
     * @param  array<int|string, array<string, mixed>>  $before
     * @param  array<int|string, array<string, mixed>>  $after
     * @return list<string>
     */
    private function movement(array $before, array $after, bool $replacedOnSave): array
    {
        if ($replacedOnSave) {
            return $this->movementByValue($before, $after);
        }

        $kept = array_intersect_key($before, $after);

        $columns = collect($kept)
            ->flatMap(fn (array $values, $key) => array_keys(array_filter(
                $after[$key],
                fn (mixed $value, string $column) => ($values[$column] ?? null) !== $value,
                ARRAY_FILTER_USE_BOTH,
            )))
            ->unique()
            ->sort()
            ->map(fn (string $column) => Str::headline($column).' edited');

        $added = count(array_diff_key($after, $before));
        $removed = count(array_diff_key($before, $after));

        return array_values(array_filter(array_merge(
            [$added > 0 ? "+{$added} added" : null],
            [$removed > 0 ? "-{$removed} removed" : null],
            $columns->all(),
        )));
    }

    /**
     * For a group that is deleted and recreated on every save, ids are worthless as
     * identities: the same values come back under new ones. Rows are matched by
     * value instead, and an edited row reads as one gone and one arrived - which is
     * exactly what happened to it.
     *
     * @param  array<int|string, array<string, mixed>>  $before
     * @param  array<int|string, array<string, mixed>>  $after
     * @return list<string>
     */
    private function movementByValue(array $before, array $after): array
    {
        $unchanged = count(array_intersect(
            array_map($this->comparable(...), $before),
            array_map($this->comparable(...), $after),
        ));

        $added = count($after) - $unchanged;
        $removed = count($before) - $unchanged;

        return array_values(array_filter([
            $added > 0 ? "+{$added} added" : null,
            $removed > 0 ? "-{$removed} removed" : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function comparable(array $values): string
    {
        ksort($values);

        return (string) json_encode($values);
    }
}

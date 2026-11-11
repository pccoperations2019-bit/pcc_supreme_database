<?php

namespace App\Support\Audits;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the fields a save wrote outside a record's own columns.
 *
 * A profile logs its own columns through `LogsAuditTrail`, and that already
 * reports only what moved. What it cannot see is what a form submission writes
 * *beside* those columns: the address row hanging off the profile, the projects
 * it is assigned to. Those are separate rows, so the profile's own entry either
 * claims the profile changed while the street the reviewer just edited is nowhere
 * in it, or - when the address was the only thing touched - no entry is written
 * at all.
 *
 * This closes that gap, and keeps two things out of the trail while doing it:
 *
 * - Fields that did not move. An entry listing twenty unchanged fields says
 *   nothing; the one field that moved is buried in it.
 * - Fields that only look different. A form submits text while the database hands
 *   back the column's own type, so a zip code read as `4859` and submitted as
 *   `"4859"` would otherwise be reported as an edit on every save.
 *
 * A list of values - the projects a center belongs to - is recorded as a single
 * field rather than one per entry, so the review screen reads "Projects: a, b ->
 * c" instead of numbering a line per project.
 */
class AuditTrail
{
    /**
     * Write one entry naming only the fields that were added or changed, and
     * nothing at all when none of them moved.
     *
     * Both arrays must describe the same set of fields, and both values for a
     * field must be read the same way - through the model's accessors rather than
     * straight off the database - so that a value that did not move compares
     * equal. Callers read the "before" half before the write and the "after" half
     * from the model once it has been saved.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  string  $event  The slug the Audits page resolves the action from.
     * @return bool Whether anything moved, i.e. whether an entry was written.
     */
    public static function logChanges(
        Model $subject,
        ?Model $causer,
        string $description,
        array $before,
        array $after,
        string $event = 'updated',
    ): bool {
        $changes = self::changesBetween($before, $after);

        // An edit that left nothing behind is not an edit. Writing an entry with
        // nothing in it is the noise this whole class exists to avoid.
        if ($changes['attributes'] === []) {
            return false;
        }

        activity()
            ->causedBy($causer)
            ->performedOn($subject)
            ->withProperties($changes)
            ->event($event)
            ->log($description);

        return true;
    }

    /**
     * Write a profile's address and record the fields that moved.
     *
     * The address is a morphOne row hanging off the profile, so it is invisible to
     * the profile's own entry - and an edit that touched only the address moves no
     * profile column, so with `logOnlyDirty` there is no entry at all. Every
     * profile form writes one, so this is the shared shape rather than something
     * each controller assembles for itself.
     *
     * The address has to be read before the write: it is overwritten in place, and
     * afterwards there is nothing left to compare against.
     *
     * @param  array<string, mixed>  $data  The columns to write, in full - a field
     *                                      left out is left alone, one set to null is
     *                                      cleared and reported as such.
     */
    public static function syncAddress(
        Model $subject,
        ?Model $causer,
        string $description,
        array $data,
        string $event = 'updated',
    ): bool {
        $address = $subject->address;

        $before = $address?->only(array_keys($data)) ?? [];

        if ($address) {
            $address->update($data);
        } else {
            $address = $subject->address()->create($data);
        }

        return self::logChanges(
            subject: $subject,
            causer: $causer,
            description: $description,
            before: $before,
            after: $address->only(array_keys($data)),
            event: $event,
        );
    }

    /**
     * The half of `$after` that `$before` does not already hold, paired with the
     * values it replaced - the shape the Audits page renders as from/to pairs.
     *
     * A field only `$before` holds is reported too: it was removed, which is a
     * change the reviewer needs to see just as much as an addition.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string, mixed>, attributes: array<string, mixed>}
     */
    public static function changesBetween(array $before, array $after): array
    {
        $old = [];
        $moved = [];

        foreach (array_keys($before + $after) as $field) {
            // Resolved before comparing, not just before recording: the address
            // cascade moved from storing names to storing PSGC codes, so a location
            // that did not move arrives spelled two different ways. Comparing the raw
            // values reported that as somebody having moved the dairy box.
            $previous = self::line(ValueLabels::for($field, $before[$field] ?? null));
            $current = self::line(ValueLabels::for($field, $after[$field] ?? null));

            if (self::holds($previous, $current)) {
                continue;
            }

            $old[$field] = $previous;
            $moved[$field] = $current;
        }

        return ['old' => $old, 'attributes' => $moved];
    }

    /**
     * Whether two values mean the same thing, rather than merely printing the
     * same way.
     */
    private static function holds(mixed $before, mixed $after): bool
    {
        if (is_array($before) || is_array($after)) {
            return $before == $after;
        }

        // The column's type and the form's text can spell one number two ways: an
        // `int` zip code read as 4859 and submitted as "4859", a decimal column
        // read as 18.0479 and submitted as "18.0479000". Neither is an edit.
        if (is_numeric($before) && is_numeric($after)) {
            return (float) $before === (float) $after;
        }

        return self::asText($before) === self::asText($after);
    }

    /**
     * A single comparable, recordable form of a value.
     *
     * An empty list becomes null, so a set of assignments that was emptied and one
     * that never had any are the same thing. A list becomes one comma-separated
     * line, because a set of values is one field a reviewer reads, not a numbered
     * column per entry. An associative array is left alone: a bag of unrelated
     * keys is already rendered key by key by the Audits page.
     */
    private static function line(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return null;
        }

        if (! array_is_list($value)) {
            return $value;
        }

        return implode(', ', array_map(
            fn (mixed $item) => is_scalar($item) || $item === null ? (string) $item : json_encode($item),
            $value,
        ));
    }

    private static function asText(mixed $value): string
    {
        // An audited column cast to an enum hands back the case, and a case cannot
        // be cast to string - comparing one against the string the form submitted
        // would report every save of that column as an edit.
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}

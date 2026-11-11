<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit-trail wiring for the records a PCC or center admin maintains.
 *
 * The Audits page already knows how to read everything this produces: the
 * subject morph type decides the category (AuditCategoryEnum), the description
 * decides the action (AuditActionEnum), and the `changes` property is rendered
 * as a from/to list. Nothing further is needed per model, so every audited model
 * shares these defaults rather than restating them.
 *
 * Two decisions are made here that a model should not have to repeat:
 *
 * - Only dirty attributes are logged. A save that writes the same row back would
 *   otherwise fill the trail with "updated" noise that hides the real edits.
 * - Credentials are excluded. These models are `$guarded`, so `logUnguarded()`
 *   covers every column, which would otherwise write password hashes into a
 *   table the review screen renders.
 *
 * One caller-side rule comes with this: spatie snapshots the pre-edit state from
 * the model's own original attributes, so a model that was created earlier in the
 * same request still carries only the columns it was given. Every column the
 * database defaulted therefore reads as having changed. Controllers are safe
 * because route model binding hands them a model loaded from the database, but
 * code that does `$model = X::create([...]); $model->update([...]);` must
 * refresh first, or its audit entry will list defaults that never moved.
 */
trait LogsAuditTrail
{
    use LogsActivity;

    /**
     * Attributes that must never reach the activity log. A model with a
     * secret of its own extends this rather than replacing it.
     *
     * @var list<string>
     */
    protected array $auditExcludedAttributes = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        // Bookkeeping, not content. A model that does not list them in `$guarded`
        // otherwise writes both into every entry, and a reviewer reads two
        // meaningless fields on a creation.
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty()
            ->logExcept($this->auditExcludedAttributes);

        return $this->auditSubmitsEmptyLogs() ? $options->submitEmptyLogs() : $options->dontSubmitEmptyLogs();
    }

    /**
     * Whether to write a row even when the edit leaves no loggable field behind.
     *
     * The default is no: an edit that only touched an excluded attribute is
     * indistinguishable from no edit, and logging it as "updated" with nothing
     * underneath is noise. A model whose most important change is exactly such a
     * field overrides this - a password change, for instance, is worth a row even
     * though the password itself can never appear in it.
     */
    protected function auditSubmitsEmptyLogs(): bool
    {
        return false;
    }

    /**
     * A sentence rather than an event slug, because that sentence is what a
     * reviewer reads and what AuditActionEnum recovers the verb from.
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return Str::headline(class_basename($this)).' '.Str::headline($eventName);
    }
}

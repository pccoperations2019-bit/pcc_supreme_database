<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

/**
 * The verb behind an audit entry.
 *
 * Activity events are written as free-form slugs (`document-submitted-for-review`),
 * so the action is recovered from the wording rather than from a registry of
 * event names. That keeps a new workflow transition from silently becoming
 * unfilterable just because nobody declared an enum case for it.
 */
enum AuditActionEnum: string implements WithLabels
{
    use HasLabels;

    case CREATED = 'created';
    case UPDATED = 'updated';
    case IMPORTED = 'imported';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DELETED = 'deleted';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case EXPORTED = 'exported';

    /**
     * Wording that identifies each action, in the order they are probed.
     *
     * Order matters: "resubmitted" contains "submitted" and "processing failed"
     * contains "failed", so the more specific verb has to win. Longer, more
     * specific phrases are therefore listed before their shorter substrings.
     *
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
        self::FAILED->value => ['failed', 'failure', 'errored', 'error'],
        self::REJECTED->value => ['rejected', 'rejects'],
        self::APPROVED->value => ['approved', 'approves'],
        self::DELETED->value => ['deleted', 'removed', 'destroyed'],
        // What a spreadsheet import leaves behind. "Submitted" was the old wording
        // for it, and it is what the uploads are called in the field: a document
        // submitted for review is a file waiting to be brought in, not an approval
        // step of its own. Placed before CREATED so "Imported" is never read as an
        // upload of a single row.
        self::IMPORTED->value => ['imported', 'submitted for review', 'submitted', 'submits'],
        self::PROCESSED->value => ['processing', 'processed', 'completed', 'started'],
        self::CREATED->value => ['created', 'uploaded', 'added'],
        // Generating a file is an audit action of its own, and it was falling
        // through every keyword above it - so a report a user downloaded left an
        // entry with no action at all, which the action filter cannot find.
        // Placed after 'started' and 'failed' on purpose: "Document Export
        // Started" and "Document Export Failed" are transitions, not an export.
        self::EXPORTED->value => ['exported', 'requested', 'generated', 'downloaded'],
        self::UPDATED->value => ['updated', 'changed', 'edited'],
    ];

    /**
     * Recover the action from an activity's human description or its event slug.
     *
     * The description is probed first because it is the sentence a PCC user
     * reads; the event is the fallback for rows written before a description
     * existed, or by code that only set an event.
     */
    public static function resolve(?string $description, ?string $event = null): ?self
    {
        foreach ([$description, $event] as $candidate) {
            if (blank($candidate)) {
                continue;
            }

            $haystack = str($candidate)->lower()->toString();

            foreach (self::KEYWORDS as $action => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str($haystack)->contains($keyword)) {
                        return self::from($action);
                    }
                }
            }
        }

        return null;
    }

    public static function labels(): array
    {
        return [
            self::CREATED->value => 'Created',
            self::UPDATED->value => 'Updated',
            self::IMPORTED->value => 'Imported',
            self::APPROVED->value => 'Approved',
            self::REJECTED->value => 'Rejected',
            self::DELETED->value => 'Deleted',
            self::PROCESSED->value => 'Processed',
            self::FAILED->value => 'Failed',
            self::EXPORTED->value => 'Exported',
        ];
    }

    /**
     * Every action that can be filtered on, in the order the filter panel lists
     * them. Unlike `cases()` this is ordered by how often a reviewer reaches for
     * the filter rather than by declaration order.
     *
     * @return list<string>
     */
    public static function filterable(): array
    {
        return [
            self::CREATED->value,
            self::UPDATED->value,
            self::IMPORTED->value,
            self::APPROVED->value,
            self::REJECTED->value,
            self::DELETED->value,
            self::PROCESSED->value,
            self::FAILED->value,
            self::EXPORTED->value,
        ];
    }
}

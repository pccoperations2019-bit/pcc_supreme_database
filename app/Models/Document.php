<?php

namespace App\Models;

use App\Enums\ComplianceStatusEnum;
use App\Enums\DocumentKindEnum;
use App\Enums\DocumentStatusEnum;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $guarded = [
        'id',
        'file_id',
        'created_at',
        'updated_at',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function tags(): MorphMany
    {
        return $this->morphMany(Tag::class, 'taggable');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function centerReviewedBy()
    {
        return $this->belongsTo(User::class, 'center_reviewed_by');
    }

    public function agencyReviewedBy()
    {
        return $this->belongsTo(User::class, 'agency_reviewed_by');
    }

    /**
     * Generated files carry a `report_type` tag; everything else is an upload
     * waiting to be imported, so the Import tab stays a complete catch-all.
     *
     * Named to avoid the `import` macro Laravel-Excel registers on the
     * Eloquent builder, which would swallow a scope of that name.
     */
    protected function scopeKindImport(Builder $query): void
    {
        $query->whereDoesntHave('tags', fn ($tags) => $tags->where('key', DocumentKindEnum::EXPORT_TAG_KEY));
    }

    /**
     * The positively-identified side: both GenerateReportController and the
     * export workflows tag generated documents with `report_type`.
     */
    protected function scopeKindExport(Builder $query): void
    {
        $query->whereHas('tags', fn ($tags) => $tags->where('key', DocumentKindEnum::EXPORT_TAG_KEY));
    }

    /**
     * Workflow audit trail, owned by spatie/laravel-activitylog. This is the
     * only history of what happened to a document; `metadata` holds current
     * operational state (queue timestamps, last processing error) only.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'validity' => 'date',
            'metadata' => 'array',
            'status' => DocumentStatusEnum::class,
            'compliance_status' => ComplianceStatusEnum::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Whether the document is waiting on a reviewer and must therefore stay out
     * of the import workflows.
     */
    public function isAwaitingReview(): bool
    {
        return in_array($this->status?->value, DocumentStatusEnum::awaitingReview(), true);
    }

    /**
     * Whether this document is awaiting a review decision, and may therefore be
     * approved or rejected by a reviewer.
     */
    public function isPendingReview(): bool
    {
        return $this->status === DocumentStatusEnum::PENDING_REVIEW;
    }

    /**
     * Whether the uploader may still edit it (draft, or a rejected document
     * being corrected).
     */
    public function isEditableByUploader(): bool
    {
        return in_array($this->status, [
            DocumentStatusEnum::DRAFT,
            DocumentStatusEnum::REJECTED,
            DocumentStatusEnum::REJECTED_BY_CENTER,
            DocumentStatusEnum::REJECTED_BY_AGENCY,
        ], true);
    }

    public function hasFinalApprovalForCurrentSubmission(): bool
    {
        if (! in_array($this->status, [
            DocumentStatusEnum::APPROVED,
            DocumentStatusEnum::PROCESSING,
            DocumentStatusEnum::PROCESSED,
        ], true)
            || ! $this->submission_version) {
            return false;
        }

        if (in_array($this->status, [DocumentStatusEnum::PROCESSING, DocumentStatusEnum::PROCESSED], true)
            && (int) $this->processing_submission_version !== (int) $this->submission_version) {
            return false;
        }

        // An agency-level document has no center/partner/farmer association, so
        // it never enters the center stage and the agency approval is final.
        if ($this->center_reviewed_by === null) {
            return $this->agency_reviewed_by !== null
                && $this->tags()->whereIn('key', ['center', 'partner', 'farmer'])->doesntExist();
        }

        return $this->agency_reviewed_by !== null
            && (int) $this->center_reviewed_by !== (int) $this->agency_reviewed_by;
    }

    protected function getProgressAttribute(): int
    {
        if ($this->status === DocumentStatusEnum::PROCESSED) {
            return 100;
        }

        if ($this->status !== DocumentStatusEnum::PROCESSING) {
            return 0;
        }

        $queuedAt = $this->metadata['queued_export']['queued_at']
            ?? $this->metadata['queue_import']['queued_at']
            ?? null;

        if (! $queuedAt) {
            return 0;
        }

        $elapsedSeconds = abs(now()->diffInSeconds(Date::parse($queuedAt)));

        $progress = 99 * (1 - exp(-$elapsedSeconds / 120));

        return min(99, (int) $progress);
    }

    protected function getDownloadUrlAttribute(): ?string
    {
        if ($this->status !== DocumentStatusEnum::PROCESSED || ! $this->file) {
            return null;
        }

        return route('reports.exports.download', $this);
    }

    public function isReadyForDownload(): bool
    {
        return $this->status === DocumentStatusEnum::PROCESSED && $this->file !== null;
    }
}

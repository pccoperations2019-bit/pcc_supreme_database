<?php

namespace App\Concerns;

use App\Enums\DocumentStatusEnum;
use App\Models\Document;
use App\Models\DocumentImportDispatch;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait HasQueueMetadata
{
    protected function setQueueMetadata(int $documentId, string $type): void
    {
        $document = Document::query()->find($documentId);
        if (! $document) {
            return;
        }

        $previousStatus = $document->status?->value;

        if ($type === 'import') {
            if ($document->status !== DocumentStatusEnum::PROCESSING
                || (int) $document->processing_submission_version !== (int) $document->submission_version
                || ! $document->hasFinalApprovalForCurrentSubmission()
                || ! DocumentImportDispatch::query()
                    ->where('document_id', $document->id)
                    ->where('submission_version', $document->submission_version)
                    ->exists()) {
                throw new RuntimeException('This document has no valid final approval for the current submission.');
            }
        }

        // Exports create their file as part of the run, so only imports can be
        // checked up front. Catching a missing source here reports a clear
        // reason instead of Excel failing on an unresolvable path.
        if ($type === 'import') {
            $this->guardSourceFileExists($document);
        }

        $metadataKey = $type === 'export' ? 'queued_export' : 'queue_import';
        $metadata = $document->metadata ?? [];

        $document->update([
            'status' => DocumentStatusEnum::PROCESSING,
            'metadata' => array_merge($metadata, [
                $metadataKey => array_merge($metadata[$metadataKey] ?? [], [
                    'queued_at' => now()->toIso8601String(),
                ]),
            ]),
        ]);

        // A workflow and its Excel import both call this, so only the call that
        // actually moves the document into processing is a transition worth
        // recording - otherwise the log fills with processing -> processing.
        if ($previousStatus !== DocumentStatusEnum::PROCESSING->value) {
            $this->logTransition(
                $document,
                DocumentStatusEnum::PROCESSING,
                $type === 'import' ? 'Document Processing Started' : 'Document Export Started',
                $previousStatus,
            );
        }
    }

    /**
     * Make sure the uploaded file is still on the disk the import reads from.
     *
     * Marks the document failed before throwing: at this point the status is
     * still whatever it was before processing (approved), which the shared
     * failure handler does not treat as "in flight".
     */
    protected function guardSourceFileExists(Document $document): void
    {
        $path = $document->file?->path;

        if ($path !== null && Storage::disk('dpm')->exists($path)) {
            return;
        }

        $message = sprintf(
            'The uploaded file for document #%d is missing from storage%s. Re-upload the document and try again.',
            $document->id,
            $path === null ? '' : " ({$path})"
        );

        $metadata = $document->metadata ?? [];
        $queuedImport = $metadata['queue_import'] ?? [];
        $previousStatus = $document->status?->value;

        $document->update([
            'status' => DocumentStatusEnum::FAILED,
            'metadata' => array_merge($metadata, [
                'queue_import' => array_merge($queuedImport, [
                    'failed_at' => now()->toIso8601String(),
                    'error' => $message,
                ]),
            ]),
        ]);

        $this->logTransition(
            $document,
            DocumentStatusEnum::FAILED,
            'Document Processing Failed',
            $previousStatus,
            ['error' => $message],
        );

        throw new RuntimeException($message);
    }

    protected function markAsProcessed(int $documentId): void
    {
        $document = Document::query()->find($documentId);
        if (! $document) {
            return;
        }

        $previousStatus = $document->status?->value;

        $document->update([
            'status' => DocumentStatusEnum::PROCESSED,
            'published_at' => now(),
        ]);

        if ($previousStatus !== DocumentStatusEnum::PROCESSED->value) {
            $this->logTransition(
                $document,
                DocumentStatusEnum::PROCESSED,
                'Document Processing Completed',
                $previousStatus,
            );
        }
    }

    /**
     * Record a processing transition in the activity log. Processing runs on the
     * queue, so there is no authenticated user to attribute these to.
     *
     * That is the right answer for these rows rather than a gap: who asked for the
     * work and who carried it out are two different questions, and the request
     * entry names the person while this one names the machine that did it.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function logTransition(
        Document $document,
        DocumentStatusEnum $newStatus,
        string $description,
        ?string $previousStatus,
        array $extra = [],
    ): void {
        activity()
            ->performedOn($document)
            ->withProperties(array_merge([
                'old_status' => $previousStatus,
                'new_status' => $newStatus->value,
            ], $extra))
            ->event(str($description)->slug()->toString())
            ->log($description);
    }
}

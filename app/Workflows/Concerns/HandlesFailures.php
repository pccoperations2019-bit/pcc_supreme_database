<?php

namespace App\Workflows\Concerns;

use App\Enums\DocumentStatusEnum;
use App\Models\Document;
use Illuminate\Support\Facades\Log;

trait HandlesFailures
{
    private bool $exportCompleted = false;

    private bool $importCompleted = false;

    public function registerExportShutdownHandler(Document $document): void
    {
        $documentId = $document->id;

        register_shutdown_function(function () use ($documentId) {
            // A shutdown handler must never fatal: doing so hides the real
            // error, and the container may already be torn down.
            try {
                Log::info("Export shutdown handler called for document {$documentId}");

                if ($this->exportCompleted) {
                    Log::info("Document {$documentId} already marked as completed");

                    return;
                }

                $error = error_get_last();
                if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                    Log::error("Fatal error detected for document {$documentId}: ".$error['message']);
                    $this->markDocumentAsFailed($documentId, $error['message'] ?? 'Fatal error during export', 'queued_export');
                }
            } catch (\Throwable $e) {
                // Nothing useful left to do at shutdown.
            }
        });
    }

    public function registerImportShutdownHandler(Document $document): void
    {
        $documentId = $document->id;

        register_shutdown_function(function () use ($documentId) {
            // A shutdown handler must never fatal: doing so hides the real
            // error, and the container may already be torn down.
            try {
                Log::info("Import shutdown handler called for document {$documentId}");

                if ($this->importCompleted) {
                    Log::info("Document {$documentId} already marked as completed");

                    return;
                }

                $error = error_get_last();
                if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                    Log::error("Fatal error detected during import for document {$documentId}: ".$error['message']);
                    $this->markDocumentAsFailed($documentId, $error['message'] ?? 'Fatal error during import', 'queue_import');
                }
            } catch (\Throwable $e) {
                // Nothing useful left to do at shutdown.
            }
        });
    }

    public function markExportCompleted(): void
    {
        $this->exportCompleted = true;
    }

    public function markImportCompleted(): void
    {
        $this->importCompleted = true;
    }

    /**
     * Mark a document as failed due to error.
     */
    private function markDocumentAsFailed(int $documentId, string $errorMessage, string $metadataKey): void
    {
        try {
            $doc = Document::find($documentId);
            if ($doc && ($doc->status === DocumentStatusEnum::PROCESSING || $doc->status === DocumentStatusEnum::DRAFT)) {
                $metadata = $doc->metadata ?? [];
                $previousStatus = $doc->status?->value;

                $doc->update([
                    'status' => DocumentStatusEnum::FAILED,
                    'metadata' => array_merge($metadata, [
                        $metadataKey => array_merge($metadata[$metadataKey] ?? [], [
                            'failed_at' => now()->toIso8601String(),
                            'error' => $errorMessage,
                        ]),
                    ]),
                ]);

                $isImport = $metadataKey === 'queue_import';

                activity()
                    ->performedOn($doc)
                    ->withProperties([
                        'old_status' => $previousStatus,
                        'new_status' => DocumentStatusEnum::FAILED->value,
                        'error' => $errorMessage,
                    ])
                    ->event($isImport ? 'document-processing-failed' : 'document-export-failed')
                    ->log($isImport ? 'Document Processing Failed' : 'Document Export Failed');

                Log::info("Document {$documentId} marked as FAILED");
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update document status: '.$e->getMessage());
        }
    }

    /**
     * Mark a document as failed due to exception.
     */
    protected function markDocumentAsFailedByException(int $documentId, string $errorMessage, string $metadataKey): void
    {
        $this->markDocumentAsFailed($documentId, $errorMessage, $metadataKey);
    }
}

<?php

namespace App\Imports\Concerns;

use App\Concerns\HasQueueMetadata;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

trait WithDocumentStatus
{
    use HasQueueMetadata {
        HasQueueMetadata::setQueueMetadata as protected setQueueMetadataWithApprovalGuard;
    }

    protected ?int $documentId = null;

    public function setDocumentId(int $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->setQueueMetadataWithApprovalGuard($this->documentId, 'import');
                info("Import started for document ID: {$this->documentId}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
                info("Import completed successfully for document ID: {$this->documentId}");
            },
        ];
    }
}

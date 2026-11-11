<?php

namespace App\Imports;

use App\Concerns\HasQueueMetadata;
use App\Imports\Concerns\WithDocumentStatus;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class DocumentTypeImport implements WithEvents
{
    use HasQueueMetadata, WithDocumentStatus {
        WithDocumentStatus::setQueueMetadata insteadof HasQueueMetadata;
        WithDocumentStatus::markAsProcessed insteadof HasQueueMetadata;
    }

    private string $documentType;

    private string $entityType;

    private string $entityCode;

    public function __construct(string $documentType, string $entityType, string $entityCode)
    {
        $this->documentType = $documentType;
        $this->entityType = $entityType;
        $this->entityCode = $entityCode;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->setQueueMetadata($this->documentId, 'import');
                info("Import started for document ID: {$this->documentId}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
                info("Import completed successfully for document ID: {$this->documentId}");
            },
        ];
    }
}

<?php

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class DairyBoxSalesImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    private array $sheetNames = [];

    public function onUnknownSheet($sheetName): void
    {
        // Log unknown sheets but continue processing
        info("Unknown sheet encountered: {$sheetName}");
    }

    public function setSheetNames(array $sheetNames): void
    {
        $this->sheetNames = $sheetNames;
    }

    public function sheets(): array
    {
        if ($this->sheetNames) {
            $sheets = [];

            foreach ($this->sheetNames as $sheetName) {
                $sheets[$sheetName] = new DairyBoxSalesSheetImport;
            }

            return $sheets;
        }

        return [];
    }

    public function registerEvents(): array
    {
        $documentStatusEvents = [
            BeforeImport::class => function (BeforeImport $event) {
                $this->setQueueMetadata($this->documentId, 'import');
                info("Dairy Box Sales import started for document ID: {$this->documentId}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
                info("Dairy Box Sales import completed successfully for document ID: {$this->documentId}");
            },
        ];

        $events = $documentStatusEvents;

        $events[BeforeImport::class] = function (BeforeImport $event) use ($documentStatusEvents) {
            HeadingRowFormatter::default('slug');

            if (isset($documentStatusEvents[BeforeImport::class]) && is_callable($documentStatusEvents[BeforeImport::class])) {
                $documentStatusEvents[BeforeImport::class]($event);
            }
        };

        return $events;
    }
}

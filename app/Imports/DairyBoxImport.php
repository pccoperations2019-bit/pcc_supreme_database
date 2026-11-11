<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\DairyBox\UpsertDairyBoxAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Imports\Concerns\WithDocumentStatus;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class DairyBoxImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
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
                $sheets[$sheetName] = new DairyBoxSheetImport(
                    app(UpsertDairyBoxAction::class),
                    app(UpsertAddressAction::class),
                    app(UpsertPartnerAction::class)
                );
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
                info("Dairy Box Masterlist import started for document ID: {$this->documentId}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
                info("Dairy Box Masterlist import completed successfully for document ID: {$this->documentId}");
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

<?php

namespace App\Imports;

use App\Concerns\HasQueueMetadata;
use App\Enums\ProgramEnum;
use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class ManureProductionImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use HasQueueMetadata, WithDocumentStatus {
        WithDocumentStatus::setQueueMetadata insteadof HasQueueMetadata;
        WithDocumentStatus::markAsProcessed insteadof HasQueueMetadata;
    }

    protected ProgramEnum $program;

    private array $sheetNames = [];

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setSheetNames(array $sheetNames): void
    {
        $this->sheetNames = $sheetNames;
    }

    public function sheets(): array
    {
        if ($this->sheetNames) {
            $sheets = [];
            $sheetNames = $this->sheetNames;

            foreach ($sheetNames as $sheetName) {
                $sheetLower = strtolower($sheetName);

                if (stripos($sheetLower, 'sales') !== false) {
                    $salesImport = new ManureSalesSheetImport;
                    $program = $this->getProgramFromSheetName($sheetName);
                    $salesImport->setProgram($program);
                    $sheets[$sheetName] = $salesImport;
                } else {
                    $productionImport = new ManureProductionSheetImport;
                    $program = $this->getProgramFromSheetName($sheetName);
                    $productionImport->setProgram($program);
                    $sheets[$sheetName] = $productionImport;
                }
            }

            return $sheets;
        }

        // Default: handle both Production and Sales sheets if they exist
        return [
            'Production' => new ManureProductionSheetImport,
            'Sales' => new ManureSalesSheetImport,
        ];
    }

    public function registerEvents(): array
    {
        $documentStatusEvents = [
            BeforeImport::class => function (BeforeImport $event) {
                $this->setQueueMetadata($this->documentId, 'import');
                info("Import started for document ID: {$this->documentId}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
                info("Import completed successfully for document ID: {$this->documentId}");
            },
        ];

        $events = $documentStatusEvents;

        $events[BeforeImport::class] = function (BeforeImport $event) use ($documentStatusEvents) {
            HeadingRowFormatter::default('none');

            if (isset($documentStatusEvents[BeforeImport::class]) && is_callable($documentStatusEvents[BeforeImport::class])) {
                $documentStatusEvents[BeforeImport::class]($event);
            }
        };

        $events[AfterImport::class] = function (AfterImport $event) use ($documentStatusEvents) {
            HeadingRowFormatter::default('slug');

            if (isset($documentStatusEvents[AfterImport::class]) && is_callable($documentStatusEvents[AfterImport::class])) {
                $documentStatusEvents[AfterImport::class]($event);
            }
        };

        return $events;
    }

    public function onUnknownSheet($sheetName): void
    {
        Log::warning("ManureProductionImport: Sheet '{$sheetName}' skipped.");
    }

    private function getProgramFromSheetName(string $sheetName): ProgramEnum
    {
        $lowerSheetName = strtolower($sheetName);
        if (stripos($lowerSheetName, 'cbed') !== false) {
            return ProgramEnum::CBED;
        }

        return ProgramEnum::IH; // default
    }
}

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

class MilkProduction4DXImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use HasQueueMetadata, WithDocumentStatus {
        WithDocumentStatus::setQueueMetadata insteadof HasQueueMetadata;
        WithDocumentStatus::markAsProcessed insteadof HasQueueMetadata;
    }

    protected ProgramEnum $program;

    private const SKIP_SHEETS = ['sheet10'];

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
            $sheetNames = array_filter($this->sheetNames, fn ($name) => ! in_array(strtolower(trim($name)), self::SKIP_SHEETS, true));

            foreach ($sheetNames as $sheetName) {
                $lowerSheetName = strtolower($sheetName);
                $program = ProgramEnum::IH;
                if (stripos($lowerSheetName, 'cbed') !== false) {
                    $program = ProgramEnum::CBED;
                } elseif (stripos($lowerSheetName, 'institutional') !== false || stripos($lowerSheetName, 'ih') !== false) {
                    $program = ProgramEnum::IH;
                }

                if (stripos($sheetName, 'sales') !== false) {
                    $salesImport = new MilkSales4DXSheetImport;
                    $salesImport->setProgram($program);
                    $sheets[$sheetName] = $salesImport;

                    continue;
                }

                $productionImport = new MilkProduction4DXSheetImport;
                $productionImport->setProgram($program);
                $sheets[$sheetName] = $productionImport;
            }

            return $sheets;
        }

        $productionImport = new MilkProduction4DXSheetImport;
        $salesImport = new MilkSales4DXSheetImport;

        if (isset($this->program)) {
            $productionImport->setProgram($this->program);
            $salesImport->setProgram($this->program);
        }

        return [
            0 => $productionImport,
            1 => $salesImport,
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
        Log::warning("MilkProduction4DXImport: Sheet '{$sheetName}' skipped - only production/sales sheets are processed.");
    }
}

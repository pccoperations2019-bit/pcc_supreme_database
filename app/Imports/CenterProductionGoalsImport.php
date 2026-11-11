<?php

namespace App\Imports;

use App\Enums\ProgramEnum;
use App\Imports\Concerns\WithDocumentStatus;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CenterProductionGoalsImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithConditionalSheets, WithDocumentStatus;

    private array $centerIds = [];

    private array $sheetNames = [];

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function setSheetNames(array $sheetNames): void
    {
        $this->sheetNames = $sheetNames;
    }

    public function conditionalSheets(): array
    {
        $sheets = [];

        foreach ($this->sheetNames as $sheetName) {
            $centerGoalsImport = new CenterGoalsImport;
            $centerGoalsImport->setCenterIds($this->centerIds);
            $centerGoalsImport->setProgram($this->parseProgramFromSheetName($sheetName));
            $centerGoalsImport->setYear($this->parseYearFromSheetName($sheetName));

            $sheets[$sheetName] = $centerGoalsImport;
        }

        return $sheets;
    }

    /**
     * Derive the program (IH/CBED) from a sheet name like "2021 CBED Goals".
     */
    protected function parseProgramFromSheetName(string $sheetName): ?ProgramEnum
    {
        if (preg_match('/\b(cbed)\b/i', $sheetName)) {
            return ProgramEnum::CBED;
        }

        if (preg_match('/\b(ih)\b/i', $sheetName)) {
            return ProgramEnum::IH;
        }

        return null;
    }

    /**
     * Derive the year from a sheet name like "2021 CBED Goals".
     */
    protected function parseYearFromSheetName(string $sheetName): ?int
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $sheetName, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

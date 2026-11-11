<?php

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MilkConsolidationImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    protected ?int $userId = null;

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function sheets(): array
    {
        $centers = DB::table('centers')
            ->select('code', 'id')
            ->get();

        $importClasses = [];

        foreach ($centers as $center) {
            $import = new MilkConsolidationSheetImport;
            $import->setCenterId($center->id);
            $import->setDocumentId($this->documentId);
            $import->setUserId($this->userId);
            $importClasses[$center->code] = $import;
        }

        return $importClasses;
    }

    public function onUnknownSheet($sheetName)
    {
        // Sheet skipped - no center found with matching code
    }
}

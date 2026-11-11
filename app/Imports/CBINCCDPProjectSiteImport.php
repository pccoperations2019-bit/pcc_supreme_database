<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * DPM wrapper for Projectsite.xlsx.
 *
 * The workbook has a single sheet with cleaned headers (no merged cells).
 * Data starts at row 2 with proper snake_case column names.
 */
class CBINCCDPProjectSiteImport implements WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    public function sheets(): array
    {
        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $sheet = new CBINCCDPSiteSheetImport;
        $sheet->setCenterIds($centerIds);

        return [
            0 => $sheet,
        ];
    }
}

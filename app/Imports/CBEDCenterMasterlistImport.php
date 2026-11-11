<?php

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CBEDCenterMasterlistImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    public function sheets(): array
    {
        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $centerServicesImport = new CBEDCenterServicesImport;
        $centerServicesImport->setCenterIds($centerIds);

        $centerProjectsImport = new CBEDCenterProjectImport;
        $centerProjectsImport->setCenterIds($centerIds);

        return [
            'Services' => $centerServicesImport,
            'Projects' => $centerProjectsImport,
            'Beneficiaries' => new BeneficiarySourcesImport,
        ];
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

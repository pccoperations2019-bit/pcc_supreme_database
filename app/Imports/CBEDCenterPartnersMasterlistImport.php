<?php

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CBEDCenterPartnersMasterlistImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    public function sheets(): array
    {
        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $importClasses = [];
        foreach ($centerIds as $centerCode => $centerId) {
            $partnerByCenter = new PartnersImport(
                centerId: $centerId,
                upsertPartnerAction: resolve('App\Actions\Partner\UpsertPartnerAction'),
                upsertAddressAction: resolve('App\Actions\Address\UpsertAddressAction'),
                createDocumentAction: resolve('App\Actions\Document\CreateDocumentAction'),
            );
            $importClasses[$centerCode] = $partnerByCenter;
        }

        return $importClasses;
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

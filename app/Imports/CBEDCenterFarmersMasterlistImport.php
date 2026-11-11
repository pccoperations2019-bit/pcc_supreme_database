<?php

namespace App\Imports;

use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CBEDCenterFarmersMasterlistImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    private array $validCenterCodes = [];

    public function __construct()
    {
        $this->validCenterCodes = DB::table('centers')
            ->pluck('id', 'code')
            ->toArray();
    }

    public function sheets(): array
    {
        $importClasses = [];

        foreach ($this->validCenterCodes as $centerCode => $centerId) {
            $farmerByCenter = new FarmersImport(
                centerId: $centerId,
                upsertFarmerAction: resolve('App\Actions\Farmer\UpsertFarmerAction'),
                upsertPartnerAction: resolve('App\Actions\Partner\UpsertPartnerAction'),
                upsertAddressAction: resolve('App\Actions\Address\UpsertAddressAction'),
                createDocumentAction: resolve('App\Actions\Document\CreateDocumentAction'),
            );
            $normalized = trim((string) $centerCode);
            $aliases = array_values(array_unique([
                (string) $centerCode,
                $normalized,
                strtoupper($normalized),
                strtolower($normalized),
            ]));

            foreach ($aliases as $alias) {
                if ($alias === '') {
                    continue;
                }
                $importClasses[$alias] = $farmerByCenter;
            }
        }

        return $importClasses;
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

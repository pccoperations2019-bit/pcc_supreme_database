<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Center\UpsertCenterAction;
use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IHCenterMasterlistImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithConditionalSheets,  WithDocumentStatus;

    public function conditionalSheets(): array
    {
        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $agencyId = DB::table('agencies')
            ->where('code', 'PCC')
            ->value('id');

        $centerImport = new CenterImport(
            agencyId: $agencyId,
            upsertCenterAction: resolve(UpsertCenterAction::class),
            upsertAddressAction: resolve(UpsertAddressAction::class),
        );

        $productionUnitImport = new ProductionUnitImport;
        $productionUnitImport->setCenterIds($centerIds);

        $consolidationUnitImport = new ConsolidationUnitImport;
        $consolidationUnitImport->setCenterIds($centerIds);

        $processingUnitImport = new ProcessingUnitImport;
        $processingUnitImport->setCenterIds($centerIds);

        $marketingUnitImport = new MarketingUnitImport;
        $marketingUnitImport->setCenterIds($centerIds);

        $centerProductsImport = new CenterProductsImport;
        $centerProductsImport->setCenterIds($centerIds);

        return [
            'Centers' => $centerImport,
            'Production' => $productionUnitImport,
            'Consolidation' => $consolidationUnitImport,
            'Processing' => $processingUnitImport,
            'Marketing' => $marketingUnitImport,
            'Products' => $centerProductsImport,
        ];
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

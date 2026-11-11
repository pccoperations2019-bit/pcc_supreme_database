<?php

namespace App\Imports;

use App\Enums\ProjectProgramEnum;
use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CBEDCenterMilkFeedingProgramImport implements SkipsUnknownSheets, WithEvents, WithMultipleSheets
{
    use WithDocumentStatus;

    public function sheets(): array
    {
        $projectId = DB::table('projects')
            ->where([
                'program' => ProjectProgramEnum::MFP->value,
            ])
            ->value('id');

        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $mfpDataImport = new CenterMilkFeedingProgramImport(
            projectId: $projectId,
            upsertMilkFeedingActionAction: resolve('App\Actions\Project\UpsertMilkFeedingProgramAction'),
            upsertPartnerAction: resolve('App\Actions\Partner\UpsertPartnerAction'),
            upsertAddressAction: resolve('App\Actions\Address\UpsertAddressAction')
        );
        $mfpDataImport->setCenterIds($centerIds);

        return [$mfpDataImport];
    }

    public function onUnknownSheet($sheetName)
    {
        info("Sheet {$sheetName} was skipped");
    }
}

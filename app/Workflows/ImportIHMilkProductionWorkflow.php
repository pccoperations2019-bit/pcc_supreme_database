<?php

namespace App\Workflows;

use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\ProgramEnum;
use App\Imports\MilkProductionImport;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportIHMilkProductionWorkflow
{
    public function resolve(string $taggableType, ?int $taggablId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggablId
        ) {
            return;
        }
        $document = Document::with('tags')->find($taggablId);

        $programTag = $document->tags->firstWhere('key', 'program');
        $programEnum = ProgramEnum::tryFrom($programTag?->value ?? '') ?? ProgramEnum::DEFAULT;

        $upsertMilkProductionAction = app(UpsertMilkProductionAction::class);
        $upsertMilkSaleAction = app(UpsertMilkSaleAction::class);
        $upsertPartnerAction = app(UpsertPartnerAction::class);

        $import = new MilkProductionImport(
            $upsertMilkProductionAction,
            $upsertMilkSaleAction,
            $upsertPartnerAction,
        );
        $import->setDocumentId($taggablId);
        $import->setProgram($programEnum);

        $localPath = Storage::disk('dpm')->path($document->file->path);
        $reader = IOFactory::createReaderForFile($localPath);
        $sheetNames = $reader->listWorksheetNames($localPath);
        $import->setSheetNames($sheetNames);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

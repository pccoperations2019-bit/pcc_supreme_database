<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\ProgramEnum;
use App\Imports\MilkProductionMonthlyImport;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMilkProductionMonthlyWorkflow
{
    use HasQueueMetadata;

    public function resolve(string $taggableType, ?int $taggablId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggablId
        ) {
            return;
        }

        $document = Document::with('tags')->find($taggablId);

        $this->setQueueMetadata($taggablId, 'import');

        $programTag = $document->tags->firstWhere('key', 'program');
        $programEnum = ProgramEnum::tryFrom($programTag?->value ?? '') ?? ProgramEnum::DEFAULT;

        $import = new MilkProductionMonthlyImport;
        $import->setDocumentId($taggablId);
        $import->setProgram($programEnum);

        $localPath = Storage::disk('dpm')->path($document->file->path);
        $reader = IOFactory::createReaderForFile($localPath);
        $sheetNames = $reader->listWorksheetNames($localPath);
        $import->setSheetNames($sheetNames);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

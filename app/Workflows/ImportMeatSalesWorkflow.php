<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\ProgramEnum;
use App\Imports\MeatSalesImport;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMeatSalesWorkflow
{
    use HasQueueMetadata;

    public function resolve(string $taggableType, ?int $taggableId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggableId
        ) {
            return;
        }

        $document = Document::with('tags')->find($taggableId);

        if (! $document || ! $document->file) {
            return;
        }

        $this->setQueueMetadata($taggableId, 'import');

        $programTag = $document->tags->firstWhere('key', 'program');
        $programEnum = ProgramEnum::tryFrom($programTag?->value ?? '') ?? ProgramEnum::DEFAULT;

        $import = new MeatSalesImport;
        $import->setDocumentId($taggableId);
        $import->setProgram($programEnum);

        $localPath = Storage::disk('dpm')->path($document->file->path);
        $reader = IOFactory::createReaderForFile($localPath);
        $sheetNames = $reader->listWorksheetNames($localPath);
        $import->setSheetNames($sheetNames);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

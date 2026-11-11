<?php

namespace App\Workflows;

use App\Imports\DairyBoxImport;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportDairyBoxWorkflow
{
    public function resolve(string $taggableType, ?int $taggablId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggablId
        ) {
            return;
        }

        $document = Document::find($taggablId);
        if (! $document || ! $document->file) {
            return;
        }

        $import = new DairyBoxImport;
        $import->setDocumentId($taggablId);

        $localPath = Storage::disk('dpm')->path($document->file->path);
        $reader = IOFactory::createReaderForFile($localPath);
        $sheetNames = $reader->listWorksheetNames($localPath);
        $import->setSheetNames($sheetNames);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

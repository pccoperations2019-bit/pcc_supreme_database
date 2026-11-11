<?php

namespace App\Workflows;

use App\Imports\MilkConsolidationImport;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportIHMilkConsolidationWorkflow
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

        $import = new MilkConsolidationImport;
        $import->setDocumentId($taggablId);
        $import->setUserId($document->file->uploaded_by ?? null);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

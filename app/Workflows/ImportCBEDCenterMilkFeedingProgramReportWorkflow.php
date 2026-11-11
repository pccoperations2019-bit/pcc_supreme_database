<?php

namespace App\Workflows;

use App\Imports\CBEDCenterMilkFeedingProgramImport;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportCBEDCenterMilkFeedingProgramReportWorkflow
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
        $import = new CBEDCenterMilkFeedingProgramImport;
        $import->setDocumentId($taggablId);
        Excel::import($import, $document->file->path, 'dpm');
    }
}

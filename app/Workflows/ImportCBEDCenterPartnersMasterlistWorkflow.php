<?php

namespace App\Workflows;

use App\Imports\CBEDCenterPartnersMasterlistImport;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportCBEDCenterPartnersMasterlistWorkflow
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
        $import = new CBEDCenterPartnersMasterlistImport;
        $import->setDocumentId($taggablId);
        Excel::import($import, $document->file->path, 'dpm');
    }
}

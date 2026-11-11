<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Imports\CBEDCenterFarmersMasterlistImport;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportCBEDCenterFarmersMasterlistWorkflow
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

        $document = Document::find($taggablId);

        $this->setQueueMetadata($taggablId, 'import');

        $import = new CBEDCenterFarmersMasterlistImport;
        $import->setDocumentId($taggablId);
        Excel::import($import, $document->file->path, 'dpm');
    }
}

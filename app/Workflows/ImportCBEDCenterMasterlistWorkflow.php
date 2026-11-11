<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Imports\CBEDCenterMasterlistImport;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportCBEDCenterMasterlistWorkflow
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

        $document = Document::find($taggableId);

        $this->setQueueMetadata($taggableId, 'import');

        $import = new CBEDCenterMasterlistImport;
        $import->setDocumentId($taggableId);
        Excel::import($import, $document->file->path, 'dpm');
    }
}

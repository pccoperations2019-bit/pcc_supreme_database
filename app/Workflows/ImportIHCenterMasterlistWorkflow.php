<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Imports\IHCenterMasterlistImport;
use App\Models\Center;
use App\Models\Document;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportIHCenterMasterlistWorkflow
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
        if (! $document || ! $document->file) {
            return;
        }

        $this->setQueueMetadata($taggablId, 'import');

        if (Center::count() === 0) {
            $import = new IHCenterMasterlistImport;
            $import->setDocumentId($taggablId);
            $import->onlySheets(['Centers']);
            Excel::import($import, $document->file->path, 'dpm');
        }
        $import = new IHCenterMasterlistImport;
        $import->setDocumentId($taggablId);
        $import->onlySheets([
            'Centers',
            'Production',
            'Consolidation',
            'Processing',
            'Marketing',
            'Products',
        ]);

        Excel::import($import, $document->file->path, 'dpm');
    }
}

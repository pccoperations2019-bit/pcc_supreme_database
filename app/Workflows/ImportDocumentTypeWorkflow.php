<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Enums\DocumentStatusEnum;
use App\Imports\DocumentTypeImport;
use App\Models\Document;
use App\Models\DocumentImportDispatch;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportDocumentTypeWorkflow
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
        if (! $document || ! $document->file) {
            return;
        }

        if ($document->status !== DocumentStatusEnum::PROCESSING
            || ! $document->hasFinalApprovalForCurrentSubmission()
            || ! DocumentImportDispatch::query()
                ->where('document_id', $document->id)
                ->where('submission_version', $document->submission_version)
                ->where('workflow_key', self::class)
                ->exists()) {
            return;
        }

        $documentTag = $document->tags()->where('key', 'document')->first();
        $entityTag = $document->tags()
            ->whereIn('key', ['center', 'partner', 'farmer'])
            ->first();

        if (! $documentTag || ! $entityTag) {
            return;
        }

        $fileExtension = strtolower(pathinfo($document->file->path, PATHINFO_EXTENSION));

        if (! in_array($fileExtension, ['xlsx', 'xls', 'csv'])) {
            $document->update([
                'status' => DocumentStatusEnum::PROCESSED,
                'published_at' => now(),
            ]);

            return;
        }

        $import = new DocumentTypeImport($documentTag->value, $entityTag->key, $entityTag->value);
        $import->setDocumentId($taggableId);

        $this->setQueueMetadata($taggableId, 'import');

        // The file is stored on the `dpm` disk, so the disk has to be named
        // here - otherwise Excel resolves the path against the default disk and
        // reports the file as missing.
        Excel::import($import, $document->file->path, 'dpm');
    }
}

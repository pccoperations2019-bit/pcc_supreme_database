<?php

namespace App\Actions\Document;

use App\Models\Document;
use Illuminate\Support\Facades\DB;

class UpdateDocumentAction
{
    /**
     * Update an existing document.
     */
    public function execute(
        Document $document,
        ?string $title = null,
        ?string $description = null,
        ?string $registrationNumber = null,
        ?string $validity = null,
        ?string $complianceStatus = null,
        ?string $fileName = null,
        ?string $fileKey = null,
        ?string $fileType = null,
        ?int $fileSize = null,
    ): Document {

        return DB::transaction(function () use (
            $document,
            $title,
            $description,
            $registrationNumber,
            $validity,
            $complianceStatus,
            $fileName,
            $fileKey,
            $fileType,
            $fileSize
        ) {

            $updateData = [
                'title' => $fileName ?? $title ?? $document->title,
                'description' => $description ?? $document->description,
                'registration_number' => $registrationNumber ?? $document->registration_number,
                'validity' => $validity ?? $document->validity,
                'compliance_status' => $complianceStatus !== null ? ($complianceStatus !== '' ? $complianceStatus : null) : $document->compliance_status,
            ];

            // If file is being updated, update the associated file
            if ($fileName && $fileKey && $fileType && $fileSize) {
                $file = $document->file;
                $file->update([
                    'name' => $fileName,
                    'path' => $fileKey,
                    'type' => $fileType,
                    'size' => $fileSize,
                ]);
            }

            $document->update($updateData);

            return $document->fresh();
        });
    }
}

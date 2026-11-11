<?php

namespace App\Actions\Document;

use App\Enums\DocumentEnum;
use App\Enums\DocumentStatusEnum;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\File;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

class CreateDocumentAction
{
    /**
     * Create a new document.
     */
    public function execute(
        string $title,
        string $fileName,
        string $fileKey,
        string $fileType,
        int $fileSize,
        int $uploadedBy,
        ?string $description = null,
        ?string $registrationNumber = null,
        ?string $validity = null,
        ?string $complianceStatus = null,
        ?DocumentStatusEnum $status = null,
        ?array $metadata = null,
    ): Document {

        return DB::transaction(function () use (
            $title,
            $fileName,
            $fileKey,
            $fileType,
            $fileSize,
            $uploadedBy,
            $description,
            $registrationNumber,
            $validity,
            $complianceStatus,
            $status,
            $metadata
        ) {

            $file = new File([
                'name' => $fileName,
                'path' => $fileKey,
                'type' => $fileType,
                'size' => $fileSize,
            ]);

            $file->uploadedBy()->associate($uploadedBy);
            $file->save();

            $document = new Document([
                'title' => $title,
                'description' => $description,
                'registration_number' => $registrationNumber,
                'validity' => $validity,
                'compliance_status' => $complianceStatus,
                'status' => $status ?? DocumentStatusEnum::DEFAULT,
                'metadata' => $metadata,
            ]);

            $document->file()->associate($file);
            $document->save();

            return $document;
        });
    }

    /**
     * Update entity boolean flags when a document is tagged.
     * This should be called after document tags are created.
     */
    public static function updateEntityBooleanFlags(Document $document): void
    {
        $documentType = $document->tags()->where('key', 'document')->value('value');

        if (! $documentType) {
            return;
        }

        $entityCode = null;
        $entityType = null;

        // Get entity from tags (partner, farmer, or center)
        $partnerTag = $document->tags()->where('key', 'partner')->first();
        $farmerTag = $document->tags()->where('key', 'farmer')->first();

        if ($partnerTag) {
            $entityCode = $partnerTag->value;
            $entityType = 'partner';
        } elseif ($farmerTag) {
            $entityCode = $farmerTag->value;
            $entityType = 'farmer';
        }

        if (! $entityCode || ! $entityType) {
            return;
        }

        // Map document types to boolean flag names
        $flagMap = [
            DocumentEnum::CSO->value => 'has_cso',
            DocumentEnum::FDA_LTO->value => 'has_fda_lto',
            DocumentEnum::NDA_LTO->value => 'has_nda_lto',
            DocumentEnum::FFEDIS->value => 'has_ffedis',
            DocumentEnum::RSBSA->value => 'has_rsbsa',
        ];

        $flagName = $flagMap[$documentType] ?? null;

        if (! $flagName) {
            return;
        }

        // Update the boolean flag on the entity
        if ($entityType === 'partner') {
            Partner::where('code', $entityCode)->update([$flagName => true]);
        } elseif ($entityType === 'farmer') {
            Farmer::where('code', $entityCode)->update([$flagName => true]);
        }
    }
}

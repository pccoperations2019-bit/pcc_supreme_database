<?php

namespace App\Exports\Directory\Support;

use App\Enums\ComplianceStatusEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Resolves accreditation documents using the correct tag schema.
 * Tag schema: taggable_type=document, taggable_id=document.id
 * Entity association: tags.key=center|partner|farmer, tags.value=<entity code>
 */
final class DirectoryAccreditationResolver
{
    /**
     * Get accreditation documents for entities using the correct tag schema.
     *
     * @param  array<string>  $entityCodes
     * @return Collection<array{entity_code: string, validity: string, compliance_status: string}>
     */
    public static function getDocuments(
        string $entityType,
        array $entityCodes,
        string $documentType,
    ): Collection {
        if ($entityCodes === []) {
            return collect();
        }

        $query = DB::table('documents')
            ->join('tags as doc_type_tags', function ($join) use ($documentType) {
                $join->on('documents.id', '=', 'doc_type_tags.taggable_id')
                    ->where('doc_type_tags.taggable_type', '=', 'document')
                    ->where('doc_type_tags.key', '=', 'document')
                    ->where('doc_type_tags.value', '=', $documentType);
            })
            ->join('tags as entity_tags', function ($join) use ($entityType) {
                $join->on('documents.id', '=', 'entity_tags.taggable_id')
                    ->where('entity_tags.taggable_type', '=', 'document')
                    ->where('entity_tags.key', '=', $entityType);
            })
            ->where('documents.status', 'processed')
            ->orderByDesc('documents.validity')
            ->orderByDesc('documents.id')
            ->select([
                'entity_tags.value as entity_code',
                'documents.validity',
                'documents.compliance_status',
            ]);

        // For centers, tags store codes. For partners/farmers, tags store IDs.
        if (in_array($entityType, ['partner', 'farmer'], true)) {
            $table = $entityType === 'partner' ? 'partners' : 'farmers';
            $query->join($table, function ($join) use ($table) {
                $join->on('entity_tags.value', '=', $table.'.id');
            })
            // Filter by ID for partners/farmers since tags store IDs
                ->whereIn('entity_tags.value', $entityCodes)
                ->select([
                    'entity_tags.value as entity_code',
                    'documents.validity',
                    'documents.compliance_status',
                ]);
        } else {
            $query->whereIn('entity_tags.value', $entityCodes);
        }

        return $query
            ->get()
            ->unique('entity_code')
            ->keyBy('entity_code');
    }

    /**
     * Format document validity date safely for string dates from DB::table().
     */
    public static function formatValidity(mixed $validity): string
    {
        if ($validity instanceof \DateTimeInterface) {
            return $validity->format('Y-m-d');
        }

        if ($validity === null || trim((string) $validity) === '') {
            return '';
        }

        // Handle string dates from DB::table() queries
        try {
            return Date::parse((string) $validity)->toDateString();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Resolve accreditation status from compliance status.
     */
    public static function resolveStatus(mixed $status, bool $fallback): string
    {
        if ($status instanceof ComplianceStatusEnum) {
            return $status->label();
        }

        $normalizedStatus = strtolower(trim((string) $status));
        if ($normalizedStatus === 'compliant') {
            return ComplianceStatusEnum::ACCREDITED->label();
        }

        $complianceStatus = ComplianceStatusEnum::tryFrom($normalizedStatus);

        return $complianceStatus?->label()
            ?? ($normalizedStatus !== ''
                ? ucwords(str_replace(['_', '-'], ' ', $normalizedStatus))
                : ($fallback ? ComplianceStatusEnum::ACCREDITED->label() : ''));
    }

    /**
     * Get boolean accreditation label.
     */
    public static function booleanLabel(bool $accredited): string
    {
        return $accredited ? ComplianceStatusEnum::ACCREDITED->label() : '';
    }
}

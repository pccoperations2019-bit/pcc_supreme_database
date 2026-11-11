<?php

namespace App\Support\Documents;

use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;

class DocumentOwnershipResolver
{
    /**
     * Resolve ownership from persisted center/entity relations, never from an
     * unvalidated request payload. Ambiguous or stale tags fail closed.
     *
     * @return array{center: Center, agency_id: int}|null
     */
    public function resolve(Document $document): ?array
    {
        $centerIds = [];

        foreach ($document->tags()->whereIn('key', ['center', 'partner', 'farmer'])->get() as $tag) {
            $centerId = match ($tag->key) {
                'center' => Center::query()->where('code', $tag->value)->value('id'),
                'partner' => Partner::query()->where('code', $tag->value)->value('center_id'),
                'farmer' => Farmer::query()->where('code', $tag->value)->value('center_id'),
                default => null,
            };

            if ($centerId === null) {
                return null;
            }

            $centerIds[(int) $centerId] = true;
        }

        if (count($centerIds) !== 1) {
            return null;
        }

        $center = Center::query()->with('agency')->find(array_key_first($centerIds));

        if (! $center || ! $center->agency_id) {
            return null;
        }

        return ['center' => $center, 'agency_id' => (int) $center->agency_id];
    }
}

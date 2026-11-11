<?php

namespace App\Queries\Dashboard;

use App\Enums\DocumentEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use Illuminate\Support\Collection;

class DocumentCountQuery
{
    public function execute(?int $centerId = null, ?ProgramEnum $program = null): Collection
    {
        $documentCounts = [];

        $program = $program ?? ProgramEnum::DEFAULT;
        $entityKeys = $program === ProgramEnum::CBED
            ? ['partner', 'farmer']
            : ['center'];

        // Documents are tagged with entity *codes*, not ids, so a center-scoped
        // CBED count has to resolve the center's partner/farmer codes first.
        $entityCodesByKey = $centerId
            ? $this->entityCodesByKey($centerId, $entityKeys)
            : [];

        foreach (DocumentEnum::cases() as $certification) {
            $propertyName = strtolower($certification->name).'_compliance';

            $query = Document::whereHas('tags', function ($query) use ($certification) {
                $query->where('key', 'document')->where('value', $certification->value);
            });

            // Scope to entity type(s) based on program
            $query->whereHas('tags', function ($query) use ($entityKeys, $entityCodesByKey) {
                if ($entityCodesByKey === []) {
                    $query->whereIn('key', $entityKeys);

                    return;
                }

                $query->where(function ($query) use ($entityCodesByKey) {
                    foreach ($entityCodesByKey as $key => $codes) {
                        if ($codes === []) {
                            continue;
                        }

                        $query->orWhere(function ($query) use ($key, $codes) {
                            $query->where('key', $key)->whereIn('value', $codes);
                        });
                    }
                });
            });

            $documentCounts[$propertyName] = $query->count();
        }

        return collect($documentCounts);
    }

    /**
     * Resolve the partner/farmer codes belonging to a center.
     *
     * @return array<string, array<int, string>>
     */
    private function entityCodesByKey(int $centerId, array $entityKeys): array
    {
        if (! in_array('center', $entityKeys, true)) {
            return [
                'partner' => Partner::where('center_id', $centerId)
                    ->whereNotNull('code')->pluck('code')->all(),
                'farmer' => Farmer::where('center_id', $centerId)->pluck('code')->all(),
            ];
        }

        $centerCode = Center::find($centerId)?->code;

        return ['center' => $centerCode !== null ? [$centerCode] : []];
    }
}

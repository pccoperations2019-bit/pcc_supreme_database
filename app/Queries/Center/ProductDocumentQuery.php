<?php

namespace App\Queries\Center;

use App\Models\Center;
use App\Models\Document;
use App\Models\Partner;

class ProductDocumentQuery
{
    public function productRegistrationDocumentsByCode(Center|Partner $owner): array
    {
        $codes = $owner->products()
            ->whereNotNull('code')
            ->pluck('code')
            ->values()
            ->all();

        if ($codes === []) {
            return [];
        }

        $docs = Document::query()
            ->with(['tags', 'file', 'file.uploadedBy'])
            ->whereHas('tags', function ($query) use ($codes) {
                $query->where('key', 'product')->whereIn('value', $codes);
            })
            ->whereHas('tags', function ($query) {
                $query->where('key', 'document')->where('value', 'product_registration');
            })
            ->get();

        return $docs
            ->mapWithKeys(function ($doc) {
                $code = $doc->tags->firstWhere('key', 'product')?->value;

                return $code ? [$code => $doc] : [];
            })
            ->all();
    }
}

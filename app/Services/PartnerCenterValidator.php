<?php

namespace App\Services;

use App\Models\Partner;

class PartnerCenterValidator
{
    /**
     * A partner/center pair is valid when either isn't set, or when the
     * chosen partner actually belongs to the chosen center.
     */
    public function belongsToCenter(?int $partnerId, ?int $centerId): bool
    {
        if (! $partnerId || ! $centerId) {
            return true;
        }

        $partner = Partner::query()->select(['id', 'center_id'])->find($partnerId);

        return $partner && (int) $partner->center_id === $centerId;
    }
}

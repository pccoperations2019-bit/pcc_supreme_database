<?php

namespace App\Exports\Directory\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves value chain units using grouped queries instead of per-row exists().
 */
final class DirectoryValueChainResolver
{
    /**
     * Get value chain labels for centers.
     *
     * @param  array<int>  $centerIds
     * @return array<int, string>
     */
    public static function getForCenters(array $centerIds): array
    {
        if ($centerIds === []) {
            return [];
        }

        $hasProduction = DB::table('production_units')
            ->whereIn('center_id', $centerIds)
            ->pluck('center_id')
            ->unique()
            ->flip()
            ->all();

        $hasProcessing = DB::table('processing_units')
            ->whereIn('center_id', $centerIds)
            ->pluck('center_id')
            ->unique()
            ->flip()
            ->all();

        $hasConsolidation = DB::table('consolidation_units')
            ->whereIn('center_id', $centerIds)
            ->pluck('center_id')
            ->unique()
            ->flip()
            ->all();

        $hasMarketing = DB::table('marketing_units')
            ->whereIn('center_id', $centerIds)
            ->pluck('center_id')
            ->unique()
            ->flip()
            ->all();

        $result = [];
        foreach ($centerIds as $centerId) {
            $chain = [];
            if (isset($hasProduction[$centerId])) {
                $chain[] = 'Production';
            }
            if (isset($hasProcessing[$centerId])) {
                $chain[] = 'Processing';
            }
            if (isset($hasConsolidation[$centerId])) {
                $chain[] = 'Consolidation';
            }
            if (isset($hasMarketing[$centerId])) {
                $chain[] = 'Marketing';
            }
            $result[$centerId] = implode(' ', $chain);
        }

        return $result;
    }

    /**
     * Get value chain labels for partners.
     *
     * @param  array<int>  $partnerIds
     * @return array<int, string>
     */
    public static function getForPartners(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        $hasProduction = DB::table('production_units')
            ->whereIn('partner_id', $partnerIds)
            ->pluck('partner_id')
            ->unique()
            ->flip()
            ->all();

        $hasProcessing = DB::table('processing_units')
            ->whereIn('partner_id', $partnerIds)
            ->pluck('partner_id')
            ->unique()
            ->flip()
            ->all();

        $hasConsolidation = DB::table('consolidation_units')
            ->whereIn('partner_id', $partnerIds)
            ->pluck('partner_id')
            ->unique()
            ->flip()
            ->all();

        $hasMarketing = DB::table('marketing_units')
            ->whereIn('partner_id', $partnerIds)
            ->pluck('partner_id')
            ->unique()
            ->flip()
            ->all();

        $result = [];
        foreach ($partnerIds as $partnerId) {
            $chain = [];
            if (isset($hasProduction[$partnerId])) {
                $chain[] = 'Production';
            }
            if (isset($hasProcessing[$partnerId])) {
                $chain[] = 'Processing';
            }
            if (isset($hasConsolidation[$partnerId])) {
                $chain[] = 'Consolidation';
            }
            if (isset($hasMarketing[$partnerId])) {
                $chain[] = 'Marketing';
            }
            $result[$partnerId] = implode(' ', $chain);
        }

        return $result;
    }
}

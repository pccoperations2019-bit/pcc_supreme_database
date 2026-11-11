<?php

namespace App\Exports\Directory\Support;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Resolves engagement dates for MFP and Dairy Box programs.
 */
final class DirectoryEngagementResolver
{
    /**
     * Get MFP engagement for centers.
     *
     * @param  array<int>  $centerIds
     * @return array<int, array{engaged: string, date: string}>
     */
    public static function getCenterMfpEngagement(array $centerIds): array
    {
        if ($centerIds === []) {
            return [];
        }

        return DB::table('milk_feeding_programs')
            ->whereIn('center_id', $centerIds)
            ->select('center_id', DB::raw('MIN(date_started) as first_engagement'))
            ->groupBy('center_id')
            ->get()
            ->keyBy('center_id')
            ->map(fn ($item) => [
                'engaged' => 'Yes',
                'date' => $item->first_engagement ? Date::parse($item->first_engagement)->format('Y') : '',
            ])
            ->all();
    }

    /**
     * Get MFP engagement for partners.
     *
     * @param  array<int>  $partnerIds
     * @return array<int, array{engaged: string, date: string}>
     */
    public static function getPartnerMfpEngagement(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        return DB::table('milk_feeding_programs')
            ->whereIn('partner_id', $partnerIds)
            ->select('partner_id', DB::raw('MIN(date_started) as first_engagement'))
            ->groupBy('partner_id')
            ->get()
            ->keyBy('partner_id')
            ->map(fn ($item) => [
                'engaged' => 'Yes',
                'date' => $item->first_engagement ? Date::parse($item->first_engagement)->format('Y') : '',
            ])
            ->all();
    }

    /**
     * Get MFP engagement for farmers (inherited from partners).
     *
     * @param  array<int>  $farmerIds
     * @return array<int, array{engaged: string, date: string}>
     */
    public static function getFarmerMfpEngagement(array $farmerIds): array
    {
        if ($farmerIds === []) {
            return [];
        }

        return DB::table('farmers')
            ->join('partners', 'farmers.partner_id', '=', 'partners.id')
            ->join('milk_feeding_programs', 'partners.id', '=', 'milk_feeding_programs.partner_id')
            ->whereIn('farmers.id', $farmerIds)
            ->select('farmers.id', DB::raw('MIN(milk_feeding_programs.date_started) as first_engagement'))
            ->groupBy('farmers.id')
            ->get()
            ->keyBy('id')
            ->map(fn ($item) => [
                'engaged' => 'Yes',
                'date' => $item->first_engagement ? Date::parse($item->first_engagement)->format('Y') : '',
            ])
            ->all();
    }

    /**
     * Get Dairy Box engagement for partners.
     *
     * @param  array<int>  $partnerIds
     * @return array<int, array{engaged: string, date: string}>
     */
    public static function getPartnerDairyBoxEngagement(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        return DB::table('dairy_boxes')
            ->whereIn('partner_id', $partnerIds)
            ->select('partner_id', DB::raw('MIN(launched_date) as first_engagement'))
            ->groupBy('partner_id')
            ->get()
            ->keyBy('partner_id')
            ->map(fn ($item) => [
                'engaged' => 'Yes',
                'date' => $item->first_engagement ? Date::parse($item->first_engagement)->format('Y') : '',
            ])
            ->all();
    }

    /**
     * Get KNP engagement for partners (intentional stub - no data schema).
     *
     * @return array<int, array{engaged: string, date: string}>
     */
    public static function getPartnerKnpEngagement(): array
    {
        // This would need to be implemented based on actual KNP data structure
        // Keeping as typed stub as per plan
        return [];
    }
}

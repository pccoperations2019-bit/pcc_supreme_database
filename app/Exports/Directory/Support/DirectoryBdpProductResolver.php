<?php

namespace App\Exports\Directory\Support;

use App\Enums\ProductCategoryEnum;
use Illuminate\Support\Facades\DB;

/**
 * Resolves Buffalo Derived Products (Milk/Meat/Manure/Hide) for
 * directory export variant "products" by inspecting actual BDP
 * production tables instead of the often-empty center_product pivot.
 *
 * Falls back to center_product/partner_product if production tables are empty,
 * so the resolver remains compatible with environments where the pivot is populated.
 */
final class DirectoryBdpProductResolver
{
    /**
     * @param  array<int>  $centerIds
     * @return array<int, array<int, string>>
     */
    public static function getForCenters(array $centerIds): array
    {
        if ($centerIds === []) {
            return [];
        }

        // Try production tables first – this is the canonical BDP source.
        $fromProduction = self::getForCentersFromProduction($centerIds);
        if (array_filter($fromProduction) !== []) {
            return $fromProduction;
        }

        // Fallback to legacy pivot.
        return DirectoryProductResolver::getForCenters($centerIds);
    }

    /**
     * @param  array<int>  $partnerIds
     * @return array<int, array<int, string>>
     */
    public static function getForPartners(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        $fromProduction = self::getForPartnersFromProduction($partnerIds);
        if (array_filter($fromProduction) !== []) {
            return $fromProduction;
        }

        return DirectoryProductResolver::getForPartners($partnerIds);
    }

    /**
     * @param  array<int>  $farmerIds
     * @return array<int, array<int, string>>
     */
    public static function getForFarmers(array $farmerIds): array
    {
        if ($farmerIds === []) {
            return [];
        }

        $fromProduction = self::getForFarmersFromProduction($farmerIds);
        if (array_filter($fromProduction) !== []) {
            return $fromProduction;
        }

        return DirectoryProductResolver::getForFarmers($farmerIds);
    }

    /**
     * @param  array<int>  $centerIds
     * @return array<int, array<int, string>>
     */
    private static function getForCentersFromProduction(array $centerIds): array
    {
        $map = array_fill_keys($centerIds, []);

        $milk = DB::table('milk_productions')
            ->whereIn('center_id', $centerIds)
            ->whereNotNull('center_id')
            ->select('center_id')
            ->distinct()
            ->pluck('center_id')
            ->all();
        foreach ($milk as $id) {
            $map[$id][] = ProductCategoryEnum::MILK->value;
        }

        $meat = DB::table('meat_productions')
            ->whereIn('center_id', $centerIds)
            ->select('center_id')
            ->distinct()
            ->pluck('center_id')
            ->all();
        foreach ($meat as $id) {
            $map[$id][] = ProductCategoryEnum::MEAT->value;
        }

        $manure = DB::table('manure_productions')
            ->whereIn('center_id', $centerIds)
            ->select('center_id')
            ->distinct()
            ->pluck('center_id')
            ->all();
        foreach ($manure as $id) {
            $map[$id][] = ProductCategoryEnum::MANURE->value;
        }

        // Also consider sales as secondary indicator (if production missing but sales exist)
        if (empty($milk) || empty($meat) || empty($manure)) {
            $milkSales = DB::table('milk_sales')->whereIn('center_id', $centerIds)->distinct()->pluck('center_id')->all();
            foreach ($milkSales as $id) {
                if (! in_array(ProductCategoryEnum::MILK->value, $map[$id] ?? [], true)) {
                    $map[$id][] = ProductCategoryEnum::MILK->value;
                }
            }
            $meatSales = DB::table('meat_sales')->whereIn('center_id', $centerIds)->distinct()->pluck('center_id')->all();
            foreach ($meatSales as $id) {
                if (! in_array(ProductCategoryEnum::MEAT->value, $map[$id] ?? [], true)) {
                    $map[$id][] = ProductCategoryEnum::MEAT->value;
                }
            }
            $manureSales = DB::table('manure_sales')->whereIn('center_id', $centerIds)->distinct()->pluck('center_id')->all();
            foreach ($manureSales as $id) {
                if (! in_array(ProductCategoryEnum::MANURE->value, $map[$id] ?? [], true)) {
                    $map[$id][] = ProductCategoryEnum::MANURE->value;
                }
            }
        }

        // Preserve order milk, meat, manure, hide and dedupe
        foreach ($map as $id => $cats) {
            $map[$id] = collect($cats)->unique()->values()->all();
            // Ensure empty stays empty → no fallback placeholder here; caller formats.
        }

        // Filter to only centers that actually have at least one product? Keep all keys so blank can be detected.
        return $map;
    }

    private static function getForPartnersFromProduction(array $partnerIds): array
    {
        $map = array_fill_keys($partnerIds, []);

        $milk = DB::table('milk_productions')
            ->whereIn('partner_id', $partnerIds)
            ->select('partner_id')
            ->distinct()
            ->pluck('partner_id')
            ->all();
        foreach ($milk as $id) {
            $map[$id][] = ProductCategoryEnum::MILK->value;
        }

        // Meat/manure at partner level are typically null (center-only) but check anyway
        $meat = DB::table('meat_productions')->whereIn('partner_id', $partnerIds)->distinct()->pluck('partner_id')->all();
        foreach ($meat as $id) {
            $map[$id][] = ProductCategoryEnum::MEAT->value;
        }
        $manure = DB::table('manure_productions')->whereIn('partner_id', $partnerIds)->distinct()->pluck('partner_id')->all();
        foreach ($manure as $id) {
            $map[$id][] = ProductCategoryEnum::MANURE->value;
        }

        // Sales fallback
        $milkSales = DB::table('milk_sales')->whereIn('partner_id', $partnerIds)->distinct()->pluck('partner_id')->all();
        foreach ($milkSales as $id) {
            if (! in_array(ProductCategoryEnum::MILK->value, $map[$id] ?? [], true)) {
                $map[$id][] = ProductCategoryEnum::MILK->value;
            }
        }

        foreach ($map as $id => $cats) {
            $map[$id] = collect($cats)->unique()->values()->all();
        }

        return $map;
    }

    private static function getForFarmersFromProduction(array $farmerIds): array
    {
        $map = array_fill_keys($farmerIds, []);

        $milk = DB::table('milk_productions')
            ->whereIn('farmer_id', $farmerIds)
            ->select('farmer_id')
            ->distinct()
            ->pluck('farmer_id')
            ->all();
        foreach ($milk as $id) {
            $map[$id][] = ProductCategoryEnum::MILK->value;
        }

        $meat = DB::table('meat_productions')->whereIn('farmer_id', $farmerIds)->distinct()->pluck('farmer_id')->all();
        foreach ($meat as $id) {
            $map[$id][] = ProductCategoryEnum::MEAT->value;
        }
        $manure = DB::table('manure_productions')->whereIn('farmer_id', $farmerIds)->distinct()->pluck('farmer_id')->all();
        foreach ($manure as $id) {
            $map[$id][] = ProductCategoryEnum::MANURE->value;
        }

        $milkSales = DB::table('milk_sales')->whereIn('farmer_id', $farmerIds)->distinct()->pluck('farmer_id')->all();
        foreach ($milkSales as $id) {
            if (! in_array(ProductCategoryEnum::MILK->value, $map[$id] ?? [], true)) {
                $map[$id][] = ProductCategoryEnum::MILK->value;
            }
        }

        foreach ($map as $id => $cats) {
            $map[$id] = collect($cats)->unique()->values()->all();
        }

        return $map;
    }
}

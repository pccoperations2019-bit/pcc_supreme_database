<?php

namespace App\Exports\Directory\Support;

use App\Enums\ProductCategoryEnum;
use Illuminate\Support\Facades\DB;

/**
 * Resolves product categories for entities using grouped queries.
 */
final class DirectoryProductResolver
{
    /**
     * Get product categories for centers.
     *
     * @param  array<int>  $centerIds
     * @return array<int, array<int, string>>
     */
    public static function getForCenters(array $centerIds): array
    {
        if ($centerIds === []) {
            return [];
        }

        return DB::table('center_product')
            ->join('products', 'center_product.product_id', '=', 'products.id')
            ->whereIn('center_product.center_id', $centerIds)
            ->select([
                'center_product.center_id as entity_id',
                'products.category as product_category',
            ])
            ->get()
            ->groupBy('entity_id')
            ->map(fn ($group) => $group->pluck('product_category')->unique()->values()->all())
            ->all();
    }

    /**
     * Get product categories for partners.
     *
     * @param  array<int>  $partnerIds
     * @return array<int, array<int, string>>
     */
    public static function getForPartners(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        return DB::table('partner_product')
            ->join('products', 'partner_product.product_id', '=', 'products.id')
            ->whereIn('partner_product.partner_id', $partnerIds)
            ->select([
                'partner_product.partner_id as entity_id',
                'products.category as product_category',
            ])
            ->get()
            ->groupBy('entity_id')
            ->map(fn ($group) => $group->pluck('product_category')->unique()->values()->all())
            ->all();
    }

    /**
     * Get product categories for farmers (inherited from partners).
     *
     * @param  array<int>  $farmerIds
     * @return array<int, array<int, string>>
     */
    public static function getForFarmers(array $farmerIds): array
    {
        if ($farmerIds === []) {
            return [];
        }

        return DB::table('farmers')
            ->join('partners', 'farmers.partner_id', '=', 'partners.id')
            ->join('partner_product', 'partners.id', '=', 'partner_product.partner_id')
            ->join('products', 'partner_product.product_id', '=', 'products.id')
            ->whereIn('farmers.id', $farmerIds)
            ->select([
                'farmers.id as entity_id',
                'products.category as product_category',
            ])
            ->get()
            ->groupBy('entity_id')
            ->map(fn ($group) => $group->pluck('product_category')->unique()->values()->all())
            ->all();
    }

    /**
     * Format product list as comma-separated labels.
     *
     * @param  array<int, string>  $productCategories
     */
    public static function formatList(array $productCategories): string
    {
        return collect($productCategories)
            ->map(function ($category) {
                if ($category instanceof ProductCategoryEnum) {
                    return $category->label();
                }

                $value = trim($category);
                if ($value === '') {
                    return null;
                }

                return ProductCategoryEnum::tryFrom(strtolower($value))?->label() ?? $value;
            })
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
    }
}

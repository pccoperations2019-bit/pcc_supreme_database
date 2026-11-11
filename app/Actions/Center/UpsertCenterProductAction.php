<?php

namespace App\Actions\Center;

use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Models\Product;

class UpsertCenterProductAction
{
    public function execute(
        string $code,
        ProductCategoryEnum $category,
        ?ProductSubCategoryEnum $subCategory,
        string $name,
        ?string $size,
        float $costPerQty,
        ?string $details,
        array $centerIds
    ): void {

        $product = Product::updateOrCreate(
            [
                'code' => $code,
            ],
            [
                'name' => $name,
                'category' => $category,
                'sub_category' => $subCategory,
                'size' => $size,
                'cost_per_qty' => $costPerQty,
                'details' => $details,
            ]
        );

        $product->centers()->syncWithoutDetaching($centerIds);
    }
}

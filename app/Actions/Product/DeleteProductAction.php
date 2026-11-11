<?php

namespace App\Actions\Product;

use App\Models\Center;
use App\Models\Partner;
use App\Models\Product;

class DeleteProductAction
{
    public function delete(Product $product): void
    {
        $product->centers()->detach();
        $product->partners()->detach();
        $product->delete();
    }

    public function detachFrom(Product $product, string $relation, int $parentId): void
    {
        $product->{$relation}()->detach($parentId);

        if ($product->centers()->doesntExist() && $product->partners()->doesntExist()) {
            $product->delete();
        }
    }

    public function removeFromCenter(Center $center, Product $product): void
    {
        $this->detachFrom($product, 'centers', $center->id);
    }

    public function removeFromPartner(Partner $partner, Product $product): void
    {
        $this->detachFrom($product, 'partners', $partner->id);
    }
}

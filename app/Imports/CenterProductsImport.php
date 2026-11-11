<?php

namespace App\Imports;

use App\Actions\Center\UpsertCenterProductAction;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CenterProductsImport implements OnEachRow, WithCalculatedFormulas, WithHeadingRow
{
    public $centerIds = [];

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        Log::info('Importing Center Product: ', $row->toArray());
        $productCode = $row['code'] ?? null;
        if (! isset($productCode) || empty($productCode)) {
            $productCode = Product::generateCode(
                $row['category'],
                $row['sub_category'] ?? ''
            );
        }

        resolve(UpsertCenterProductAction::class)->execute(
            code: $productCode,
            category: ProductCategoryEnum::tryFrom(Str::of($row['category'])->lower()->slug('_')),
            subCategory: ProductSubCategoryEnum::tryFrom(Str::of($row['sub_category'])->lower()->slug('_')),
            name: $row['name'],
            size: $row['size'] ?? null,
            costPerQty: is_numeric($row['cost_per_qty']) ? floatval($row['cost_per_qty']) : 0.00,
            details: $row['details'] ?? null,
            centerIds: array_values($this->centerIds)
        );
    }
}

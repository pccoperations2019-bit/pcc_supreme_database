<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Tag\CreateTagAction;
use App\Enums\HideProductTypeEnum;
use App\Enums\ManureProductTypeEnum;
use App\Enums\MeatProductTypeEnum;
use App\Enums\MilkProductTypeEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Center;
use App\Models\Document;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = QueryBuilder::for(Product::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('sub_category'),
            ])
            ->allowedSorts(['name', 'code', 'category', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Products/Index', [
            'products' => $products,
            // The listing shows a product's name, which is the raw product-type
            // value ("fresh_milk"). The labels are shipped here so the table can
            // render "Fresh Milk" without duplicating the enums on the client.
            'enums' => [
                'productCategory' => ProductCategoryEnum::labels(),
                'productSubCategory' => ProductSubCategoryEnum::labels(),
                'milkProductType' => MilkProductTypeEnum::labels(),
                'meatProductType' => MeatProductTypeEnum::labels(),
                'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                'manureProductType' => ManureProductTypeEnum::labels(),
                'hideProductType' => HideProductTypeEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, CreateDocumentAction $createDocumentAction, CreateTagAction $createTagAction)
    {
        $validated = $request->validated();

        $center = $this->resolveParent(Center::class, 'center', $request);
        $partner = $this->resolveParent(Partner::class, 'partner', $request);

        if ($center === null && $partner === null) {
            abort(404);
        }

        $category = ProductCategoryEnum::from($validated['category']);
        $subCategory = isset($validated['sub_category']) && $validated['sub_category'] !== ''
            ? ProductSubCategoryEnum::from($validated['sub_category'])
            : null;

        $code = $validated['code'] ?? null;
        if ($code === null || $code === '') {
            $code = Product::generateCode($category->value, $subCategory?->value);
        }

        // Use product_type as name if name is not provided
        $name = $validated['name'] ?? $validated['product_type'];

        $product = Product::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'category' => $category,
                'sub_category' => $subCategory,
                'product_type' => $validated['product_type'] ?? null,
                'product_registration' => $validated['product_registration'] ?? null,
                'size' => $validated['size'] ?? null,
                'cost_per_qty' => isset($validated['cost_per_qty']) ? (float) $validated['cost_per_qty'] : 0.00,
                'details' => $validated['details'] ?? null,
            ]
        );

        if ($center !== null) {
            $product->centers()->syncWithoutDetaching([$center->id]);
        }

        if ($partner !== null) {
            $product->partners()->syncWithoutDetaching([$partner->id]);
        }

        if (! empty($validated['registration_file_key'])) {
            $document = $createDocumentAction->execute(
                title: $validated['registration_file_name'] ?? 'Product Registration',
                fileName: $validated['registration_file_name'],
                fileKey: $validated['registration_file_key'],
                fileType: $validated['registration_file_type'],
                fileSize: (int) $validated['registration_file_size'],
                uploadedBy: $request->user()->id,
                description: 'Product Registration PDF',
                registrationNumber: $validated['product_registration'] ?? null
            );

            $taggableType = Str::of(class_basename(Document::class))->lower();
            $createTagAction->execute('product', $product->code, $taggableType, (string) $document->id);
            $createTagAction->execute('document', 'product_registration', $taggableType, (string) $document->id);

            if ($center !== null) {
                $createTagAction->execute('center', $center->code, $taggableType, (string) $document->id);
            }

            if ($partner !== null) {
                $createTagAction->execute('partner', $partner->code, $taggableType, (string) $document->id);
            }
        }

        return back()->with('success', 'Product created successfully.');
    }

    private function resolveParent(string $model, string $param, $request): Center|Partner|null
    {
        $value = $request->route($param);

        if ($value === null) {
            return null;
        }

        return $value instanceof $model ? $value : $model::query()->findOrFail((int) $value);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($parent, Product $product)
    {
        $product->load(['centers', 'partners']);

        if ($product->centers->isNotEmpty()) {
            $center = $product->centers->first();
            $center->load(['products']);

            return Inertia::render('Products/Edit', [
                'center' => $center,
                'product' => $product,
                'enums' => [
                    'productCategory' => ProductCategoryEnum::labels(),
                    'productSubCategory' => ProductSubCategoryEnum::labels(),
                    'milkProductType' => MilkProductTypeEnum::labels(),
                    'meatProductType' => MeatProductTypeEnum::labels(),
                    'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                    'manureProductType' => ManureProductTypeEnum::labels(),
                    'hideProductType' => HideProductTypeEnum::labels(),
                ],
            ]);
        } else {
            $partner = $product->partners->first();
            $partner->load(['products']);

            return Inertia::render('Products/Edit', [
                'partner' => $partner,
                'product' => $product,
                'enums' => [
                    'productCategory' => ProductCategoryEnum::labels(),
                    'productSubCategory' => ProductSubCategoryEnum::labels(),
                    'milkProductType' => MilkProductTypeEnum::labels(),
                    'meatProductType' => MeatProductTypeEnum::labels(),
                    'meatProductTypeCategories' => MeatProductTypeEnum::categories(),
                    'manureProductType' => ManureProductTypeEnum::labels(),
                    'hideProductType' => HideProductTypeEnum::labels(),
                ],
            ]);
        }
    }

    public function update(UpdateProductRequest $request, $parent, Product $product, CreateDocumentAction $createDocumentAction, CreateTagAction $createTagAction)
    {
        $validated = $request->validated();

        $center = $this->resolveParent(Center::class, 'center', $request);
        $partner = $this->resolveParent(Partner::class, 'partner', $request);

        // Use product_type as name if name is not provided
        $name = $validated['name'] ?? $validated['product_type'] ?? $product->name;

        $product->update([
            'code' => $validated['code'] ?? $product->code,
            'name' => $name,
            'category' => $validated['category'] ?? $product->category,
            'sub_category' => $validated['sub_category'] ?? $product->sub_category,
            'product_type' => $validated['product_type'] ?? $product->product_type,
            'product_registration' => $validated['product_registration'] ?? $product->product_registration,
            'size' => $validated['size'] ?? $product->size,
            'cost_per_qty' => isset($validated['cost_per_qty'])
                ? (float) $validated['cost_per_qty']
                : $product->cost_per_qty,
            'details' => $validated['details'] ?? $product->details,
        ]);

        if ($center !== null) {
            $product->centers()->syncWithoutDetaching([$center->id]);
        }

        if ($partner !== null) {
            $product->partners()->syncWithoutDetaching([$partner->id]);
        }

        if (! empty($validated['registration_file_key']) && ($center !== null || $partner !== null)) {
            $document = $createDocumentAction->execute(
                title: $validated['registration_file_name'] ?? 'Product Registration',
                fileName: $validated['registration_file_name'],
                fileKey: $validated['registration_file_key'],
                fileType: $validated['registration_file_type'],
                fileSize: (int) $validated['registration_file_size'],
                uploadedBy: $request->user()->id,
                description: 'Product Registration PDF',
                registrationNumber: $validated['product_registration'] ?? null
            );

            $taggableType = Str::of(class_basename(Document::class))->lower();
            $createTagAction->execute('product', $product->code, $taggableType, (string) $document->id);
            $createTagAction->execute('document', 'product_registration', $taggableType, (string) $document->id);

            if ($center !== null) {
                $createTagAction->execute('center', $center->code, $taggableType, (string) $document->id);
            }

            if ($partner !== null) {
                $createTagAction->execute('partner', $partner->code, $taggableType, (string) $document->id);
            }
        }

        return back()->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product, DeleteProductAction $deleteProductAction)
    {
        $deleteProductAction->delete($product);

        return back()->with('success', 'Product deleted successfully.');
    }

    public function destroyFromCenter(Center $center, Product $product, DeleteProductAction $deleteProductAction)
    {
        $deleteProductAction->detachFrom($product, 'centers', $center->id);

        return back()->with('success', 'Product removed successfully.');
    }

    public function destroyFromPartner(Partner $partner, Product $product, DeleteProductAction $deleteProductAction)
    {
        $deleteProductAction->detachFrom($product, 'partners', $partner->id);

        return back()->with('success', 'Product removed successfully.');
    }
}

<?php

namespace Tests\Feature\Products;

use App\Enums\MilkProductTypeEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A product's stored `name` is the raw product-type value (`fresh_milk`), and
 * every table that lists one - the products index and the product tables inside
 * the center and partner forms - resolves it to a label on the client. That only
 * works if each of those pages is given the label maps, so this pins that they
 * are: a page that ships products without them renders `fresh_milk` again.
 */
class ProductLabelOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Center $center;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::AGENCY_ADMIN->value, 'web');

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);

        Product::query()->create([
            'code' => 'MIL-00001',
            'name' => MilkProductTypeEnum::FRESH_MILK->value,
            'category' => ProductCategoryEnum::MILK,
        ]);

        $this->pccAdmin = User::create([
            'agency_id' => $agency->id,
            'center_id' => null,
            'name' => 'pcc-admin',
            'username' => 'pcc-admin',
            'email' => 'pcc-admin@pcc.gov.ph',
            'password' => Hash::make('password'),
        ]);

        $this->pccAdmin->assignRole(UserRoleEnum::AGENCY_ADMIN->value);
    }

    public function test_the_products_index_ships_the_label_maps(): void
    {
        $props = $this->props(route('products.index'));

        $this->assertSame(
            'Fresh Milk',
            $props['enums']['milkProductType'][MilkProductTypeEnum::FRESH_MILK->value],
        );

        // Each category needs its own map, because the same stored name means
        // different things per product type.
        foreach (['milkProductType', 'meatProductType', 'manureProductType', 'hideProductType'] as $key) {
            $this->assertNotEmpty($props['enums'][$key], "{$key} was not shipped.");
        }
    }

    public function test_the_products_index_lists_products_with_their_stored_name(): void
    {
        $props = $this->props(route('products.index'));

        // The raw value is still what the page receives - the label is a display
        // concern - so the test asserts the two halves are both available.
        $product = collect($props['products']['data'])->firstWhere('code', 'MIL-00001');

        $this->assertNotNull($product);
        $this->assertSame('fresh_milk', $product['name']);
        $this->assertSame('milk', $product['category']);
    }

    public function test_the_partner_edit_page_ships_the_label_maps(): void
    {
        $partner = Partner::create([
            'center_id' => $this->center->id,
            'code' => 'PCC-KO',
            'name' => 'Kosi Milk Cooperative',
        ]);

        $props = $this->props(route('partners.edit', $partner));

        $this->assertSame(
            'Fresh Milk',
            $props['enums']['milkProductType'][MilkProductTypeEnum::FRESH_MILK->value],
        );
    }

    public function test_the_center_edit_page_ships_the_label_maps(): void
    {
        $props = $this->props(route('centers.edit', $this->center));

        $this->assertSame(
            'Fresh Milk',
            $props['enums']['milkProductType'][MilkProductTypeEnum::FRESH_MILK->value],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $url): array
    {
        return $this->actingAs($this->pccAdmin)
            ->get($url)
            ->assertOk()
            ->viewData('page')['props'];
    }
}

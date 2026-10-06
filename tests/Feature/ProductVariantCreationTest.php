<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductVariantCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_creation_page_and_modal_offer_both_product_types(): void
    {
        $user = $this->branchUser($this->branch('A'));
        $this->actingAs($user)->get(route('products.create'))
            ->assertOk()->assertSee('value="variant"', false)->assertSee('منتج بمتغيرات');
        $this->get(route('products.index'))
            ->assertOk()->assertSee('value="variant"', false)->assertSee('منتج بمتغيرات');
    }

    public function test_variant_product_is_saved_with_branch_and_opens_variant_creation(): void
    {
        $branch = $this->branch('A');
        $user = $this->branchUser($branch);
        $response = $this->actingAs($user)->post(route('products.store'), $this->payload('variant'));
        $product = Product::query()->sole();

        $response->assertSessionHasNoErrors()->assertRedirect(route('products.variants.create', $product));
        $this->assertSame(ProductType::VARIANT, $product->product_type);
        $this->assertNotEmpty($product->sku);
        $this->assertNotEmpty($product->barcode);
        $this->assertDatabaseHas('location_products', [
            'product_id' => $product->id, 'location_id' => $branch->id, 'is_available' => true,
        ]);
        $this->get(route('products.variants.create', $product))->assertOk();
        $this->post(route('products.variants.store', $product), [
            'name' => 'Small', 'name_ar' => 'صغير', 'selling_price' => 12,
            'is_active' => 1, 'is_default' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('products.variants.index', $product));
        $variant = $product->variants()->sole();
        $this->assertSame('12.00', $variant->selling_price);
        $this->assertNotSame($product->sku, $variant->sku);
        $this->assertNotSame($product->barcode, $variant->barcode);
    }

    public function test_standard_product_keeps_normal_save_redirect(): void
    {
        $user = $this->branchUser($this->branch('A'));
        $this->actingAs($user)->post(route('products.store'), $this->payload('standard'))
            ->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
        $this->assertSame(ProductType::STANDARD, Product::query()->sole()->product_type);
    }

    public function test_create_only_user_is_not_redirected_to_forbidden_variant_editor(): void
    {
        $user = $this->branchUser($this->branch('A'), ['products.view', 'products.create']);
        $this->actingAs($user)->post(route('products.store'), $this->payload('variant'))
            ->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
        $product = Product::query()->sole();
        $this->get(route('products.variants.create', $product))->assertForbidden();
    }

    public function test_branch_user_cannot_manage_variants_of_another_branch(): void
    {
        $user = $this->branchUser($this->branch('A'));
        $otherUser = $this->branchUser($this->branch('B'));
        $this->actingAs($otherUser)->post(route('products.store'), $this->payload('variant'))
            ->assertSessionHasNoErrors();
        $product = Product::query()->sole();
        $this->actingAs($user)->get(route('products.variants.index', $product))->assertForbidden();
        $this->get(route('products.variants.create', $product))->assertForbidden();
        $this->post(route('products.variants.store', $product), ['name' => 'Private'])->assertForbidden();
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_edit_preserves_variant_type_and_rejects_downgrade_with_existing_variants(): void
    {
        $user = $this->branchUser($this->branch('A'));
        $payload = $this->payload('variant');
        $this->actingAs($user)->post(route('products.store'), $payload)->assertSessionHasNoErrors();
        $product = Product::query()->sole();
        $this->put(route('products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(ProductType::VARIANT, $product->fresh()->product_type);

        $this->post(route('products.variants.store', $product), ['name' => 'Small'])->assertSessionHasNoErrors();
        $this->put(route('products.update', $product), array_replace($payload, ['product_type' => 'standard']))
            ->assertSessionHasErrors('product_type');
        $this->assertSame(ProductType::VARIANT, $product->fresh()->product_type);
        $this->assertDatabaseCount('product_variants', 1);
    }

    private function payload(string $type): array
    {
        $category = Category::query()->firstOrCreate(['slug' => 'variant-test'], [
            'name' => 'Variant test', 'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();

        return [
            'name' => 'Test cake', 'name_ar' => 'كيك تجريبي',
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'product_type' => $type, 'base_selling_price' => 10,
        ];
    }

    private function branch(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'Branch '.$suffix, 'code' => 'VAR-'.$suffix,
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function branchUser(Location $branch, ?array $permissions = null): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach ($permissions ?? ['products.view', 'products.create', 'products.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

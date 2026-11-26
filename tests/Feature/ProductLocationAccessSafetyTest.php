<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductLocationAccessSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function setupCatalog(): array
    {
        $branchA = Location::create(['name' => 'فرع أول', 'code' => 'P-SAFE-A', 'type' => 'branch', 'is_active' => true]);
        $branchB = Location::create(['name' => 'فرع ثانٍ', 'code' => 'P-SAFE-B', 'type' => 'branch', 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => 'product-safe'], ['name' => 'منتجات تجريبية', 'is_active' => true]);
        $unit = Unit::where('code', 'piece')->firstOrFail();
        $product = Product::create([
            'name' => 'منتج اختبار',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'unit' => $unit->code,
            'product_type' => 'standard',
            'base_selling_price' => 25,
            'sku' => 'SAFE-CODE-1',
            'barcode' => '1234567890128',
            'is_active' => true,
        ]);
        $a = LocationProduct::create(['product_id' => $product->id, 'location_id' => $branchA->id, 'is_available' => true, 'local_selling_price' => 20, 'minimum_stock_level' => 2]);
        $b = LocationProduct::create(['product_id' => $product->id, 'location_id' => $branchB->id, 'is_available' => true, 'local_selling_price' => 22, 'minimum_stock_level' => 3]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branchA->id, ['is_primary' => true]);
        foreach (['products.view', 'products.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return [$branchA, $branchB, $product, $a, $b, $user];
    }

    public function test_saving_price_does_not_disable_product_in_location(): void
    {
        [, , , $a, , $user] = $this->setupCatalog();
        $this->actingAs($user)->put(route('location-products.update', $a), [
            'local_selling_price' => 28,
            'minimum_stock_level' => 5,
        ])->assertRedirect();
        $this->assertTrue($a->fresh()->is_available);
        $this->assertSame('28.00', $a->fresh()->local_selling_price);
    }

    public function test_resetting_branch_price_inherits_future_base_changes_without_touching_other_branch(): void
    {
        [$branchA, $branchB, $product, $a, $b, $user] = $this->setupCatalog();

        $this->actingAs($user)->put(route('location-products.update', $a), [
            'local_selling_price' => '',
        ])->assertRedirect();
        $this->assertNull($a->fresh()->local_selling_price);
        $this->assertTrue($a->fresh()->is_available);
        $this->assertSame('25.00', $product->fresh()->getEffectivePriceForLocation($branchA->id));

        $product->update(['base_selling_price' => 30]);
        $this->assertSame('30.00', $product->fresh()->getEffectivePriceForLocation($branchA->id));
        $this->assertSame('22.00', $product->fresh()->getEffectivePriceForLocation($branchB->id));
        $this->assertSame('22.00', $b->fresh()->local_selling_price);
    }

    public function test_branch_cannot_update_or_disable_other_branch_product_link(): void
    {
        [, , , , $b, $user] = $this->setupCatalog();
        $this->actingAs($user)->put(route('location-products.update', $b), [
            'local_selling_price' => 1,
        ])->assertForbidden();
        $this->delete(route('location-products.destroy', $b))->assertForbidden();
        $this->assertSame('22.00', $b->fresh()->local_selling_price);
        $this->assertTrue($b->fresh()->is_available);
    }

    public function test_product_status_cannot_be_double_toggled_from_stale_form(): void
    {
        [, , $product, , , $user] = $this->setupCatalog();
        $this->actingAs($user)->post(route('products.toggle-status', $product), ['expected_active' => '1'])->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $this->post(route('products.toggle-status', $product), ['expected_active' => '1'])->assertSessionHas('error');
        $this->assertFalse($product->fresh()->is_active);
    }
}

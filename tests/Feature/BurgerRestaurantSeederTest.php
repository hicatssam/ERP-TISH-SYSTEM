<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use Database\Seeders\BurgerRestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BurgerRestaurantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_seed_builds_the_complete_burger_catalogue_and_menu(): void
    {
        $this->seed(BurgerRestaurantSeeder::class);
        $this->assertSame(7, Category::count());
        $this->assertSame(68, Product::count());
        $this->assertSame(68, RestaurantMenuItem::count());
        $this->assertDatabaseCount('menu_banners', 3);
        $this->assertDatabaseHas('products', ['sku' => 'BRG-BEEF-CLASSIC', 'name_ar' => 'برجر لحم كلاسيك']);
        $this->assertDatabaseHas('products', ['sku' => 'BRG-FRIES-CHEESE']);
        $this->assertDatabaseHas('products', ['sku' => 'BRG-DRINKS-WATER']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventories', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rerunning_preserves_manual_prices_availability_and_deletions(): void
    {
        $this->seed(BurgerRestaurantSeeder::class);
        $product = Product::where('sku', 'BRG-BEEF-CLASSIC')->firstOrFail();
        $product->update(['base_selling_price' => 99]);
        $banner = \App\Models\MenuBanner::query()->firstOrFail();
        $banner->update(['title' => 'Edited banner', 'is_active' => false]);
        RestaurantMenuItem::where('product_id', $product->id)->update(['is_active' => false]);
        Product::where('sku', 'BRG-FRIES-CHEESE')->firstOrFail()->delete();

        $this->seed(BurgerRestaurantSeeder::class);
        $this->assertSame(68, Product::withTrashed()->count());
        $this->assertSame('99.00', $product->fresh()->base_selling_price);
        $this->assertSame('Edited banner', $banner->fresh()->title);
        $this->assertFalse($banner->fresh()->is_active);
        $this->assertFalse(RestaurantMenuItem::where('product_id', $product->id)->firstOrFail()->is_active);
        $this->assertTrue(Product::withTrashed()->where('sku', 'BRG-FRIES-CHEESE')->firstOrFail()->trashed());
        $this->assertSame(68, RestaurantMenuItem::count());
    }

    public function test_existing_catalogue_and_branches_are_preserved_and_each_active_branch_gets_the_menu(): void
    {
        $category = Category::create(['name' => 'Existing', 'slug' => 'existing']);
        Product::create(['category_id' => $category->id, 'name' => 'Existing item', 'sku' => 'EXISTING', 'barcode' => 'EXISTING-TEST', 'base_selling_price' => 50]);
        foreach (['B1', 'B2'] as $code) {
            Location::create(['code' => $code, 'name' => $code, 'type' => 'branch', 'is_active' => true]);
        }
        $this->seed(BurgerRestaurantSeeder::class);
        $this->assertSame(2, Location::count());
        $this->assertSame(136, RestaurantMenuItem::count());
        $this->assertDatabaseCount('menu_banners', 6);
        $this->assertDatabaseHas('products', ['sku' => 'EXISTING', 'base_selling_price' => 50]);
        $this->assertDatabaseHas('categories', ['slug' => 'existing', 'name' => 'Existing']);
        $this->seed(BurgerRestaurantSeeder::class);
        $this->assertSame(136, RestaurantMenuItem::count());
    }
}

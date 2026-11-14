<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantMenuItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ModuleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BurgerRestaurantDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_is_a_complete_restaurant_fixture_without_legacy_sweets(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(76, Product::where('is_raw_material', false)->count());
        $this->assertSame(76, Recipe::where('status', 'approved')->count());
        $this->assertSame(152, RestaurantMenuItem::count());
        $this->assertSame(12, Order::count());
        $this->assertSame(12, Invoice::count());
        $this->assertSame(8, Product::where('sku', 'like', 'BRG-SAUCES-%')->count());
        $this->assertSame(['B01'], Location::query()->orderBy('code')->pluck('code')->all());
        $branchId = Location::query()->where('code', 'B01')->value('id');
        $this->assertSame(1, \App\Models\RestaurantArea::query()->where('location_id', $branchId)->count());
        $this->assertSame('HALL', \App\Models\RestaurantArea::query()->where('location_id', $branchId)->value('code'));
        $this->assertSame(['KITCHEN', 'PREP'], \App\Models\KitchenStation::query()
            ->where('location_id', $branchId)->orderBy('code')->pluck('code')->all());
        $this->assertSame(6, Order::where('restaurant_service_type', 'dine_in')->count());
        $this->assertSame(6, Order::where('restaurant_service_type', 'takeaway')->count());
        $this->assertSame('0', (string) SystemSetting::get('customer_menu_allow_delivery'));
        $this->assertDatabaseHas('recipe_items', [
            'recipe_id' => Recipe::where('code', 'RCP-BRG-SAUCES-RANCH')->value('id'),
            'ingredient_product_id' => Product::where('sku', 'BRG-ING-RANCH')->value('id'),
            'quantity' => .05,
        ]);
        $this->assertDatabaseCount('special_cake_orders', 0);
        $this->assertDatabaseCount('showroom_sweets_requests', 0);
        $this->assertDatabaseCount('showroom_cake_requests', 0);
        $this->assertSame(0, Product::where('sku', 'not like', 'BRG-%')->count());
        $this->assertSame(0, Category::where('slug', 'not like', 'burger-%')->count());
        $this->assertSame('restaurant', SystemSetting::get('business_profile_code'));
        $this->assertFalse(app(ModuleService::class)->isEnabled('bakery'));
        $this->assertFalse(app(ModuleService::class)->isEnabled('cake_orders'));
        $this->assertTrue(app(ModuleService::class)->isEnabled('restaurant_pos'));
        $this->assertSame(8, Order::where('status', 'completed')->count());
        $this->assertGreaterThan(0, DB::table('order_inventory_consumptions')->count());
        $this->assertGreaterThan(0, DB::table('stock_movements')->where('reason', 'order_sale')->count());
        $this->assertGreaterThan(0, DB::table('kitchen_tickets')->count());
        $this->assertGreaterThan(0, DB::table('sales_ledger_entries')->count());
        $this->assertSame(0, DB::table('inventories')->where('quantity', '<', 0)->count());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('TishDemo@2026!', User::where('username', 'admin')->firstOrFail()->password));
        $this->assertFalse(Location::where('name', 'like', '%دهب%')->exists());

        foreach (['B01'] as $branchCode) {
            $menu = $this->get('/menu/'.$branchCode.'?branch_selected=1')->assertOk();
            $menu->assertViewHas('menuItems', fn ($items) => $items->count() === 76
                && $items->every(fn ($item) => str_starts_with($item['sku'], 'BRG-')
                    && str_contains((string) $item['image'], '/images/burger-menu/products/')));
            $menu->assertViewHas('categories', fn ($categories) => $categories->count() === 8
                && $categories->every(fn ($category) => ! empty($category['image'])));
            $menu->assertViewHas('banners', fn ($banners) => $banners->count() === 3);
        }

        $quantities = DB::table('inventories')->orderBy('id')->pluck('quantity', 'id')->all();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(24, Order::count());
        $this->assertSame($quantities, DB::table('inventories')->orderBy('id')->pluck('quantity', 'id')->all());
        $this->assertSame(76, Recipe::count());
    }

    public function test_full_seed_refuses_to_mix_legacy_records_into_the_restaurant_fixture(): void
    {
        $category = Category::create(['name' => 'Cake', 'slug' => 'cakes']);
        Product::create(['category_id' => $category->id, 'sku' => 'OLD-CAKE', 'barcode' => 'OLD-CAKE', 'name' => 'Old cake']);
        $this->expectException(\RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }
}

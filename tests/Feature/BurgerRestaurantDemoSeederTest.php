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
        $this->assertSame(68, Product::where('is_raw_material', false)->count());
        $this->assertSame(68, Recipe::where('status', 'approved')->count());
        $this->assertSame(136, RestaurantMenuItem::count());
        $this->assertSame(24, Order::count());
        $this->assertSame(24, Invoice::count());
        $this->assertDatabaseCount('special_cake_orders', 0);
        $this->assertDatabaseCount('showroom_sweets_requests', 0);
        $this->assertDatabaseCount('showroom_cake_requests', 0);
        $this->assertSame(0, Product::where('sku', 'not like', 'BRG-%')->count());
        $this->assertSame(0, Category::where('slug', 'not like', 'burger-%')->count());
        $this->assertSame('restaurant', SystemSetting::get('business_profile_code'));
        $this->assertFalse(app(ModuleService::class)->isEnabled('bakery'));
        $this->assertFalse(app(ModuleService::class)->isEnabled('cake_orders'));
        $this->assertTrue(app(ModuleService::class)->isEnabled('restaurant_pos'));
        $this->assertSame(16, Order::where('status', 'completed')->count());
        $this->assertGreaterThan(0, DB::table('order_inventory_consumptions')->count());
        $this->assertGreaterThan(0, DB::table('stock_movements')->where('reason', 'order_sale')->count());
        $this->assertGreaterThan(0, DB::table('kitchen_tickets')->count());
        $this->assertGreaterThan(0, DB::table('sales_ledger_entries')->count());
        $this->assertSame(0, DB::table('inventories')->where('quantity', '<', 0)->count());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('TishDemo@2026!', User::where('username', 'admin')->firstOrFail()->password));
        $this->assertFalse(Location::where('name', 'like', '%دهب%')->exists());

        $quantities = DB::table('inventories')->orderBy('id')->pluck('quantity', 'id')->all();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(24, Order::count());
        $this->assertSame($quantities, DB::table('inventories')->orderBy('id')->pluck('quantity', 'id')->all());
        $this->assertSame(68, Recipe::count());
    }

    public function test_full_seed_refuses_to_mix_legacy_records_into_the_restaurant_fixture(): void
    {
        $category = Category::create(['name' => 'Cake', 'slug' => 'cakes']);
        Product::create(['category_id' => $category->id, 'sku' => 'OLD-CAKE', 'barcode' => 'OLD-CAKE', 'name' => 'Old cake']);
        $this->expectException(\RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }
}

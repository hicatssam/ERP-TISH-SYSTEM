<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantMenuItem;
use Database\Seeders\TishBreakfastMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TishBreakfastMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_breakfast_starters_are_created_for_active_branches_without_activating_sales(): void
    {
        Location::create(['name' => 'Tish B01', 'code' => 'B01', 'type' => 'branch', 'is_active' => true]);
        Location::create(['name' => 'Tish B02', 'code' => 'B02', 'type' => 'branch', 'is_active' => true]);
        Location::create(['name' => 'Former factory', 'code' => 'FAC', 'type' => 'factory', 'is_active' => true]);

        $this->seed(TishBreakfastMenuSeeder::class);

        $this->assertDatabaseHas('categories', ['slug' => 'tish-breakfast', 'name' => 'Breakfast Menu']);
        $this->assertSame(6, Product::where('sku', 'like', 'BRG-BREAKFAST-%')->count());
        $this->assertSame(12, RestaurantMenuItem::count());
        $this->assertSame(12, LocationProduct::count());
        $this->assertSame(0, Recipe::count());
        $this->assertSame(0, Order::count());
        foreach (Product::where('sku', 'like', 'BRG-BREAKFAST-%')->get() as $product) {
            $this->assertSame('0.00', $product->base_selling_price);
            $this->assertSame(2, $product->restaurantMenuItems()->count());
            $this->assertTrue($product->restaurantMenuItems()->get()->every(
                fn (RestaurantMenuItem $menu) => ! $menu->is_active && ! $menu->show_in_pos
                    && ! $menu->show_in_qr && $menu->inventory_mode === 'recipe'
            ));
            $this->assertTrue($product->locationProducts()->get()->every(fn ($assignment) => ! $assignment->is_available));
        }
        $this->assertDatabaseHas('products', ['sku' => 'BRG-BREAKFAST-TUNA-BAGUETTE']);
        $this->assertDatabaseHas('products', ['sku' => 'BRG-BREAKFAST-PALESTINIAN-CHEESE']);

        $modified = Product::where('sku', 'BRG-BREAKFAST-CLUB-SANDWICH')->firstOrFail();
        $modified->update(['base_selling_price' => 28, 'name_ar' => 'كلوب خاص']);
        Product::where('sku', 'BRG-BREAKFAST-CHICKEN-ROLL')->firstOrFail()->delete();

        $this->seed(TishBreakfastMenuSeeder::class);

        $this->assertSame(6, Product::withTrashed()->where('sku', 'like', 'BRG-BREAKFAST-%')->count());
        $this->assertSame(12, RestaurantMenuItem::count());
        $this->assertSame('28.00', $modified->fresh()->base_selling_price);
        $this->assertSame('كلوب خاص', $modified->fresh()->name_ar);
        $this->assertTrue(Product::withTrashed()->where('sku', 'BRG-BREAKFAST-CHICKEN-ROLL')->firstOrFail()->trashed());
    }
}

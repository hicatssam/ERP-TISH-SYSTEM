<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantMenuItem;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Orders\OrderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TishRecipeStockFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tish_menu_product_has_approved_recipe_and_raw_stock_in_each_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $products = Product::query()
            ->where('is_raw_material', false)
            ->where('sku', 'like', 'BRG-%')
            ->get();

        $this->assertCount(76, $products);

        foreach ($products as $product) {
            $recipe = Recipe::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->where('status', 'approved')
                ->where('is_active', true)
                ->with('items.ingredient')
                ->first();

            $this->assertNotNull($recipe, $product->sku);
            $this->assertGreaterThan(0, (float) $recipe->yield_quantity, $product->sku);
            $this->assertNotEmpty($recipe->items->all(), $product->sku);

            foreach ($recipe->items as $ingredient) {
                $this->assertTrue((bool) $ingredient->ingredient?->is_raw_material, $product->sku);
                $this->assertGreaterThan(0, (float) $ingredient->quantity, $product->sku);
            }

            foreach (Location::query()->branches()->active()->get() as $branch) {
                $menu = RestaurantMenuItem::query()
                    ->where('location_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->first();

                $this->assertNotNull($menu, $branch->code.'/'.$product->sku);
                $this->assertSame('recipe', $menu->inventory_mode, $branch->code.'/'.$product->sku);

                foreach ($recipe->items as $ingredient) {
                    $this->assertDatabaseHas('inventories', [
                        'location_id' => $branch->id,
                        'product_id' => $ingredient->ingredient_product_id,
                    ]);
                }
            }
        }
    }

    public function test_confirm_then_cancel_unpaid_burger_order_deducts_and_restores_its_exact_ingredients(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Location::query()->where('code', 'B01')->firstOrFail();
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $burger = Product::query()->where('sku', 'BRG-BEEF-CLASSIC')->firstOrFail();
        $bun = Product::query()->where('sku', 'BRG-ING-BUN')->firstOrFail();
        $beef = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();

        $before = collect([$bun, $beef])->mapWithKeys(fn ($item) => [
            $item->id => (float) Inventory::query()
                ->where('location_id', $branch->id)
                ->where('product_id', $item->id)
                ->value('quantity'),
        ])->all();

        $order = app(OrderService::class)->createOrder([
            'location_id' => $branch->id,
            'sales_channel_id' => SalesChannel::query()->where('slug', 'tish-direct')->value('id'),
            'restaurant_service_type' => 'takeaway',
            'order_source' => 'restaurant_pos',
            'payment_arrangement' => 'pay_on_pickup',
            'items' => [['product_id' => $burger->id, 'quantity' => 2]],
            'notes' => 'TISH-RECIPE-INTEGRATION-TEST',
        ], $admin, false);

        $this->assertSame('draft', $order->statusValue());
        $this->assertSame(0, DB::table('order_inventory_consumptions')
            ->where('order_id', $order->id)->count());

        $order = app(OrderService::class)->confirmOrder($order, $admin);
        $this->assertSame('confirmed', $order->statusValue());
        $this->assertNotNull($order->fresh()->invoice);

        $this->assertDatabaseHas('order_inventory_consumptions', [
            'order_id' => $order->id,
            'stock_product_id' => $bun->id,
            'source' => 'recipe',
        ]);
        $this->assertDatabaseHas('order_inventory_consumptions', [
            'order_id' => $order->id,
            'stock_product_id' => $beef->id,
            'source' => 'recipe',
        ]);

        foreach ([$bun, $beef] as $ingredient) {
            $consumed = (float) DB::table('order_inventory_consumptions')
                ->where('order_id', $order->id)
                ->where('stock_product_id', $ingredient->id)
                ->sum('consumed_quantity');
            $now = (float) Inventory::query()
                ->where('location_id', $branch->id)
                ->where('product_id', $ingredient->id)
                ->value('quantity');
            $this->assertEqualsWithDelta($before[$ingredient->id] - $consumed, $now, 0.001);
        }

        app(OrderService::class)->cancelOrder($order->fresh(), 'Test cancellation of unpaid order', $admin);
        $this->assertSame('cancelled', $order->fresh()->statusValue());

        foreach ([$bun, $beef] as $ingredient) {
            $after = (float) Inventory::query()
                ->where('location_id', $branch->id)
                ->where('product_id', $ingredient->id)
                ->value('quantity');
            $this->assertEqualsWithDelta($before[$ingredient->id], $after, 0.001);
        }

        $this->assertSame(0, DB::table('order_inventory_consumptions')
            ->where('order_id', $order->id)->whereNull('restored_at')->count());
    }

    public function test_confirm_rejects_insufficient_ingredient_stock_without_partially_consuming_other_ingredients(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Location::query()->where('code', 'B01')->firstOrFail();
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $burger = Product::query()->where('sku', 'BRG-BEEF-CLASSIC')->firstOrFail();
        $bun = Product::query()->where('sku', 'BRG-ING-BUN')->firstOrFail();
        $beef = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();

        // Isolated test fixture; never perform a direct stock update on production.
        Inventory::query()->where('location_id', $branch->id)
            ->where('product_id', $bun->id)->update(['quantity' => 0]);
        $beefBefore = Inventory::query()->where('location_id', $branch->id)
            ->where('product_id', $beef->id)->value('quantity');

        $order = app(OrderService::class)->createOrder([
            'location_id' => $branch->id,
            'sales_channel_id' => SalesChannel::query()->where('slug', 'tish-direct')->value('id'),
            'restaurant_service_type' => 'takeaway',
            'order_source' => 'restaurant_pos',
            'payment_arrangement' => 'pay_on_pickup',
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
            'notes' => 'TISH-RECIPE-OUT-OF-STOCK-TEST',
        ], $admin, false);

        try {
            app(OrderService::class)->confirmOrder($order, $admin);
            $this->fail('Confirmation should fail when the recipe lacks buns.');
        } catch (ValidationException $expected) {
            $this->assertSame('draft', $order->fresh()->statusValue());
            $this->assertSame(0, DB::table('order_inventory_consumptions')
                ->where('order_id', $order->id)->count());
            $this->assertEqualsWithDelta((float) $beefBefore, (float) Inventory::query()
                ->where('location_id', $branch->id)
                ->where('product_id', $beef->id)->value('quantity'), 0.001);
            $this->assertNull($order->fresh()->invoice);
        }
    }

    public function test_safe_existing_database_seeder_preserves_stock_orders_and_recipe_quantities_on_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Location::query()->where('code', 'B01')->firstOrFail();
        $stockBefore = DB::table('inventories')->where('location_id', $branch->id)
            ->orderBy('product_id')->pluck('quantity', 'product_id')->all();
        $movementsBefore = DB::table('stock_movements')->count();
        $ordersBefore = DB::table('orders')->count();
        $invoicesBefore = DB::table('invoices')->count();
        $recipeItemsBefore = DB::table('recipe_items')->count();

        $this->seed(\\Database\\Seeders\\TishExistingDatabaseSeeder::class);
        $this->seed(\\Database\\Seeders\\TishExistingDatabaseSeeder::class);

        $this->assertSame($movementsBefore, DB::table('stock_movements')->count());
        $this->assertSame($ordersBefore, DB::table('orders')->count());
        $this->assertSame($invoicesBefore, DB::table('invoices')->count());
        $this->assertSame($recipeItemsBefore, DB::table('recipe_items')->count());
        $this->assertEquals($stockBefore, DB::table('inventories')
            ->where('location_id', $branch->id)->orderBy('product_id')
            ->pluck('quantity', 'product_id')->all());
        $this->assertSame(2, DB::table('kitchen_stations')
            ->where('location_id', $branch->id)->whereIn('code', ['KITCHEN', 'PREP'])->count());
        $this->assertSame(8, DB::table('kitchen_category_routes')
            ->where('location_id', $branch->id)->count());
        $this->assertSame(76, DB::table('restaurant_menu_items')
            ->where('location_id', $branch->id)->where('inventory_mode', 'recipe')
            ->whereIn('product_id', Product::query()->where('is_raw_material', false)
                ->where('sku', 'like', 'BRG-%')->pluck('id'))->count());
    }

}

<?php

namespace Tests\Feature\Restaurant;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Realistic cashier scenarios. Database is isolated by RefreshDatabase.
 * No production payment provider or external service is contacted.
 */
class PosCashierShiftSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $stock = 40): array
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::query()->create([
            'name' => 'Test restaurant', 'code' => 'SIM-' . Str::random(7),
            'type' => 'branch', 'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'SIM-' . Str::random(8), 'full_name' => 'Shift Cashier',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $cashier = User::factory()->create(['employee_id' => $employee->id]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'restaurant_menu.view', 'orders.create'] as $ability) {
            $cashier->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }
        $category = Category::query()->create([
            'name' => 'Burgers', 'name_ar' => 'برغر', 'is_active' => true,
        ]);
        $products = [];
        foreach (['BURGER', 'FRIES', 'DRINK'] as $i => $name) {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => $name, 'sku' => $name . '-' . Str::random(5),
                'base_selling_price' => 10 + $i * 5,
                'is_active' => true,
            ]);
            RestaurantMenuItem::query()->create([
                'location_id' => $branch->id, 'product_id' => $product->id,
                'is_active' => true, 'show_in_pos' => true, 'sort_order' => $i + 1,
                'inventory_mode' => 'product',
            ]);
            LocationProduct::query()->create([
                'location_id' => $branch->id, 'product_id' => $product->id,
                'is_available' => true,
            ]);
            Inventory::query()->create([
                'location_id' => $branch->id, 'product_id' => $product->id,
                'quantity' => $stock, 'reserved_quantity' => 0, 'unit_cost' => 4,
            ]);
            $products[] = $product;
        }
        $channel = SalesChannel::query()->create([
            'name' => 'Counter', 'slug' => 'counter-' . Str::random(7),
            'type' => 'direct', 'is_active' => true,
        ]);
        $method = PaymentMethod::query()->create([
            'name' => 'Simulation wallet', 'name_ar' => 'محفظة اختبار',
            'code' => 'sim-' . Str::random(9),
            'type' => 'electronic_wallet', 'is_active' => true,
            'requires_verification' => false,
        ]);
        return compact('branch', 'cashier', 'products', 'channel', 'method');
    }

    private function orderPayload(array $f, array $items): array
    {
        return [
            'pos_request_token' => (string) Str::uuid(),
            'pos_action' => 'bill_payment',
            'location_id' => $f['branch']->id,
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now',
            'payment_method_id' => $f['method']->id,
            'reference_number' => 'SIM-RECEIPT-' . Str::random(8),
            'payment_received_confirmed' => 1,
            'sales_channel_id' => $f['channel']->id,
            'items' => $items,
        ];
    }

    #[Test]
    public function pos_01_single_burger_creates_one_paid_order_invoice_and_stock_movement(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $response = $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertCreated()->assertJsonPath('status', 'confirmed');

        $order = Order::query()->sole();
        $this->assertSame($order->id, $response->json('order_id'));
        $this->assertEquals(10, $order->total_amount);
        $this->assertEquals(10, $order->invoice?->total_amount);
        $this->assertEquals(10, $order->payments()->sole()->amount);
        $this->assertEquals(39, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
    }

    #[Test]
    public function pos_02_three_products_create_three_lines_and_one_payment(): void
    {
        $f = $this->fixture();
        $items = array_map(fn ($p) => ['product_id' => $p->id, 'quantity' => 1], $f['products']);
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'), $this->orderPayload($f, $items)
        )->assertCreated();
        $order = Order::query()->sole();
        $this->assertSame(3, $order->items()->count());
        $this->assertEquals(45, $order->total_amount);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(1, $order->invoice()->count());
    }

    #[Test]
    public function pos_03_ten_of_one_product_deducts_exactly_ten_units(): void
    {
        $f = $this->fixture(12);
        $p = $f['products'][0];
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 10]])
        )->assertCreated();
        $this->assertEquals(100, Order::query()->sole()->total_amount);
        $this->assertEquals(2, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
    }

    #[Test]
    public function pos_04_posting_same_request_twice_returns_original_without_double_payment_or_stock(): void
    {
        $f = $this->fixture(10);
        $p = $f['products'][0];
        $payload = $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 2]]);
        $first = $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $second = $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $this->assertSame($first->json('order_id'), $second->json('order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertEquals(8, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
    }

    #[Test]
    public function pos_05_reusing_old_request_token_for_modified_order_is_rejected(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $payload = $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $payload['items'][0]['quantity'] = 3;
        $this->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('pos_request_token');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function pos_06_insufficient_inventory_rolls_back_order_payment_and_invoice(): void
    {
        $f = $this->fixture(1);
        $p = $f['products'][0];
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 4]])
        )->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertEquals(1, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
    }

    #[Test]
    public function pos_07_unavailable_branch_product_is_rejected_without_creating_order(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        LocationProduct::query()->where('product_id', $p->id)->update(['is_available' => false]);
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_08_wallet_requires_explicit_received_confirmation(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        unset($payload['payment_received_confirmed']);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('payment_received_confirmed');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_09_unauthorized_cashier_cannot_create_paid_order(): void
    {
        $f = $this->fixture();
        $guest = User::factory()->create();
        $payload = $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]]);
        $this->actingAs($guest)->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }
}

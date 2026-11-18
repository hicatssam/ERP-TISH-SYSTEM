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
use App\Models\Module;
use App\Models\KitchenStation;
use Illuminate\Support\Facades\Cache;
use App\Models\PaymentMethod;
use App\Models\CashSession;
use App\Models\RestaurantTable;
use App\Models\ProductVariant;
use App\Models\ModifierGroup;
use App\Models\Modifier;
use App\Models\ProductModifierGroup;
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
                'barcode' => 'SIM-' . $name . '-' . Str::random(8),
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
    public function pos_10_quantity_edit_preserves_channel_discount_and_invoice_total(): void
    {
        $f = $this->fixture();
        $f['channel']->update(['discount_type' => 'percentage', 'discount_value' => 10]);
        $p = $f['products'][0];
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertCreated();

        $order = Order::query()->sole();
        $this->assertEquals(9, $order->total_amount);
        $item = $order->items()->sole();
        app(\App\Services\Orders\OrderService::class)->updateOrder($order, [
            'payment_arrangement' => 'pay_now',
            'items' => [['id' => $item->id, 'quantity' => 2]],
        ], $f['cashier']);

        $fresh = $order->fresh(['invoice', 'items']);
        $this->assertEquals(20, $fresh->subtotal);
        $this->assertEquals(18, $fresh->total_amount);
        $this->assertEquals(18, $fresh->invoice?->total_amount);
        $this->assertEquals(2, $fresh->items->first()->quantity);
    }

    #[Test]
    public function pos_11_zero_or_negative_quantity_never_creates_a_sale(): void
    {
        $f = $this->fixture();
        foreach ([0, -2] as $qty) {
            $this->actingAs($f['cashier'])->postJson(
                route('restaurant.pos.orders.store'),
                $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => $qty]])
            )->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_12_forged_client_price_cannot_change_server_sale_price(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1, 'unit_price' => 0.01],
        ]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $this->assertEquals(10, Order::query()->sole()->items()->sole()->unit_price);
        $this->assertEquals(10, Order::query()->sole()->total_amount);
    }

    #[Test]
    public function pos_13_invalid_modifier_is_rejected_without_financial_footprint(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [[
            'product_id' => $f['products'][0]->id,
            'quantity' => 1,
            'modifiers' => [['modifier_id' => 999999, 'quantity' => 1]],
        ]]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.modifiers.0.modifier_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_14_pay_now_without_payment_method_is_rejected(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        unset($payload['payment_method_id']);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_15_percentage_discount_over_one_hundred_is_rejected(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 101;
        $payload['discount_reason'] = 'Management correction for test';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_16_fixed_discount_above_subtotal_rolls_back_checkout(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['discount_type'] = 'fixed';
        $payload['discount_value'] = 20;
        $payload['discount_reason'] = 'Manager-approved price correction';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_17_valid_manual_discount_flows_to_payment_and_invoice(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['discount_type'] = 'fixed';
        $payload['discount_value'] = 2;
        $payload['discount_reason'] = 'Compensation for delayed service';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $order = Order::query()->sole();
        $this->assertEquals(8, $order->total_amount);
        $this->assertEquals(8, $order->payments()->sole()->amount);
        $this->assertEquals(8, $order->invoice?->total_amount);
    }

    #[Test]
    public function pos_18_wallet_without_reference_number_is_rejected(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        unset($payload['reference_number']);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('reference_number');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_19_paid_order_cannot_be_cancelled_before_refund(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertCreated();
        $order = Order::query()->sole();

        try {
            app(\App\Services\Orders\OrderService::class)
                ->cancelOrder($order, 'Test cancellation before refund', $f['cashier']);
            $this->fail('A paid sale must not cancel without a refund.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        $this->assertSame('confirmed', $order->fresh()->statusValue());
        $this->assertEquals(39, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function pos_20_separate_checkout_tokens_create_two_independent_orders(): void
    {
        $f = $this->fixture(10);
        $p = $f['products'][0];
        foreach ([1, 2] as $quantity) {
            $this->actingAs($f['cashier'])->postJson(
                route('restaurant.pos.orders.store'),
                $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => $quantity]])
            )->assertCreated();
        }
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertEquals(7, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
    }

    #[Test]
    public function pos_21_same_token_from_second_cashier_cannot_read_original_order(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)->assertCreated();

        $employee = Employee::query()->create([
            'employee_number' => 'SIM-CASHIER-B', 'full_name' => 'Other cashier',
        ]);
        $employee->locations()->attach($f['branch']->id, ['is_primary' => true]);
        $second = User::factory()->create(['employee_id' => $employee->id]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'restaurant_menu.view', 'orders.create'] as $ability) {
            $second->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }
        $this->actingAs($second)->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('pos_request_token');
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function pos_22_cashier_cannot_submit_order_to_another_branch(): void
    {
        $f = $this->fixture();
        $other = Location::query()->create([
            'name' => 'Other test branch', 'code' => 'SIM-OTHER',
            'type' => 'branch', 'is_active' => true,
        ]);
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['location_id'] = $other->id;
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_23_huge_quantity_cannot_sell_beyond_available_stock(): void
    {
        $f = $this->fixture(5);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [
                ['product_id' => $f['products'][0]->id, 'quantity' => 9999999],
            ])
        )->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_24_nonexistent_product_cannot_be_purchased(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => 999999, 'quantity' => 1]])
        )->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_25_disabled_sales_channel_rejects_checkout(): void
    {
        $f = $this->fixture();
        $f['channel']->update(['is_active' => false]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertUnprocessable()->assertJsonValidationErrors('sales_channel_id');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_26_inactive_wallet_rejects_checkout(): void
    {
        $f = $this->fixture();
        $f['method']->update(['is_active' => false]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_27_negative_paid_amount_is_rejected_by_validation(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['paid_amount'] = -100;
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('paid_amount');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_28_pending_verification_does_not_consume_inventory_or_issue_invoice(): void
    {
        $f = $this->fixture(20);
        $f['method']->update(['requires_verification' => true]);
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['payment_arrangement'] = 'pending_verification';
        $payload['payment_proof'] = \Illuminate\Http\UploadedFile::fake()->image('receipt.png');
        $this->actingAs($f['cashier'])->post(route('restaurant.pos.orders.store'), $payload, [
            'Accept' => 'application/json',
        ])->assertCreated()->assertJsonPath('status', 'draft');

        $order = Order::query()->sole();
        $this->assertNull($order->invoice);
        $this->assertEquals(20, Inventory::query()->where('product_id', $f['products'][0]->id)->sole()->quantity);
        $this->assertSame('pending_verification', $order->payments()->sole()->statusValue());
    }

    #[Test]
    public function pos_29_on_account_without_named_customer_is_rejected(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['payment_arrangement'] = 'on_account';
        unset($payload['payment_method_id']);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Enable real KDS ticket creation on the test branch; no external station.
     */
    private function kitchenFixture(array $f): void
    {
        Module::query()->firstOrCreate(['code' => 'kitchen'], [
            'name' => 'Kitchen', 'type' => 'industry', 'is_active' => true,
        ]);
        Cache::forget('modules:registry:v1');
        KitchenStation::query()->create([
            'location_id' => $f['branch']->id, 'name' => 'Main kitchen',
            'code' => 'MAIN', 'is_default' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function pos_30_kitchen_ticket_is_idempotent_for_one_confirmed_order(): void
    {
        $f = $this->fixture();
        $this->kitchenFixture($f);
        $payload = $this->orderPayload($f, [[
            'product_id' => $f['products'][0]->id, 'quantity' => 2,
        ]]);
        $payload['pos_action'] = 'kot_print';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated()->assertJsonPath('kitchen_dispatched', true);
        $order = Order::query()->sole();
        $this->assertSame(1, $order->kitchenTickets()->count());

        app(\App\Services\Kitchen\KitchenTicketService::class)
            ->dispatchConfirmedOrder($order, $f['cashier']);
        $this->assertSame(1, $order->kitchenTickets()->count());
        $this->assertSame(1, $order->kitchenTickets()->first()->items()->count());
        $this->assertEquals(38, Inventory::query()
            ->where('product_id', $f['products'][0]->id)->sole()->quantity);
    }

    #[Test]
    public function pos_31_kitchen_queued_order_rejects_quantity_edit_without_financial_changes(): void
    {
        $f = $this->fixture();
        $this->kitchenFixture($f);
        $payload = $this->orderPayload($f, [[
            'product_id' => $f['products'][0]->id, 'quantity' => 1,
        ]]);
        $payload['pos_action'] = 'kot_print';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $order = Order::query()->sole();
        $item = $order->items()->sole();
        $this->assertSame('queued', $order->kitchenTickets()->sole()->statusValue());

        try {
            app(\App\Services\Orders\OrderService::class)->updateOrder($order, [
                'payment_arrangement' => 'pay_on_pickup',
                'items' => [['id' => $item->id, 'quantity' => 2]],
            ], $f['cashier']);
            $this->fail('Changing a kitchen-dispatched quantity must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertEquals(1, $order->fresh()->items()->sole()->quantity);
        $this->assertEquals(10, $order->fresh()->invoice?->total_amount);
        $this->assertEquals(39, Inventory::query()
            ->where('product_id', $f['products'][0]->id)->sole()->quantity);
    }

    #[Test]
    public function pos_32_kitchen_queued_order_rejects_note_change_without_changing_ticket(): void
    {
        $f = $this->fixture();
        $this->kitchenFixture($f);
        $payload = $this->orderPayload($f, [[
            'product_id' => $f['products'][0]->id, 'quantity' => 1,
            'kitchen_notes' => 'No onion',
        ]]);
        $payload['pos_action'] = 'kot_print';
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $order = Order::query()->sole();
        $item = $order->items()->sole();

        try {
            app(\App\Services\Orders\OrderService::class)->updateOrder($order, [
                'payment_arrangement' => 'pay_on_pickup',
                'items' => [['id' => $item->id, 'quantity' => 1, 'kitchen_notes' => 'Extra onion']],
            ], $f['cashier']);
            $this->fail('Kitchen notes must not change after dispatch.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertSame('No onion', $item->fresh()->kitchen_notes);
        $this->assertEquals(10, $order->fresh()->total_amount);
        $this->assertSame(1, $order->kitchenTickets()->count());
    }

    #[Test]
    public function pos_33_cashier_without_refund_permission_cannot_reverse_a_payment(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertCreated();

        $payment = Payment::query()->sole();
        $this->actingAs($f['cashier'])->post(route('payments.refund', $payment), [
            'amount' => 3,
            'payment_method_id' => $f['method']->id,
            'reason' => 'Refund unauthorized test',
        ])->assertForbidden();
        $this->assertDatabaseCount('refunds', 0);
        $this->assertEquals(10, $payment->fresh()->amount);
    }

    #[Test]
    public function pos_34_partial_refund_preserves_original_payment_and_updates_invoice(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('payments.refund', 'web'));
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertCreated();
        $order = Order::query()->sole();
        $payment = $order->payments()->sole();

        $this->actingAs($f['cashier'])->post(route('payments.refund', $payment), [
            'amount' => 4,
            'payment_method_id' => $f['method']->id,
            'reason' => 'Partial refund for test',
        ])->assertRedirect();

        $this->assertEquals(10, $payment->fresh()->amount);
        $this->assertEquals(4, $payment->refunds()->sum('amount'));
        $this->assertSame('confirmed', $payment->fresh()->statusValue());
        $this->assertEquals(6, $order->fresh()->invoice?->paid_amount);
    }

    #[Test]
    public function pos_35_second_refund_above_remaining_balance_is_rejected(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('payments.refund', 'web'));
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertCreated();
        $payment = Payment::query()->sole();

        $this->actingAs($f['cashier'])->post(route('payments.refund', $payment), [
            'amount' => 6,
            'payment_method_id' => $f['method']->id,
            'reason' => 'First partial refund',
        ])->assertRedirect();
        $this->actingAs($f['cashier'])->post(route('payments.refund', $payment), [
            'amount' => 5,
            'payment_method_id' => $f['method']->id,
            'reason' => 'Invalid overrefund attempt',
        ])->assertSessionHasErrors('amount');

        $this->assertEquals(6, $payment->refunds()->sum('amount'));
        $this->assertDatabaseCount('refunds', 1);
    }

    #[Test]
    public function pos_36_full_refund_allows_order_cancellation_and_restores_stock(): void
    {
        $f = $this->fixture(5);
        $f['cashier']->givePermissionTo(Permission::findOrCreate('payments.refund', 'web'));
        $p = $f['products'][0];
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertCreated();
        $order = Order::query()->sole();
        $payment = $order->payments()->sole();

        $this->actingAs($f['cashier'])->post(route('payments.refund', $payment), [
            'amount' => 10,
            'payment_method_id' => $f['method']->id,
            'reason' => 'Full refund before cancellation',
        ])->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->statusValue());

        app(\App\Services\Orders\OrderService::class)
            ->cancelOrder($order, 'Customer asked to cancel', $f['cashier']);
        $this->assertSame('cancelled', $order->fresh()->statusValue());
        $this->assertEquals(5, Inventory::query()->where('product_id', $p->id)->sole()->quantity);
        $this->assertSame('cancelled', $order->fresh()->invoice->statusValue());
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function pos_37_cashier_shift_opens_with_float_and_rejects_duplicate_open(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('cash_sessions.manage', 'web'));
        $this->actingAs($f['cashier'])->post(route('cash-sessions.open'), [
            'opening_balance' => 100,
        ])->assertRedirect();
        $session = CashSession::query()->sole();
        $this->assertSame('open', $session->status->value);
        $this->assertEquals(100, $session->opening_balance);
        $this->assertEquals(100, $session->expected_cash);

        $this->post(route('cash-sessions.open'), ['opening_balance' => 50])
            ->assertSessionHasErrors('cash_session');
        $this->assertDatabaseCount('cash_sessions', 1);
    }

    #[Test]
    public function pos_38_cashier_shift_rejects_invalid_opening_float(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('cash_sessions.manage', 'web'));
        $this->actingAs($f['cashier'])->post(route('cash-sessions.open'), [
            'opening_balance' => -10,
        ])->assertSessionHasErrors('opening_balance');
        $this->assertDatabaseCount('cash_sessions', 0);
    }

    #[Test]
    public function pos_39_shift_close_records_cash_shortage_and_cannot_close_twice(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('cash_sessions.manage', 'web'));
        $this->actingAs($f['cashier'])->post(route('cash-sessions.open'), [
            'opening_balance' => 100,
        ])->assertRedirect();
        $session = CashSession::query()->sole();
        $this->post(route('cash-sessions.close', $session), [
            'actual_cash' => 95, 'closing_note' => 'Cash shortage confirmed in test',
        ])->assertRedirect();

        $this->assertSame('closed', $session->fresh()->status->value);
        $this->assertEquals(100, $session->fresh()->expected_cash);
        $this->assertEquals(-5, $session->fresh()->variance);
        $this->post(route('cash-sessions.close', $session), [
            'actual_cash' => 95,
        ])->assertSessionHasErrors('cash_session');
        $this->assertDatabaseCount('cash_sessions', 1);
    }

    #[Test]
    public function pos_40_wallet_payment_does_not_inflate_physical_cash_shift(): void
    {
        $f = $this->fixture();
        $f['cashier']->givePermissionTo(Permission::findOrCreate('cash_sessions.manage', 'web'));
        $this->actingAs($f['cashier'])->post(route('cash-sessions.open'), [
            'opening_balance' => 80,
        ])->assertRedirect();
        $this->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 1]])
        )->assertCreated();
        $session = CashSession::query()->sole();

        $this->post(route('cash-sessions.close', $session), [
            'actual_cash' => 80,
        ])->assertRedirect();
        $this->assertEquals(0, $session->fresh()->cash_received);
        $this->assertEquals(80, $session->fresh()->expected_cash);
        $this->assertEquals(0, $session->fresh()->variance);
    }

    #[Test]
    public function pos_41_real_cash_sale_increases_expected_drawer_balance(): void
    {
        $f = $this->fixture();
        $f['method']->update(['type' => 'cash']);
        $f['cashier']->givePermissionTo(Permission::findOrCreate('cash_sessions.manage', 'web'));
        $this->actingAs($f['cashier'])->post(route('cash-sessions.open'), [
            'opening_balance' => 50,
        ])->assertRedirect();
        $this->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $f['products'][0]->id, 'quantity' => 2]])
        )->assertCreated();
        $session = CashSession::query()->sole();

        $this->post(route('cash-sessions.close', $session), [
            'actual_cash' => 70,
        ])->assertRedirect();
        $this->assertEquals(20, $session->fresh()->cash_received);
        $this->assertEquals(70, $session->fresh()->expected_cash);
        $this->assertEquals(0, $session->fresh()->variance);
    }

    #[Test]
    public function pos_42_dine_in_sale_opens_table_session_with_guest_count(): void
    {
        $f = $this->fixture();
        $table = RestaurantTable::query()->create([
            'location_id' => $f['branch']->id, 'code' => 'T-05',
            'name' => 'Table 05', 'capacity' => 4, 'is_active' => true,
        ]);
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['service_type'] = 'dine_in';
        $payload['restaurant_table_id'] = $table->id;
        $payload['guest_count'] = 2;
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertCreated();
        $order = Order::query()->sole();
        $this->assertSame($table->id, $order->restaurant_table_id);
        $this->assertEquals(2, $order->guest_count);
        $this->assertNotNull($order->restaurant_table_session_id);
        $this->assertTrue($table->fresh()->isOccupied());
    }

    #[Test]
    public function pos_43_dine_in_without_table_is_rejected(): void
    {
        $f = $this->fixture();
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['service_type'] = 'dine_in';
        $payload['guest_count'] = 2;
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('restaurant_table_id');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_44_table_from_other_branch_is_rejected_without_payment(): void
    {
        $f = $this->fixture();
        $other = Location::query()->create([
            'name' => 'Other restaurant', 'code' => 'SIM-TABLE-OTHER',
            'type' => 'branch', 'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'location_id' => $other->id, 'code' => 'T-88',
            'name' => 'Foreign table', 'capacity' => 4, 'is_active' => true,
        ]);
        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['service_type'] = 'dine_in';
        $payload['restaurant_table_id'] = $table->id;
        $payload['guest_count'] = 2;
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('restaurant_table_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function pos_45_delivery_and_outdoor_orders_remain_distinct(): void
    {
        $f = $this->fixture();
        foreach (['delivery', 'outdoor'] as $type) {
            $payload = $this->orderPayload($f, [
                ['product_id' => $f['products'][0]->id, 'quantity' => 1],
            ]);
            $payload['service_type'] = $type;
            $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'), $payload)
                ->assertCreated();
        }
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(['delivery', 'outdoor'], Order::query()->orderBy('id')->get()
            ->map(fn ($order) => $order->restaurant_service_type->value)->all());
    }

    #[Test]
    public function pos_46_product_requiring_variant_rejects_unsized_order(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $p->update(['product_type' => 'variant']);
        ProductVariant::query()->create([
            'product_id' => $p->id, 'name' => 'Large', 'name_ar' => 'كبير',
            'sku' => 'SIM-VAR-L', 'selling_price' => 18, 'is_active' => true,
        ]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function pos_47_variant_price_comes_from_database_not_modified_checkout(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $p->update(['product_type' => 'variant']);
        $variant = ProductVariant::query()->create([
            'product_id' => $p->id, 'name' => 'Large', 'name_ar' => 'كبير',
            'sku' => 'SIM-VAR-L', 'selling_price' => 18, 'is_active' => true,
        ]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [[
                'product_id' => $p->id, 'product_variant_id' => $variant->id,
                'quantity' => 1, 'unit_price' => 0.01,
            ]])
        )->assertCreated();
        $this->assertEquals(18, Order::query()->sole()->total_amount);
        $this->assertSame($variant->id, Order::query()->sole()->items()->sole()->product_variant_id);
    }

    #[Test]
    public function pos_48_required_modifier_is_enforced_and_price_is_snapshotted(): void
    {
        $f = $this->fixture();
        $p = $f['products'][0];
        $group = ModifierGroup::query()->create([
            'code' => 'sim-sauce', 'name' => 'Sauce', 'name_ar' => 'صلصة',
            'selection_type' => 'single', 'is_required' => true, 'is_active' => true,
        ]);
        $modifier = Modifier::query()->create([
            'modifier_group_id' => $group->id, 'code' => 'sim-cheese',
            'name' => 'Cheese', 'name_ar' => 'جبنة', 'price_delta' => 2,
            'is_active' => true,
        ]);
        ProductModifierGroup::query()->create([
            'product_id' => $p->id, 'modifier_group_id' => $group->id,
            'is_active' => true,
        ]);
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [['product_id' => $p->id, 'quantity' => 1]])
        )->assertUnprocessable();
        $this->actingAs($f['cashier'])->postJson(route('restaurant.pos.orders.store'),
            $this->orderPayload($f, [[
                'product_id' => $p->id, 'quantity' => 1,
                'modifiers' => [['modifier_id' => $modifier->id, 'quantity' => 1]],
            ]])
        )->assertCreated();
        $order = Order::query()->sole();
        $this->assertEquals(12, $order->total_amount);
        $this->assertEquals(12, $order->invoice?->total_amount);
        $this->assertSame(1, $order->items()->sole()->modifiers()->count());
    }

    #[Test]
    public function pos_49_ten_customers_five_kitchen_tickets_and_five_immediate_payments_balance(): void
    {
        // Sequential peak-hour workload: real transactions on an isolated DB,
        // not a latency benchmark or simultaneous browser traffic simulation.
        $f = $this->fixture(50);
        $this->kitchenFixture($f);
        $productId = $f['products'][0]->id;

        foreach (range(1, 10) as $customer) {
            $payload = $this->orderPayload($f, [
                ['product_id' => $productId, 'quantity' => 1],
            ]);
            if ($customer <= 5) {
                $payload['pos_action'] = 'kot_print';
            }
            $this->actingAs($f['cashier'])->postJson(
                route('restaurant.pos.orders.store'), $payload
            )->assertCreated();
        }

        $this->assertDatabaseCount('orders', 10);
        $this->assertDatabaseCount('order_items', 10);
        $this->assertDatabaseCount('invoices', 10);
        $this->assertDatabaseCount('kitchen_tickets', 10);
        $this->assertDatabaseCount('payments', 5);
        $this->assertEquals(40, Inventory::query()
            ->where('location_id', $f['branch']->id)
            ->where('product_id', $productId)->sole()->quantity);
        $this->assertEquals(50, Payment::query()->sum('amount'));
    }

    #[Test]
    public function pos_50_existing_unpaid_order_can_collect_cash_and_card_separately_without_overpayment(): void
    {
        // This proves split settlement through PaymentService, not that the
        // current single-method POS checkout UI supports mixed tender.
        $f = $this->fixture();
        $this->kitchenFixture($f);
        $f['method']->update(['type' => 'cash']);
        $card = PaymentMethod::query()->create([
            'name' => 'Simulation card', 'name_ar' => 'بطاقة اختبار',
            'code' => 'sim-card-' . Str::random(8),
            'type' => 'card_pos', 'is_active' => true,
            'requires_verification' => false,
            'requires_reference' => false,
        ]);

        $payload = $this->orderPayload($f, [
            ['product_id' => $f['products'][0]->id, 'quantity' => 1],
        ]);
        $payload['pos_action'] = 'kot_print';
        $this->actingAs($f['cashier'])->postJson(
            route('restaurant.pos.orders.store'), $payload
        )->assertCreated();
        $order = Order::query()->sole();
        $service = app(\App\Services\Payments\PaymentService::class);

        $cash = $service->recordPayment([
            'order_type' => 'order', 'order_id' => $order->id,
            'payment_method_id' => $f['method']->id, 'amount' => 4,
        ], $f['cashier']);
        $cardPayment = $service->recordPayment([
            'order_type' => 'order', 'order_id' => $order->id,
            'payment_method_id' => $card->id, 'amount' => 6,
        ], $f['cashier']);

        $this->assertNotSame($cash->id, $cardPayment->id);
        $this->assertSame(2, $order->payments()->count());
        $this->assertEquals(10, $order->payments()->sum('amount'));
        $this->assertEquals(10, $order->fresh()->invoice?->paid_amount);

        try {
            $service->recordPayment([
                'order_type' => 'order', 'order_id' => $order->id,
                'payment_method_id' => $card->id, 'amount' => 1,
            ], $f['cashier']);
            $this->fail('Cannot collect money beyond the already settled order.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 2);
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

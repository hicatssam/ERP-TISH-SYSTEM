<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMenuBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_branch_menu_pages_render_and_keep_branch_links(): void
    {
        $branch = $this->branch();
        $product = $this->product($branch, 12);
        foreach (['show', 'products', 'favorites', 'cart', 'checkout', 'my-orders'] as $page) {
            $this->get(route('customer-menu.' . $page, $branch->code))
                ->assertOk()
                ->assertSee($branch->name);
        }
        $this->get(route('customer-menu.product.show', [$branch->code, $product->id]))->assertOk();
        $this->get(route('customer-menu.my-orders', $branch->code))
            ->assertSee(route('customer-menu.cart', $branch->code))
            ->assertSee(route('customer-menu.products', $branch->code));
    }

    public function test_branch_catalog_prices_categories_and_availability_are_isolated(): void
    {
        $a = $this->branch();
        $b = $this->branch();
        $shared = $this->product($a, 12);
        LocationProduct::create(['location_id' => $b->id, 'product_id' => $shared->id,
            'is_available' => true, 'local_selling_price' => 19]);
        RestaurantMenuItem::create(['location_id' => $b->id, 'product_id' => $shared->id,
            'is_active' => true, 'show_in_qr' => true]);
        $onlyB = $this->product($b, 25);
        $unavailable = $this->product($a, 8, false);

        foreach ([[$a, 12], [$b, 19]] as [$branch, $price]) {
            $response = $this->get(route('customer-menu.products', $branch->code))->assertOk();
            $items = $response->viewData('menuItems');
            $this->assertSame((float) $price, $items->firstWhere('product_id', $shared->id)['price']);
            $this->assertNull($items->firstWhere('product_id', $unavailable->id));
        }
        $aResponse = $this->get(route('customer-menu.products', $a->code));
        $this->assertNull($aResponse->viewData('menuItems')->firstWhere('product_id', $onlyB->id));
        $this->assertFalse($aResponse->viewData('categories')->contains('id', $onlyB->category_id));
        $this->get(route('customer-menu.product.show', [$a->code, $onlyB->id]))->assertNotFound();

        SystemSetting::set('customer_menu_show_unavailable', true);
        $response = $this->get(route('customer-menu.products', $a->code))->assertOk();
        $this->assertFalse($response->viewData('menuItems')->firstWhere('product_id', $unavailable->id)['available']);
    }

    public function test_complete_branch_catalog_is_not_truncated_at_250_items(): void
    {
        $branch = $this->branch();
        for ($i = 0; $i < 251; $i++) {
            $last = $this->product($branch, 10);
        }
        $response = $this->get(route('customer-menu.products', $branch->code))->assertOk();
        $this->assertCount(251, $response->viewData('menuItems'));
        $this->get(route('customer-menu.product.show', [$branch->code, $last->id]))->assertOk();
    }

    public function test_tables_and_payment_methods_only_include_current_branch(): void
    {
        $a = $this->branch();
        $b = $this->branch();
        $tableA = $this->table($a);
        $this->table($b);
        $cashA = $this->cash($a);
        $this->cash($b);

        $this->getJson(route('customer-menu.tables', $a->code))
            ->assertOk()->assertJsonCount(1, 'tables')->assertJsonPath('tables.0.id', $tableA->id);
        $this->getJson(route('customer-menu.payment-options', $a->code))
            ->assertOk()->assertJsonCount(1, 'payment_methods')
            ->assertJsonPath('payment_methods.0.id', $cashA->id);
    }

    public function test_inactive_non_branch_and_unknown_locations_cannot_open_menu_routes(): void
    {
        $inactive = $this->branch();
        $inactive->update(['is_active' => false]);
        $warehouse = $this->branch();
        $warehouse->update(['type' => 'factory']);
        foreach ([$inactive->code, $warehouse->code, 'missing-branch'] as $code) {
            foreach (['show', 'products', 'favorites', 'cart', 'checkout', 'my-orders',
                'tables', 'payment-options', 'queue-status'] as $page) {
                $this->get(route('customer-menu.' . $page, $code))->assertNotFound();
            }
        }
    }

    public function test_order_rejects_products_tables_and_payment_methods_from_another_branch(): void
    {
        $a = $this->branch();
        $b = $this->branch();
        $productA = $this->product($a, 10);
        $productB = $this->product($b, 20);
        $cashA = $this->cash($a);
        $cashB = $this->cash($b);
        $tableB = $this->table($b);
        $data = $this->payload($productA, $cashA);

        $this->postJson(route('customer-menu.orders.store', $a->code),
            array_replace($data, ['items' => [['product_id' => $productB->id, 'quantity' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson(route('customer-menu.orders.store', $a->code),
            array_replace($data, ['service_type' => 'dine_in', 'restaurant_table_id' => $tableB->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('restaurant_table_id');
        $this->postJson(route('customer-menu.orders.store', $a->code),
            array_replace($data, ['payment_method_id' => $cashB->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_request_token_cannot_expose_or_modify_an_order_from_another_branch(): void
    {
        $a = $this->branch();
        $b = $this->branch();
        $productB = $this->product($b, 20);
        $cashB = $this->cash($b);
        $actor = User::factory()->create();
        $token = (string) Str::uuid();
        $order = Order::create([
            'order_number' => 'WEB-' . Str::random(8), 'location_id' => $a->id,
            'created_by' => $actor->id, 'status' => 'draft',
            'payment_status' => 'pending_payment_verification',
            'payment_arrangement' => 'pending_verification',
            'subtotal' => 20, 'total_amount' => 20, 'order_source' => 'customer_menu',
            'public_token' => (string) Str::uuid(), 'public_request_id' => $token,
            'public_request_token' => $token,
        ]);
        $this->postJson(route('customer-menu.orders.store', $b->code),
            array_replace($this->payload($productB, $cashB), ['request_token' => $token]))
            ->assertUnprocessable()->assertJsonValidationErrors('request_token')
            ->assertJsonMissing(['public_token' => $order->public_token]);
        $this->assertSame('pending_payment_verification', $order->fresh()->payment_status->value);
        $this->assertSame($a->id, $order->fresh()->location_id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_order_uses_route_branch_price_and_same_branch_retry_does_not_duplicate_order(): void
    {
        $a = $this->branch();
        $b = $this->branch();
        $product = $this->product($b, 19);
        LocationProduct::create(['location_id' => $a->id, 'product_id' => $product->id,
            'local_selling_price' => 3, 'is_available' => true]);
        User::factory()->create(['is_active' => true]);
        SalesChannel::create(['name' => 'Website', 'slug' => 'website',
            'type' => 'website', 'is_active' => true]);
        $cash = $this->cash($b);
        $payload = $this->payload($product, $cash);
        $payload['location_id'] = $a->id;
        $payload['items'][0]['unit_price'] = 0.01;
        $response = $this->postJson(route('customer-menu.orders.store', $b->code), $payload)->assertCreated();
        $order = Order::findOrFail($response->json('order_id'));
        $this->assertSame($b->id, $order->location_id);
        $this->assertSame($b->id, $order->customer->location_id);
        $this->assertSame(19.0, (float) $order->items->first()->unit_price);
        $this->assertSame(19.0, (float) $order->total_amount);
        $this->postJson(route('customer-menu.orders.store', $b->code), $payload)
            ->assertCreated()->assertJsonPath('order_id', $order->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_outdoor_service_enabled_in_checkout_is_also_accepted_for_orders(): void
    {
        $branch = $this->branch();
        $product = $this->product($branch, 10);
        $cash = $this->cash($branch);
        User::factory()->create(['is_active' => true]);
        SalesChannel::create(['name' => 'Website', 'slug' => 'website',
            'type' => 'website', 'is_active' => true]);
        SystemSetting::set('customer_menu_allow_outdoor', true);
        $this->get(route('customer-menu.checkout', $branch->code))
            ->assertOk()->assertSee('value="outdoor"', false);
        $this->postJson(route('customer-menu.orders.store', $branch->code),
            array_replace($this->payload($product, $cash), ['service_type' => 'outdoor']))
            ->assertCreated();
    }

    private function branch(): Location
    {
        return Location::create(['name' => 'Branch ' . Str::random(8),
            'code' => 'BR-' . Str::upper(Str::random(8)), 'type' => 'branch', 'is_active' => true]);
    }

    private function product(Location $branch, float $price, bool $available = true): Product
    {
        $category = Category::create(['name' => 'Category ' . Str::random(12), 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Product ' . Str::random(8),
            'sku' => 'SKU-' . Str::random(12), 'barcode' => 'BAR-' . Str::random(12), 'product_type' => 'standard',
            'base_selling_price' => 5, 'is_active' => true]);
        LocationProduct::create(['location_id' => $branch->id, 'product_id' => $product->id,
            'local_selling_price' => $price, 'is_available' => $available]);
        RestaurantMenuItem::create(['location_id' => $branch->id, 'product_id' => $product->id,
            'is_active' => true, 'show_in_qr' => true, 'show_in_delivery' => true]);
        return $product;
    }

    private function cash(Location $branch): PaymentMethod
    {
        $cash = PaymentMethod::create(['name' => 'Cash ' . Str::random(8), 'name_ar' => 'نقدي',
            'code' => 'cash-' . Str::random(8), 'type' => 'cash', 'is_active' => true]);
        LocationPaymentMethod::create(['location_id' => $branch->id,
            'payment_method_id' => $cash->id, 'is_active' => true]);
        return $cash;
    }

    private function table(Location $branch): RestaurantTable
    {
        return RestaurantTable::create(['location_id' => $branch->id, 'code' => 'T-' . Str::random(8),
            'name' => 'Table ' . Str::random(8), 'is_active' => true]);
    }

    private function payload(Product $product, PaymentMethod $cash): array
    {
        return ['request_token' => (string) Str::uuid(), 'name' => 'Customer', 'phone' => '0591234567',
            'service_type' => 'takeaway', 'payment_method_id' => $cash->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    }
}

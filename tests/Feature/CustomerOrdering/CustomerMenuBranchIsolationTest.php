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

    public function test_my_orders_uses_the_same_five_tab_bottom_navigation_as_other_menu_pages(): void
    {
        $branch = $this->branch();
        $response = $this->get(route('customer-menu.my-orders', $branch->code))
            ->assertOk()
            ->assertSee('class="bottom-nav" aria-label="التنقل"', false)
            ->assertSee('class="nav-btn active"', false)
            ->assertDontSee('class="mobileNav"', false)
            ->assertSee("const cartKey = 'dahab_cart_' + locationCode;", false)
            ->assertSee("const favoriteKey = 'dahab_favorites_' + locationCode;", false)
            ->assertDontSee('window.CustomerMenu =', false);

        $html = $response->getContent();
        $this->assertSame(5, substr_count($html, '<a class="nav-btn '));
        foreach (['show', 'products', 'cart', 'favorites', 'my-orders'] as $page) {
            $this->assertStringContainsString(
                route('customer-menu.'.$page, $branch->code),
                $html
            );
        }
        $this->assertStringContainsString('height:calc(76px + var(--safe-bottom))', $html);
        $this->assertStringContainsString('padding-bottom:calc(102px + var(--safe-bottom))', $html);
    }

    public function test_tracking_page_keeps_the_five_tab_navigation_on_the_orders_tab(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertCreated()->json();

        $response = $this->get($result['track_url'])
            ->assertOk()
            ->assertSee('class="bottom-nav" aria-label="التنقل"', false)
            ->assertSee('class="nav-btn active"', false)
            ->assertSee("const cartKey = 'dahab_cart_' + locationCode;", false)
            ->assertSee("const favoriteKey = 'dahab_favorites_' + locationCode;", false)
            ->assertSee('padding-bottom:calc(102px + var(--safe-bottom))', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertDontSee('window.CustomerMenu =', false);

        $html = $response->getContent();
        $this->assertSame(5, substr_count($html, '<a class="nav-btn '));
        foreach (['show', 'products', 'cart', 'favorites', 'my-orders'] as $page) {
            $this->assertStringContainsString(route('customer-menu.'.$page, $branch->code), $html);
        }
        $this->assertStringContainsString(
            'href="'.route('customer-menu.my-orders', $branch->code).'"><i class="fa-solid fa-receipt"',
            $html
        );
    }

    public function test_all_branch_based_pages_share_one_bottom_navigation_component(): void
    {
        $files = [
            'show.blade.php', 'products.blade.php', 'favorites.blade.php',
            'product.blade.php', 'my-orders.blade.php', 'track.blade.php',
            'cart/cart.blade.php', 'checkout/checkout.blade.php',
        ];
        foreach ($files as $file) {
            $view = file_get_contents(resource_path('views/customer-menu/'.$file));
            $this->assertStringContainsString(
                "@include('customer-menu.partials.bottom-nav'",
                $view,
                'Missing shared navigation in '.$file
            );
        }

        // The branch chooser has no selected location yet, so route links
        // to five branch-specific tabs must not render on that screen.
        $chooser = file_get_contents(resource_path('views/customer-menu/branches.blade.php'));
        $this->assertStringNotContainsString('partials.bottom-nav', $chooser);
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

    private function orderFixture(): array
    {
        $branch = $this->branch();
        $product = $this->product($branch, 12);
        $cash = $this->cash($branch);
        $actor = User::factory()->create(['is_active' => true]);
        SalesChannel::create(['name' => 'Website', 'slug' => 'website',
            'type' => 'website', 'is_active' => true]);
        \App\Models\Inventory::create(['location_id' => $branch->id,
            'product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 3]);
        return [$branch, $product, $cash, $actor, $this->payload($product, $cash)];
    }

    public function test_full_takeaway_flow_preserves_inventory_invoice_and_kitchen_states(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();


        \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'ILS'],
            [
                'name' => 'Shekel',
                'is_base' => true,
                'is_active' => true,
            ]
        );

        \App\Models\FinancialPeriod::query()->firstOrCreate(
            ['year' => (int) now()->year, 'month' => (int) now()->month],
            [
                'name' => 'Restaurant QA '.now()->format('Y-m'),
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'status' => 'open',
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]
        );

        app(\App\Services\Kitchen\KitchenRoutingService::class)->createDefaultStationIfMissing($branch->id, $actor);
        $payload['items'][0]['quantity'] = 2;
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertCreated()->assertJsonPath('state', 'received')->json();
        $order = Order::findOrFail($result['order_id']);
        $inventory = \App\Models\Inventory::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(20.0, (float) $inventory->quantity);
        $this->assertNull($order->invoice);
        $orders = app(\App\Services\Orders\OrderService::class);
        $orders->confirmOrder($order, $actor);
        $this->assertSame(18.0, (float) $inventory->fresh()->quantity);
        $this->assertSame(24.0, (float) $order->fresh()->invoice->total_amount);
        $kitchen = app(\App\Services\Kitchen\KitchenTicketService::class);
        $ticket = $kitchen->dispatchConfirmedOrder($order->fresh(), $actor)->first();
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'accepted');
        $kitchen->start($ticket, $actor);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'preparing');
        $kitchen->markReady($ticket->fresh(), $actor);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'ready');
        try {
            $orders->completeOrder($order->fresh(), $actor);
            $this->fail('An unserved kitchen order must not complete.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        $kitchen->serve($ticket->fresh(), $actor);
        $orders->completeOrder($order->fresh(), $actor);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'completed');
        $this->get($result['track_url'])->assertOk()->assertSee($order->order_number);
        $this->get(route('customer-menu.invoice', $order->public_token))->assertOk();
        $this->assertSame(18.0, (float) $inventory->fresh()->quantity);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_cancellation_restores_stock_and_cancels_invoice_and_kitchen(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();


        \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'ILS'],
            [
                'name' => 'Shekel',
                'is_base' => true,
                'is_active' => true,
            ]
        );

        \App\Models\FinancialPeriod::query()->firstOrCreate(
            ['year' => (int) now()->year, 'month' => (int) now()->month],
            [
                'name' => 'Restaurant QA '.now()->format('Y-m'),
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'status' => 'open',
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]
        );

        app(\App\Services\Kitchen\KitchenRoutingService::class)->createDefaultStationIfMissing($branch->id, $actor);
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $order = Order::findOrFail($result['order_id']);
        $orders = app(\App\Services\Orders\OrderService::class);
        $orders->confirmOrder($order, $actor);
        app(\App\Services\Kitchen\KitchenTicketService::class)->dispatchConfirmedOrder($order->fresh(), $actor);
        $orders->cancelOrder($order->fresh(), 'Customer cancelled', $actor);
        $this->assertSame(20.0, (float) \App\Models\Inventory::where('product_id', $product->id)->value('quantity'));
        $this->assertSame('cancelled', $order->fresh()->invoice->statusValue());
        $this->assertDatabaseHas('kitchen_tickets', ['order_id' => $order->id, 'status' => 'cancelled']);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'cancelled');
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->assertJsonPath('state', 'cancelled');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_retry_cannot_downgrade_a_verified_payment_or_change_its_reference(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $payment = \App\Models\Payment::create(['order_type' => 'order', 'order_id' => $result['order_id'],
            'location_id' => $branch->id, 'payment_method_id' => $cash->id, 'received_by' => $actor->id,
            'amount' => 12, 'status' => 'confirmed', 'reference_number' => 'verified-reference', 'paid_at' => now()]);
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $result['order_id'])->update(['payment_status' => 'paid']);
        $payload['payment_reference'] = 'replacement';
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated();
        $this->assertSame('paid', Order::findOrFail($result['order_id'])->payment_status->value);
        $this->assertSame('verified-reference', $payment->fresh()->reference_number);
        $this->assertSame('confirmed', $payment->fresh()->statusValue());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_failed_proof_upload_rolls_back_order_customer_and_table_reservation(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $bank = PaymentMethod::create(['name' => 'Bank', 'name_ar' => 'تحويل بنكي', 'code' => 'bank', 'type' => 'bank_transfer',
            'is_active' => true, 'requires_verification' => true]);
        LocationPaymentMethod::create(['location_id' => $branch->id, 'payment_method_id' => $bank->id, 'is_active' => true]);
        $table = $this->table($branch);
        $payload = array_replace($payload, ['payment_method_id' => $bank->id, 'service_type' => 'dine_in',
            'restaurant_table_id' => $table->id, 'payment_proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg')]);
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('payment_proof');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('restaurant_table_sessions', 0);
    }

    public function test_duplicate_lines_over_quantity_limit_are_rejected_without_silent_truncation(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $payload['items'] = array_fill(0, 2, ['product_id' => $product->id, 'quantity' => 30]);
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_variant_specific_required_choices_render_and_price_correctly_at_checkout(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $product->update(['product_type' => 'variant']);
        $variant = \App\Models\ProductVariant::create(['product_id' => $product->id, 'name' => 'Large',
            'sku' => 'LARGE', 'selling_price' => 18, 'is_active' => true]);
        $group = \App\Models\ModifierGroup::create(['code' => 'topping', 'name' => 'Topping',
            'selection_type' => 'single', 'is_active' => true]);
        $modifier = \App\Models\Modifier::create(['modifier_group_id' => $group->id,
            'code' => 'extra', 'name' => 'Extra', 'price_delta' => 2, 'is_active' => true]);
        \App\Models\ProductModifierGroup::create(['product_id' => $product->id, 'product_variant_id' => $variant->id,
            'modifier_group_id' => $group->id, 'is_active' => true, 'is_required_override' => true]);
        $response = $this->get(route('customer-menu.product.show', [$branch->code, $product->id]))->assertOk();
        $catalog = $response->viewData('product');
        $this->assertSame([], $catalog['modifier_groups']);
        $this->assertTrue((bool) $catalog['variants'][0]['modifier_groups'][0]['required']);
        $payload['items'][0]['product_variant_id'] = $variant->id;
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $payload['items'][0]['modifiers'] = [['modifier_id' => $modifier->id, 'quantity' => 1]];
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $this->assertSame(20.0, (float) Order::findOrFail($result['order_id'])->total_amount);
    }

    public function test_dine_in_reserves_table_and_staff_can_close_it_after_cancellation(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        $table = $this->table($branch);
        $payload = array_replace($payload, ['service_type' => 'dine_in', 'restaurant_table_id' => $table->id]);
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $order = Order::findOrFail($result['order_id']);
        $this->assertNotNull($order->restaurant_table_session_id);
        $this->postJson(route('customer-menu.orders.store', $branch->code), array_replace($payload, ['request_token' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('restaurant_table_id');
        $this->assertDatabaseCount('orders', 1);
        app(\App\Services\Orders\OrderService::class)->cancelOrder($order, 'Cancelled reservation', $actor);
        app(\App\Services\Restaurant\RestaurantTableService::class)->close($table, $actor, true);
        $this->assertFalse($table->fresh()->isOccupied());
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('state', 'cancelled');
    }

    public function test_delivery_address_and_channel_availability_are_enforced(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        SystemSetting::set('customer_menu_allow_delivery', true);
        $payload['service_type'] = 'delivery';
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('address');
        $payload['address'] = 'Gaza, Main Street';
        RestaurantMenuItem::where('product_id', $product->id)->update(['show_in_delivery' => false]);
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        RestaurantMenuItem::where('product_id', $product->id)->update(['show_in_delivery' => true]);
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $this->assertDatabaseHas('orders', ['id' => $result['order_id'], 'delivery_address' => $payload['address']]);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('service_type', 'delivery');
    }

    public function test_bank_proof_remains_pending_and_retry_does_not_replace_it(): void
    {
        [$branch, $product, $cash, $actor, $payload] = $this->orderFixture();
        \Illuminate\Support\Facades\Storage::fake('local');
        $bank = PaymentMethod::create(['name' => 'Bank', 'name_ar' => 'تحويل بنكي', 'code' => 'bank', 'type' => 'bank_transfer',
            'is_active' => true, 'requires_verification' => true, 'requires_reference' => true]);
        LocationPaymentMethod::create(['location_id' => $branch->id, 'payment_method_id' => $bank->id, 'is_active' => true]);
        $payload = array_replace($payload, ['payment_method_id' => $bank->id,
            'payment_reference' => 'TX-1', 'payment_proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg')]);
        $result = $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated()->json();
        $payment = \App\Models\Payment::sole();
        $this->assertSame('pending_verification', $payment->statusValue());
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($payment->payment_proof);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('payment_status', 'pending_payment_verification');
        $payload['payment_reference'] = 'changed';
        $this->postJson(route('customer-menu.orders.store', $branch->code), $payload)->assertCreated();
        $this->assertSame('TX-1', $payment->fresh()->reference_number);
        $this->assertDatabaseCount('payments', 1);
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


<?php

namespace Tests\Feature\Restaurant;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\RestaurantTableSession;
use App\Models\User;
use App\Services\Restaurant\RestaurantTableAccountService;
use App\Services\Restaurant\RestaurantTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableSessionAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_stays_unchanged_while_new_order_has_outstanding_balance(): void
    {
        [$actor, $table, $session] = $this->makeTableSession();

        $firstOrder = $this->makeOrder($actor, $table, $session, 'TISH-OLD', 58);
        $firstInvoice = $this->makeInvoice($actor, $table, $firstOrder, 'INV-OLD', 58, 58);
        $newOrder = $this->makeOrder($actor, $table, $session, 'TISH-EXTRA', 24);
        $newInvoice = $this->makeInvoice($actor, $table, $newOrder, 'INV-EXTRA', 24, 0);

        $account = app(RestaurantTableAccountService::class)->summary($session->fresh());
        $this->assertCount(2, $account['rows']);
        $this->assertSame(8200, $account['total_cents']);
        $this->assertSame(5800, $account['paid_cents']);
        $this->assertSame(2400, $account['outstanding_cents']);
        $this->assertSame('INV-OLD', $firstInvoice->fresh()->invoice_number);
        $this->assertSame('58.00', $firstInvoice->fresh()->paid_amount);

        try {
            app(RestaurantTableService::class)->close($table, $actor, true);
            $this->fail('Closing an occupied table with unsettled charges must be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('restaurant_table_id', $e->errors());
        }

        $this->assertTrue($session->fresh()->isOpen());

        $newInvoice->update(['paid_amount' => 24, 'remaining_amount' => 0]);
        $updated = app(RestaurantTableAccountService::class)->summary($session->fresh());
        $this->assertSame(0, $updated['outstanding_cents']);

        // Settlement alone is not guest departure.
        try {
            app(RestaurantTableService::class)->close($table, $actor, false);
            $this->fail('Guest departure confirmation should be required.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guests_departed', $e->errors());
        }

        $closed = app(RestaurantTableService::class)->close($table, $actor, true);
        $this->assertFalse($closed->isOpen());
        $this->assertSame('58.00', $firstInvoice->fresh()->paid_amount);
        $this->assertSame('INV-OLD', $firstInvoice->fresh()->invoice_number);
    }

    private function makeTableSession(): array
    {
        $actor = User::factory()->create();
        $location = Location::query()->create([
            'name' => 'Tish Table Accounts',
            'code' => 'TISH-ACCOUNTS',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'location_id' => $location->id,
            'code' => '4',
            'name' => 'Table 4',
            'capacity' => 4,
            'is_active' => true,
        ]);
        $session = RestaurantTableSession::query()->create([
            'restaurant_table_id' => $table->id,
            'location_id' => $location->id,
            'opened_by' => $actor->id,
            'status' => 'open',
            'guest_count' => 4,
            'opened_at' => now(),
        ]);

        return [$actor, $table, $session];
    }

    private function makeOrder(User $actor, RestaurantTable $table, RestaurantTableSession $session, string $number, int $amount): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'location_id' => $table->location_id,
            'created_by' => $actor->id,
            'restaurant_service_type' => 'dine_in',
            'restaurant_table_id' => $table->id,
            'restaurant_table_session_id' => $session->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_arrangement' => 'pay_now',
            'subtotal' => $amount,
            'total_amount' => $amount,
        ]);
    }

    private function makeInvoice(User $actor, RestaurantTable $table, Order $order, string $number, int $total, int $paid): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => $number,
            'invoice_type' => 'regular_order',
            'order_type' => 'order',
            'order_id' => $order->id,
            'location_id' => $table->location_id,
            'status' => 'active',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => $total - $paid,
            'issued_by' => $actor->id,
            'issued_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature\Restaurant;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\Restaurant\RestaurantDiningGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiningGroupAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_opens_three_tables_atomically_and_totals_each_original_invoice(): void
    {
        $actor = User::factory()->create();
        $location = $this->location('DINING-123');
        $tables = collect(['6', '7', '8'])->map(fn ($code) => $this->table($location, $code));
        $groups = app(RestaurantDiningGroupService::class);

        $group = $groups->open($location->id, $tables->pluck('id')->all(), 'Institution ABC', 12, $actor);
        $this->assertCount(3, $group->sessions);
        $this->assertSame(12, $group->sessions->sum('guest_count'));
        $this->assertTrue($tables->every(fn ($table) => $table->fresh()->isOccupied()));
        $this->assertTrue($group->sessions->every(
            fn ($session) => $session->restaurant_dining_group_id === $group->id
        ));

        $first = $group->sessions->first();
        $second = $group->sessions->skip(1)->first();

        $oldOrder = $this->order($actor, $first, 'ORD-G6-PAID', 58);
        $oldInvoice = $this->invoice($actor, $oldOrder, 'INV-G6-PAID', 58, 58);
        $newOrder = $this->order($actor, $second, 'ORD-G7-NEW', 20);
        $secondInvoice = $this->invoice($actor, $newOrder, 'INV-G7-NEW', 20, 0);

        $statement = $groups->statement($group->fresh());
        $this->assertSame(7800, $statement['total_cents']);
        $this->assertSame(5800, $statement['paid_cents']);
        $this->assertSame(2000, $statement['outstanding_cents']);
        $this->assertSame('INV-G6-PAID', $oldInvoice->fresh()->invoice_number);
        $this->assertSame('58.00', $oldInvoice->fresh()->paid_amount);

        // Settlement on the second invoice permits releasing all three tables.
        $secondInvoice->update(['paid_amount' => 20, 'remaining_amount' => 0]);
        foreach ($group->sessions as $session) {
            app(\App\Services\Restaurant\RestaurantTableService::class)->close(
                $session->table, $actor, true
            );
        }
        $this->assertFalse($group->fresh()->isOpen());
        $this->assertSame('58.00', $oldInvoice->fresh()->paid_amount);
    }

    public function test_failed_group_open_does_not_reserve_any_other_table(): void
    {
        $actor = User::factory()->create();
        $location = $this->location('DINING-FAIL');
        $tables = collect(['6','7','8'])->map(fn ($code) => $this->table($location, $code));
        $groups = app(RestaurantDiningGroupService::class);

        $groups->open($location->id, [$tables[0]->id, $tables[1]->id], 'Existing Group', 4, $actor);

        try {
            $groups->open($location->id, [$tables[1]->id, $tables[2]->id], 'Should Fail', 4, $actor);
            $this->fail('Occupied table must not be assigned to a second group.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('table_ids', $e->errors());
        }
        $this->assertFalse($tables[2]->fresh()->isOccupied());
        $this->assertDatabaseMissing('restaurant_dining_groups', ['name' => 'Should Fail']);
    }

    private function location(string $code): Location
    {
        return Location::query()->create([
            'name' => $code, 'code' => $code, 'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function table(Location $location, string $code): RestaurantTable
    {
        return RestaurantTable::query()->create([
            'location_id' => $location->id,
            'code' => $code,
            'name' => 'Table '.$code,
            'capacity' => 4,
            'is_active' => true,
        ]);
    }

    private function order(User $actor, $session, string $number, int $total): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'location_id' => $session->location_id,
            'created_by' => $actor->id,
            'restaurant_table_id' => $session->restaurant_table_id,
            'restaurant_table_session_id' => $session->id,
            'restaurant_service_type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'subtotal' => $total,
            'total_amount' => $total,
        ]);
    }

    private function invoice(User $actor, Order $order, string $number, int $total, int $paid): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => $number,
            'invoice_type' => 'regular_order',
            'order_type' => 'order',
            'order_id' => $order->id,
            'location_id' => $order->location_id,
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

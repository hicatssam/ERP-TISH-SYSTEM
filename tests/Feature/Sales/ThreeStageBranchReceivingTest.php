<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Enums\ShowroomCakeRequestStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\Delivery\BranchDeliveryService;
use App\Support\BranchOrderMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ThreeStageBranchReceivingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function showroom_cake_is_received_from_loaded_state_with_itemized_partial_invoice(): void
    {
        Notification::fake();
        [$branch, $factory, $receiver, $factoryUser] = $this->actors();
        $cake = ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-STEP-'.Str::upper(Str::random(10)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::Ready,
            'created_by' => $receiver->id,
        ]);
        $cake->items()->create(['cake_type' => 'شوكولاتة', 'cake_size' => 'large', 'quantity' => 5]);
        $cake->items()->create(['cake_type' => 'فانيلا', 'cake_size' => 'small', 'quantity' => 3]);

        $delivery = app(BranchDeliveryService::class);
        $this->assertSame('تم التنفيذ', BranchOrderMilestones::label($cake));

        $shipment = $delivery->createShipment($cake, $factoryUser);
        $this->assertSame('تم التنفيذ', BranchOrderMilestones::label($cake->fresh()));

        $this->actingAs($receiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $this->receivedItems($shipment, [4, 1]),
        ])->assertSessionHasErrors(['status']);

        $driver = User::factory()->create();
        $shipment = $delivery->markLoaded($shipment, $factoryUser, $driver, 'تم التحميل من المصنع');
        $this->assertSame('loaded', $shipment->status);
        $this->assertSame('تم التحميل', BranchOrderMilestones::label($cake->fresh()));

        $this->actingAs($receiver)
            ->get(route('branch-shipments.show', $shipment))
            ->assertOk()
            ->assertSee('تم التنفيذ')
            ->assertSee('تم التحميل')
            ->assertSee('تم الاستلام')
            ->assertDontSee('خرجت للتوصيل')
            ->assertDontSee('وصلت إلى الفرع');

        $rows = $this->receivedItems($shipment, [4, 1]);
        $this->actingAs($receiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $rows,
            'notes' => 'ناقص ثلاث قطع بعد الجرد',
        ])->assertRedirect();

        $invoice = $shipment->fresh()->receivingInvoice;
        $this->assertSame('confirmed', $invoice->status);
        $this->assertEquals(8.0, (float) $invoice->total_items_sent);
        $this->assertEquals(5.0, (float) $invoice->total_items_received);
        $this->assertEquals(3.0, (float) $invoice->total_difference);
        $this->assertSame('ناقص ثلاث قطع بعد الجرد', $invoice->notes);
        $this->assertEqualsCanonicalizing([1.0, 2.0],
            $invoice->items->pluck('difference_quantity')->map(fn ($n) => (float) $n)->all());
        $this->assertSame(ShowroomCakeRequestStatus::Completed, $cake->fresh()->status);
        $this->assertSame('تم الاستلام', BranchOrderMilestones::label($cake->fresh()));
        $this->assertNull($shipment->fresh()->dispatched_at);
        $this->assertNull($shipment->fresh()->arrived_at);

        $this->actingAs($receiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $rows,
        ])->assertSessionHasErrors(['status']);
        $this->assertSame('confirmed', $invoice->fresh()->status);

        $this->actingAs($receiver)->get(route('branch-receiving-invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    #[Test]
    public function special_cake_completes_only_with_confirmed_invoice_after_loading(): void
    {
        Notification::fake();
        [$branch, $factory, $receiver, $factoryUser] = $this->actors();
        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Step Test Customer',
            'phone' => '0599000217',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
        $cake = SpecialCakeOrder::query()->create([
            'order_number' => 'CK-STEP-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id,
            'origin_branch_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'required_date' => now()->addDays(2)->toDateString(),
            'status' => CakeOrderStatus::Ready,
            'cake_type' => 'chocolate',
            'cake_size' => 'large',
            'total_price' => 100,
            'payment_arrangement' => 'pay_on_pickup',
            'payment_status' => 'payment_pending',
            'created_by' => $receiver->id,
        ]);
        $delivery = app(BranchDeliveryService::class);
        $shipment = $delivery->createShipment($cake, $factoryUser);
        $shipment = $delivery->markLoaded($shipment, $factoryUser, User::factory()->create());

        $this->assertSame('تم التحميل', BranchOrderMilestones::label($cake->fresh()));

        $this->actingAs($receiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $this->receivedItems($shipment, [1]),
        ])->assertRedirect();

        $this->assertSame('confirmed', $shipment->fresh()->receivingInvoice->status);
        $this->assertSame(CakeOrderStatus::Completed, $cake->fresh()->status);
        $this->assertSame('تم الاستلام', BranchOrderMilestones::label($cake->fresh()));
    }

    /** @return array{Location, Location, User, User} */
    private function actors(): array
    {
        $branch = Location::query()->create([
            'name' => 'Test receiving branch', 'code' => 'BR-'.Str::upper(Str::random(8)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'Test receiving factory', 'code' => 'FA-'.Str::upper(Str::random(8)),
            'type' => 'factory', 'is_active' => true,
        ]);
        $receiver = User::factory()->create();
        $receiver->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $factoryUser = User::factory()->create();
        $factoryUser->employee->locations()->attach($factory->id, ['is_primary' => true]);

        foreach ([
            'showroom_cake_requests.view', 'showroom_cake_requests.create',
            'cake_orders.view', 'cake_orders.receive',
        ] as $p) {
            $receiver->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $factoryUser->givePermissionTo(Permission::findOrCreate('showroom_cake_requests.update_status', 'web'));
        $factoryUser->givePermissionTo(Permission::findOrCreate('cake_orders.dispatch', 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $factory, $receiver->fresh(), $factoryUser->fresh()];
    }

    private function receivedItems($shipment, array $quantities): array
    {
        $result = [];
        foreach ($shipment->receivingInvoice->items->values() as $index => $item) {
            $result[$item->id] = [
                'received_quantity' => $quantities[$index],
                'note' => 'تم جرد البند '.($index + 1),
            ];
        }
        return $result;
    }
}

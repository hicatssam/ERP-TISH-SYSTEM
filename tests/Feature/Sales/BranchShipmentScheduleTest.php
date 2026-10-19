<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Models\BranchShipment;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\User;
use App\Services\Delivery\BranchDeliveryService;
use App\Services\Delivery\BranchDeliveryTripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchShipmentScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    #[Test]
    public function factory_dispatcher_sets_distinct_planned_times_without_forging_actual_events(): void
    {
        [$branch, $factory, $dispatcher, $receiver, $driver, $shipment] = $this->shipment();
        $url = route('branch-shipments.schedule', $shipment);

        $this->actingAs($dispatcher)->patch($url, [
            'planned_loading_at' => '2026-10-20T07:30',
            'planned_receiving_at' => '2026-10-20T09:45',
        ])->assertRedirect(route('branch-shipments.show', $shipment))
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame('2026-10-20 07:30', $shipment->planned_loading_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 09:45', $shipment->planned_receiving_at->format('Y-m-d H:i'));
        $this->assertNull($shipment->loaded_at);
        $this->assertNull($shipment->received_at);

        $this->actingAs($dispatcher)->get(route('branch-shipments.index'))
            ->assertOk()
            ->assertSee('2026-10-20 07:30')
            ->assertSee('2026-10-20 09:45');

        $this->actingAs($receiver)->get(route('branch-shipments.show', $shipment))
            ->assertOk()->assertSee('2026-10-20 09:45');
    }

    #[Test]
    public function branch_user_cannot_change_factory_schedule_even_when_can_read_the_shipment(): void
    {
        [, , $dispatcher, $receiver, , $shipment] = $this->shipment();
        $this->actingAs($receiver)
            ->patch(route('branch-shipments.schedule', $shipment), [
                'planned_loading_at' => '2026-10-20T08:00',
            ])->assertForbidden();

        $this->assertNull($shipment->fresh()->planned_loading_at);
    }

    #[Test]
    public function invalid_sequence_is_rejected_without_overwriting_existing_times(): void
    {
        [, , $dispatcher, , , $shipment] = $this->shipment();
        $this->actingAs($dispatcher)->patch(route('branch-shipments.schedule', $shipment), [
            'planned_loading_at' => '2026-10-20T11:00',
            'planned_receiving_at' => '2026-10-20T09:00',
        ])->assertSessionHasErrors(['planned_receiving_at']);

        $this->assertNull($shipment->fresh()->planned_loading_at);
        $this->assertNull($shipment->fresh()->planned_receiving_at);
    }

    #[Test]
    public function loaded_trips_freeze_planned_schedule_but_keep_actual_load_and_receipt_times(): void
    {
        [, , $dispatcher, $receiver, $driver, $shipment] = $this->shipment();
        $url = route('branch-shipments.schedule', $shipment);
        $this->actingAs($dispatcher)->patch($url, [
            'planned_loading_at' => '2026-10-20T07:30',
            'planned_receiving_at' => '2026-10-20T09:45',
        ])->assertSessionHasNoErrors();

        $item = $shipment->receivingInvoice->items->sole();
        $service = app(BranchDeliveryTripService::class);
        $trip = $service->loadTrip($shipment, $dispatcher, $driver, [
            $item->id => ['quantity' => (float) $item->sent_quantity],
        ], null);

        $this->assertNotNull($shipment->fresh()->loaded_at);
        $this->actingAs($dispatcher)->patch($url, [
            'planned_loading_at' => '2026-10-21T07:30',
            'planned_receiving_at' => '2026-10-21T09:45',
        ])->assertSessionHasErrors(['schedule']);

        $this->assertSame('2026-10-20 07:30', $shipment->fresh()->planned_loading_at->format('Y-m-d H:i'));
        $result = $service->receiveTrip($trip, $receiver, [
            $trip->items->sole()->id => ['received_quantity' => (float) $item->sent_quantity],
        ], null);

        $this->assertTrue($result['complete']);
        $this->assertNotNull($shipment->fresh()->received_at);
        $this->assertNotNull($trip->fresh()->received_at);
    }

    #[Test]
    public function schedules_belong_to_each_shipment_and_do_not_change_other_branches(): void
    {
        [, , $dispatcher, , , $first] = $this->shipment();
        [$branch2, $factory2, $dispatcher2, , , $second] = $this->shipment();

        $this->actingAs($dispatcher)->patch(route('branch-shipments.schedule', $first), [
            'planned_loading_at' => '2026-10-20T07:00',
            'planned_receiving_at' => '2026-10-20T08:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($dispatcher2)->patch(route('branch-shipments.schedule', $second), [
            'planned_loading_at' => '2026-10-20T10:00',
            'planned_receiving_at' => '2026-10-20T11:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('07:00', $first->fresh()->planned_loading_at->format('H:i'));
        $this->assertSame('10:00', $second->fresh()->planned_loading_at->format('H:i'));
        $this->assertNotSame($first->to_location_id, $second->to_location_id);
    }

    /** @return array{Location, Location, User, User, User, BranchShipment} */
    private function shipment(): array
    {
        $branch = Location::query()->create([
            'name' => 'Branch schedule',
            'code' => 'BS-'.Str::upper(Str::random(7)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'Factory schedule',
            'code' => 'FS-'.Str::upper(Str::random(7)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $dispatcher = User::factory()->create();
        $dispatcher->employee->locations()->attach($factory->id, ['is_primary' => true]);
        $dispatcher->givePermissionTo(Permission::findOrCreate('branch_shipments.manage', 'web'));

        $receiver = User::factory()->create();
        $receiver->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $receiver->givePermissionTo(Permission::findOrCreate('showroom_cake_requests.create', 'web'));
        $driver = User::factory()->create();

        $order = ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-SCH-'.Str::upper(Str::random(9)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::Ready,
            'created_by' => $receiver->id,
        ]);
        $order->items()->create([
            'cake_type' => 'chocolate',
            'cake_size' => 'large',
            'quantity' => 5,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        return [$branch, $factory, $dispatcher->fresh(), $receiver->fresh(), $driver, $shipment];
    }
}

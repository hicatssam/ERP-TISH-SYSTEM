<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Models\BranchReceivingInvoice;
use App\Models\BranchShipment;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\User;
use App\Services\Delivery\BranchDeliveryService;
use App\Services\Delivery\BranchDeliveryTripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchDispatcherTripsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    #[Test]
    public function two_trips_only_issue_one_final_invoice_when_each_requested_item_is_received(): void
    {
        [$branch, $factory, $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $this->assertTrue($shipment->multi_trip_enabled);
        $this->assertNull($shipment->receivingInvoice->invoice_number);

        $line = $shipment->receivingInvoice->items->sole();
        $tripService = app(BranchDeliveryTripService::class);
        $trip1 = $tripService->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 3],
        ], 'النقلة الأولى');
        $this->assertSame(1, $trip1->trip_number);
        $outcome1 = $tripService->receiveTrip($trip1, $receiver, [
            $trip1->items->first()->id => ['received_quantity' => 3],
        ], 'وصلت كاملة');
        $this->assertFalse($outcome1['complete']);
        $this->assertFalse($outcome1['invoice']->isConfirmed());
        $this->assertNull($outcome1['invoice']->issued_at);
        $this->assertSame('ready', $order->fresh()->status->workflowValue());

        $trip2 = $tripService->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 2],
        ], null);
        $this->assertSame(2, $trip2->trip_number);
        $outcome2 = $tripService->receiveTrip($trip2, $receiver, [
            $trip2->items->first()->id => ['received_quantity' => 2],
        ], null);

        $this->assertTrue($outcome2['complete']);
        $this->assertTrue($outcome2['invoice']->isConfirmed());
        $this->assertNotNull($outcome2['invoice']->issued_at);
        $this->assertNotNull($outcome2['invoice']->invoice_number);
        $this->assertSame(0.0, (float) $outcome2['invoice']->total_difference);
        $this->assertSame('completed', $order->fresh()->status->workflowValue());
        $this->assertSame(BranchShipment::RECEIVED, $shipment->fresh()->status);
        $this->assertSame(1, BranchReceivingInvoice::query()->count());

        $this->actingAs($receiver)->get(route('branch-receiving-invoices.show', $outcome2['invoice']))->assertOk();
    }

    #[Test]
    public function short_received_trip_creates_remaining_quantity_instead_of_a_final_invoice(): void
    {
        [, , $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $line = $shipment->receivingInvoice->items->sole();
        $service = app(BranchDeliveryTripService::class);
        $first = $service->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 4],
        ], null);
        $result = $service->receiveTrip($first, $receiver, [
            $first->items->sole()->id => ['received_quantity' => 3, 'note' => 'قطعة ناقصة'],
        ], null);

        $this->assertFalse($result['complete']);
        $this->assertSame(2.0, $service->progress($shipment->fresh())['lines'][$line->id]['available']);
        $this->assertNull($result['invoice']->issued_at);

        $second = $service->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 2],
        ], null);
        $outcome = $service->receiveTrip($second, $receiver, [
            $second->items->sole()->id => ['received_quantity' => 2],
        ], null);
        $this->assertTrue($outcome['complete']);
        $this->assertSame(2, $shipment->trips()->count());
    }

    #[Test]
    public function dispatched_quantities_reserve_capacity_and_prevent_overloading_or_duplicate_receipt(): void
    {
        [, , $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $line = $shipment->receivingInvoice->items->sole();
        $service = app(BranchDeliveryTripService::class);
        $first = $service->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 4],
        ], null);

        try {
            $service->loadTrip($shipment, $dispatcher, $driver, [
                $line->id => ['quantity' => 2],
            ], null);
            $this->fail('Overloading should be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $service->receiveTrip($first, $receiver, [
            $first->items->sole()->id => ['received_quantity' => 4],
        ], null);

        $this->expectException(ValidationException::class);
        $service->receiveTrip($first, $receiver, [
            $first->items->sole()->id => ['received_quantity' => 4],
        ], null);
    }

    #[Test]
    public function final_invoice_is_unavailable_before_full_receipt_and_branch_access_is_enforced(): void
    {
        [, $factory, $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $this->actingAs($receiver)->get(route('branch-receiving-invoices.show', $shipment->receivingInvoice))->assertNotFound();

        $otherBranch = Location::query()->create([
            'name' => 'Other', 'code' => 'OT-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $outsider = User::factory()->create();
        $outsider->employee->locations()->attach($otherBranch->id, ['is_primary' => true]);
        $this->actingAs($outsider)->get(route('branch-shipments.show', $shipment))->assertForbidden();
        $this->actingAs($outsider)->post(route('branch-shipments.trips.store', $shipment), [
            'driver_id' => $driver->id, 'items' => [$shipment->receivingInvoice->items->sole()->id => ['quantity' => 5]],
        ])->assertForbidden();
    }


    #[Test]
    public function dispatcher_can_close_short_received_order_with_mandatory_reason_and_exact_invoice_difference(): void
    {
        [$branch, , $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $line = $shipment->receivingInvoice->items->sole();
        $service = app(BranchDeliveryTripService::class);
        $trip = $service->loadTrip($shipment, $dispatcher, $driver, [
            $line->id => ['quantity' => 3],
        ], null);
        $service->receiveTrip($trip, $receiver, [
            $trip->items->sole()->id => ['received_quantity' => 2],
        ], null);

        $this->actingAs($dispatcher)
            ->get(route('branch-shipments.show', $shipment))
            ->assertOk()->assertSee('إغلاق الطلبية مع وجود نقص');

        $reason = 'نفدت مكونات الصنف قبل إكمال الكمية المتبقية';
        $response = $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => $reason]
        );
        $invoice = BranchReceivingInvoice::query()->sole();
        $response->assertRedirect(route('branch-receiving-invoices.show', $invoice));
        $this->assertSame(BranchShipment::CLOSED_SHORT, $shipment->fresh()->status);
        $this->assertSame($reason, $shipment->fresh()->closure_reason);
        $this->assertSame($dispatcher->id, $shipment->fresh()->closed_by);
        $this->assertNotNull($shipment->fresh()->closed_at);
        $this->assertTrue($invoice->isConfirmed());
        $this->assertEquals(5.0, (float) $invoice->total_items_sent);
        $this->assertEquals(2.0, (float) $invoice->total_items_received);
        $this->assertEquals(3.0, (float) $invoice->total_difference);
        $this->assertEquals(2.0, (float) $invoice->items()->sole()->received_quantity);
        $this->assertSame('closed_short', $order->fresh()->status->workflowValue());
        $this->assertSame($branch->id, $invoice->receiving_location_id);

        $this->actingAs($receiver)
            ->get(route('branch-receiving-invoices.show', $invoice))
            ->assertOk()->assertSee($reason);
        $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => 'تكرار محاولة الإغلاق النهائي للطلبية']
        )->assertSessionHasErrors('shipment');
        $this->assertSame(1, BranchReceivingInvoice::query()->count());
    }

    #[Test]
    public function dispatcher_cannot_close_without_reason_or_before_branch_receives_a_trip(): void
    {
        [, , $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $line = $shipment->receivingInvoice->items->sole();

        $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => '']
        )->assertSessionHasErrors('closure_reason');

        $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => 'نقص في المصنع وعدم القدرة على إكمال الطلب']
        )->assertSessionHasErrors('shipment');

        $trip = app(BranchDeliveryTripService::class)->loadTrip(
            $shipment, $dispatcher, $driver,
            [$line->id => ['quantity' => 3]], null
        );

        $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => 'نقص في المصنع وعدم القدرة على إكمال الطلب']
        )->assertSessionHasErrors('shipment');

        app(BranchDeliveryTripService::class)->receiveTrip($trip, $receiver, [
            $trip->items->sole()->id => ['received_quantity' => 3],
        ], null);

        $this->actingAs($receiver)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => 'محاولة إغلاق من موظف الفرع بدون صلاحية']
        )->assertForbidden();
        $this->assertFalse($shipment->fresh()->receivingInvoice->isConfirmed());
        $this->assertSame('ready', $order->fresh()->status->workflowValue());
    }

    #[Test]
    public function dispatcher_cannot_close_a_fully_received_shipment_as_short(): void
    {
        [, , $dispatcher, $receiver, $driver, $order] = $this->prepareOrder();
        $shipment = app(BranchDeliveryService::class)->createShipment($order, $dispatcher);
        $line = $shipment->receivingInvoice->items->sole();
        $trip = app(BranchDeliveryTripService::class)->loadTrip(
            $shipment, $dispatcher, $driver,
            [$line->id => ['quantity' => 5]], null
        );
        app(BranchDeliveryTripService::class)->receiveTrip($trip, $receiver, [
            $trip->items->sole()->id => ['received_quantity' => 5],
        ], null);

        $this->actingAs($dispatcher)->post(
            route('branch-shipments.close-short', $shipment),
            ['closure_reason' => 'محاولة اعتبار الطلبية المكتملة طلبية ناقصة']
        )->assertSessionHasErrors('shipment');
        $this->assertSame(BranchShipment::RECEIVED, $shipment->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status->workflowValue());
    }

    /** @return array{Location,Location,User,User,User,ShowroomCakeRequest} */
    private function prepareOrder(): array
    {
        $branch = Location::query()->create([
            'name' => 'Branch', 'code' => 'B-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'Factory', 'code' => 'F-'.Str::upper(Str::random(6)),
            'type' => 'factory', 'is_active' => true,
        ]);

        $dispatcher = User::factory()->create();
        $dispatcher->employee->locations()->attach($factory->id, ['is_primary' => true]);
        $dispatcher->givePermissionTo(Permission::findOrCreate('branch_shipments.manage', 'web'));
        $dispatcher->givePermissionTo(Permission::findOrCreate('branch_shipments.close_short', 'web'));

        $receiver = User::factory()->create();
        $receiver->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $receiver->givePermissionTo(Permission::findOrCreate('showroom_cake_requests.create', 'web'));

        $driver = User::factory()->create();
        $order = ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-TRIP-'.Str::upper(Str::random(8)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::Ready,
            'created_by' => $receiver->id,
        ]);
        $order->items()->create([
            'cake_type' => 'chocolate', 'cake_size' => 'large', 'quantity' => 5,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return [$branch, $factory, $dispatcher->fresh(), $receiver->fresh(), $driver, $order];
    }
}

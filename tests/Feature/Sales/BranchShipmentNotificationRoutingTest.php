<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\BranchShipmentActivityNotification;
use App\Services\Delivery\BranchDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchShipmentNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    #[Test]
    public function loaded_cake_shipment_notifies_its_branch_factory_driver_and_admin_but_not_other_branches(): void
    {
        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $otherBranch = $this->location('branch');

        $receiver = $this->userAt($branch, ['showroom_cake_requests.view']);
        $factoryActor = $this->userAt($factory, ['showroom_cake_requests.update_status']);
        $factoryColleague = $this->userAt($factory, ['showroom_cake_requests.view']);
        $differentBranchViewer = $this->userAt($otherBranch, ['showroom_cake_requests.view']);
        $unrelatedEmployee = $this->userAt($branch);
        $inactiveViewer = $this->userAt($branch, ['showroom_cake_requests.view'], false);
        $globalViewer = $this->userAt($otherBranch, ['showroom_cake_requests.view_all']);
        $admin = $this->userAt($otherBranch, [], true, true);
        $driver = $this->userAt($otherBranch);

        $request = $this->cake($branch, $factory, $receiver);
        $service = app(BranchDeliveryService::class);
        $shipment = $service->createShipment($request, $factoryActor);
        $this->assertSame('pending_loading', $shipment->status);

        $service->markLoaded($shipment, $factoryActor, $driver, 'الطلبية تم تجهيزها للتحميل');

        foreach ([$receiver, $factoryColleague, $globalViewer, $admin, $driver] as $user) {
            Notification::assertSentTo(
                $user,
                BranchShipmentActivityNotification::class,
                fn ($notification) => $notification->toDatabase($user)['type'] === 'branch_shipment_loaded'
                    && $notification->toDatabase($user)['branch_shipment_id'] === $shipment->id
            );
        }

        foreach ([$factoryActor, $differentBranchViewer, $unrelatedEmployee, $inactiveViewer] as $user) {
            Notification::assertNotSentTo($user, BranchShipmentActivityNotification::class);
        }
    }

    #[Test]
    public function receiving_invoice_notifies_involved_roles_with_the_actual_difference_once(): void
    {
        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $otherBranch = $this->location('branch');
        $receiver = $this->userAt($branch, ['showroom_cake_requests.create']);
        $branchManager = $this->userAt($branch, ['showroom_cake_requests.view']);
        $factoryActor = $this->userAt($factory, ['showroom_cake_requests.update_status']);
        $driver = $this->userAt($otherBranch);
        $otherBranchViewer = $this->userAt($otherBranch, ['showroom_cake_requests.view']);
        $admin = $this->userAt($otherBranch, [], true, true);

        $request = $this->cake($branch, $factory, $receiver);
        $service = app(BranchDeliveryService::class);
        $shipment = $service->createShipment($request, $factoryActor);
        $service->markLoaded($shipment, $factoryActor, $driver);

        $items = [];
        foreach ($shipment->receivingInvoice->items as $item) {
            $items[$item->id] = [
                'received_quantity' => 2,
                'note' => 'سجل جرد فعلي',
            ];
        }

        $invoice = $service->receive($shipment, $items, $receiver, 'قطعة ناقصة');
        $this->assertTrue($invoice->isConfirmed());

        foreach ([$factoryActor, $branchManager, $driver, $admin] as $user) {
            Notification::assertSentTo(
                $user,
                BranchShipmentActivityNotification::class,
                function ($notification) use ($user, $invoice) {
                    $data = $notification->toDatabase($user);

                    return $data['type'] === 'branch_receiving_invoice_confirmed'
                        && $data['invoice_id'] === $invoice->id
                        && $data['invoice_number'] === $invoice->invoice_number
                        && $data['difference_quantity'] === 1.0
                        && $data['priority'] === 'high'
                        && str_contains($data['url'], '/branch-receiving-invoices/');
                }
            );
        }

        Notification::assertNotSentTo($otherBranchViewer, BranchShipmentActivityNotification::class);
        Notification::assertSentToTimes($receiver, BranchShipmentActivityNotification::class, 1);

        // The only notification to the receiving actor is the earlier loading event.
        $actorAlerts = Notification::sent($receiver, BranchShipmentActivityNotification::class);
        $this->assertSame('branch_shipment_loaded', $actorAlerts->sole()->toDatabase($receiver)['type']);
    }

    #[Test]
    public function sweets_shipment_targets_only_sweets_roles_and_its_own_locations(): void
    {
        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $other = $this->location('branch');
        $creator = $this->userAt($branch, ['showroom_sweets_requests.create']);
        $sweetsReceiver = $this->userAt($branch, ['showroom_sweets_requests.receive']);
        $factoryActor = $this->userAt($factory, ['showroom_sweets_requests.dispatch']);
        $cakeOnlyViewer = $this->userAt($branch, ['showroom_cake_requests.view']);
        $outsider = $this->userAt($other, ['showroom_sweets_requests.view']);
        $driver = $this->userAt($other);

        $request = ShowroomSweetsRequest::query()->create([
            'request_number' => 'SSR-NOTIF-'.Str::upper(Str::random(8)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomSweetsRequestStatus::Ready,
            'created_by' => $creator->id,
        ]);
        $request->items()->create(['product_name_snapshot' => 'حلوى', 'quantity' => 3]);

        $service = app(BranchDeliveryService::class);
        $shipment = $service->createShipment($request, $factoryActor);
        $service->markLoaded($shipment, $factoryActor, $driver);

        Notification::assertSentTo($sweetsReceiver, BranchShipmentActivityNotification::class);
        Notification::assertSentTo($creator, BranchShipmentActivityNotification::class);
        Notification::assertNotSentTo($cakeOnlyViewer, BranchShipmentActivityNotification::class);
        Notification::assertNotSentTo($outsider, BranchShipmentActivityNotification::class);
    }

    private function location(string $type): Location
    {
        return Location::query()->create([
            'name' => 'Notification '.$type,
            'code' => 'NOTIF-'.Str::upper(Str::random(10)),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function userAt(Location $location, array $permissions = [], bool $active = true, bool $admin = false): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->employee->locations()->attach($location->id, ['is_primary' => true]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        if ($admin) {
            $user->assignRole(Role::findOrCreate('Admin', 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function cake(Location $branch, Location $factory, User $creator): ShowroomCakeRequest
    {
        $request = ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-NOTIF-'.Str::upper(Str::random(10)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::Ready,
            'created_by' => $creator->id,
        ]);

        $request->items()->create([
            'cake_type' => 'شوكولاتة',
            'cake_size' => 'large',
            'quantity' => 3,
        ]);

        return $request;
    }
}

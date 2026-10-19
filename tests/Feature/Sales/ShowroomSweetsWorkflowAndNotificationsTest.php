<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\ShowroomSweetsRequestNotification;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowroomSweetsWorkflowAndNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\PreparesBranchShipment;

    #[Test]
    public function new_showroom_sweets_request_notifies_factory_task_owner(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $factoryStarter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $branchViewer = $this->userAt($branch, [
            'showroom_sweets_requests.view',
        ]);

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        ShowroomSweetsRequestNotifier::requestCreated(
            $request->fresh()
        );

        Notification::assertSentTo(
            $factoryStarter,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryStarter)['type']
                === 'showroom_sweets_request_created'
        );

        Notification::assertSentTo(
            $branchViewer,
            ShowroomSweetsRequestNotification::class
        );
    }

    #[Test]
    public function showroom_sweets_happy_path_moves_task_between_factory_and_branch(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $starter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $readyUser = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.ready',
        ]);

        $factoryObserver = $this->userAt($factory, [
            'showroom_sweets_requests.view',
        ]);

        $branchReceiver = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.receive',
        ]);

        $showroomRequest = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        $showUrl = route(
            'showroom-sweets-requests.show',
            $showroomRequest
        );

        $this->actingAs($starter)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'in_progress',
                    'factory_notes' => 'بدأ التجهيز',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        $this->assertSame(
            'in_progress',
            $showroomRequest->status->value
        );
        $this->assertSame(
            $starter->id,
            $showroomRequest->handled_by
        );

        Notification::assertSentTo(
            $readyUser,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($readyUser)['to_status']
                === 'in_progress'
        );

        Notification::assertNotSentTo(
            $starter,
            ShowroomSweetsRequestNotification::class
        );

        $this->actingAs($readyUser)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'ready',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        Notification::assertSentTo(
            $branchReceiver,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchReceiver)['to_status']
                === 'ready'
        );

        $this->actingAs($branchReceiver)->from($showUrl)->patch(
            route('showroom-sweets-requests.status', $showroomRequest),
            ['status' => 'completed']
        )->assertRedirect($showUrl)->assertSessionHas('error');
        $this->assertSame('ready', $showroomRequest->fresh()->status->value);

        $shipment = $this->arrivedShipmentFor($showroomRequest, $readyUser);
        $this->actingAs($branchReceiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $this->fullReceiptItems($shipment),
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('confirmed', $shipment->receivingInvoice->fresh()->status);

        $showroomRequest->refresh();

        $this->assertSame(
            'completed',
            $showroomRequest->status->value
        );
        $this->assertSame(
            $branchReceiver->id,
            $showroomRequest->received_by
        );
        $this->assertNotNull(
            $showroomRequest->received_at
        );
        $this->assertNotNull(
            $showroomRequest->fulfilled_at
        );

        Notification::assertSentTo(
            $factoryObserver,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryObserver)['to_status']
                === 'completed'
        );
    }

    #[Test]
    public function branch_can_cancel_submitted_request_with_cancel_permission_and_factory_is_notified(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $branchCanceller = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.cancel',
        ]);

        $factoryStarter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        $this->actingAs($branchCanceller)
            ->from(route('showroom-sweets-requests.show', $request))
            ->patch(
                route(
                    'showroom-sweets-requests.cancel',
                    $request
                )
            )
            ->assertRedirect(
                route(
                    'showroom-sweets-requests.show',
                    $request
                )
            );

        $request->refresh();

        $this->assertSame(
            'cancelled',
            $request->status->value
        );

        Notification::assertSentTo(
            $factoryStarter,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryStarter)['to_status']
                === 'cancelled'
        );
    }

    #[Test]
    public function factory_user_cannot_execute_branch_receive_stage(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
        ]);

        $factoryReceiver = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.receive',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator,
            'ready'
        );

        $shipment = $this->arrivedShipmentFor($request, $factoryReceiver);
        $this->actingAs($factoryReceiver)->post(route('branch-shipments.receive', $shipment), [
            'items' => $this->fullReceiptItems($shipment),
        ])->assertForbidden();
        $this->assertSame('draft', $shipment->receivingInvoice->fresh()->status);
        $this->assertNull($shipment->receivingInvoice->fresh()->received_by);

        $this->assertSame(
            'ready',
            $request->fresh()->status->value
        );
    }

    #[Test]
    public function sweets_request_cards_show_product_name_above_category_and_quantity(): void
    {
        [$branch, $factory] = $this->locations();
        $viewer = $this->userAt($branch, ['showroom_sweets_requests.view']);
        $dailyRequest = $this->showroomRequest($branch, $factory, $viewer);

        $category = \App\Models\Category::query()->firstOrCreate(
            ['slug' => 'visible-sweets-name'],
            ['name' => 'حلويات شرقية', 'is_active' => true]
        );

        $sku = 'VIS-' . Str::upper(Str::random(8));
        $product = \App\Models\Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Soft Kunafa',
            'name_ar' => 'كنافة ناعمة',
            'sku' => $sku,
            'barcode' => $sku,
            'unit_id' => \App\Models\Unit::query()->where('code', 'piece')->firstOrFail()->id,
            'product_type' => 'standard',
            'is_active' => true,
        ]);

        $dailyRequest->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => 'كنافة ناعمة',
            'quantity' => 25,
            'requested_unit' => 'صدر',
        ]);

        $this->actingAs($viewer)
            ->get(route('showroom-sweets-requests.show', $dailyRequest))
            ->assertOk()
            ->assertSeeInOrder([
                '<h3 class="product-name">',
                'كنافة ناعمة',
                '</h3>',
                'حلويات شرقية',
            ], false);
    }

    #[Test]
    public function showroom_sweets_edit_form_renders_existing_items_and_old_input_without_blade_parse_error(): void
    {
        [$branch, $factory] = $this->locations();
        $editor = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);
        $dailyRequest = $this->showroomRequest($branch, $factory, $editor);

        $category = \App\Models\Category::query()->firstOrCreate(
            ['slug' => 'edit-form-regression'],
            ['name' => 'حلويات', 'is_active' => true]
        );
        $sku = 'EDIT-' . Str::upper(Str::random(8));
        $product = \App\Models\Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Baklava edit regression',
            'sku' => $sku,
            'barcode' => $sku,
            'unit_id' => \App\Models\Unit::query()->where('code', 'piece')->firstOrFail()->id,
            'product_type' => 'standard',
            'is_active' => true,
        ]);
        $dailyRequest->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => 12,
            'reserved_quantity' => 2,
            'requested_unit' => 'صدر',
            'notes' => 'اختبار التعديل',
        ]);

        $url = route('showroom-sweets-requests.edit', $dailyRequest);

        $this->actingAs($editor)->get($url)
            ->assertOk()
            ->assertSee('const oldItems =', false)
            ->assertSee('product_id', false);

        $this->actingAs($editor)
            ->withSession(['_old_input' => [
                'items' => [['product_id' => $product->id, 'quantity' => 9, 'requested_unit' => 'صينية']],
            ]])
            ->get($url)
            ->assertOk()
            ->assertSee('const oldItems =', false);
    }

    private function locations(): array
    {
        $branch = Location::query()->create([
            'name' => 'فرع اختبار الحلويات',
            'code' => 'SWT-BR-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع اختبار الحلويات',
            'code' => 'SWT-FA-' . Str::upper(Str::random(4)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        return [$branch, $factory];
    }

    private function userAt(
        Location $location,
        array $permissions
    ): User {
        $user = User::factory()->create();

        $user->employee->locations()->attach(
            $location->id,
            [
                'is_primary' => true,
            ]
        );

        foreach ($permissions as $permissionName) {
            $permission = Permission::findOrCreate(
                $permissionName,
                'web'
            );

            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user->fresh();
    }

    private function showroomRequest(
        Location $branch,
        Location $factory,
        User $creator,
        string $status = 'submitted'
    ): ShowroomSweetsRequest {
        return ShowroomSweetsRequest::query()->create([
            'request_number' =>
                'SSR-TEST-' . Str::upper(Str::random(8)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => $status,
            'needed_by' => now()->addDays(2)->toDateString(),
            'notes' => 'اختبار دورة طلب الحلويات',
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
    }
}

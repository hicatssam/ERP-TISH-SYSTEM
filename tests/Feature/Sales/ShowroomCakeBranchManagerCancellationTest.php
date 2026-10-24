<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\User;
use App\Notifications\ShowroomCakeRequestNotification;
use App\Support\PermissionUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowroomCakeBranchManagerCancellationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_manager_can_edit_and_cancel_own_branch_request_with_mandatory_reason(): void
    {
        Notification::fake();

        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $manager = $this->branchManager($branch);
        $factoryViewer = $this->factoryViewer($factory);
        $cake = $this->requestFor($branch, $factory, $manager);

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.index'))
            ->assertOk()
            ->assertSee('تعديل')
            ->assertSee('إلغاء مع السبب');

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.edit', $cake))
            ->assertOk();

        $this->actingAs($manager)
            ->put(route('showroom-cake-requests.update', $cake), [
                'factory_location_id' => $factory->id,
                'needed_by' => now()->addDays(4)->toDateString(),
                'items' => [
                    ['cake_type' => 'vanilla', 'cake_size' => 'medium', 'quantity' => 5],
                ],
            ])->assertSessionHasNoErrors();

        $this->assertSame(5, $cake->fresh()->items()->sole()->quantity);

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => '',
            ])->assertSessionHasErrors(['cancellation_reason']);

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'لا نحتاج الكمية المطلوبة بعد تعديل خطة المعرض',
            ])->assertRedirect(route('showroom-cake-requests.show', $cake));

        $cake->refresh();

        $this->assertSame(ShowroomCakeRequestStatus::Cancelled, $cake->status);
        $this->assertSame($manager->id, $cake->cancelled_by);
        $this->assertNotNull($cake->cancelled_at);
        $this->assertSame(
            'لا نحتاج الكمية المطلوبة بعد تعديل خطة المعرض',
            $cake->cancellation_reason
        );

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.show', $cake))
            ->assertOk()
            ->assertSee($cake->cancellation_reason)
            ->assertDontSee('إلغاء الطلب — يجب توضيح السبب');

        Notification::assertSentTo(
            $factoryViewer,
            ShowroomCakeRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryViewer)['to_status'] === 'cancelled'
                && $notification->toDatabase($factoryViewer)['cancellation_reason'] ===
                    $cake->cancellation_reason
        );

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'محاولة إلغاء أخرى',
            ])->assertForbidden();

        // No access to production-only status changes or deleting order history.
        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.status', $cake), [
                'status' => 'ready',
            ])->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('showroom-cake-requests.destroy', $cake))
            ->assertForbidden();
    }

    #[Test]
    public function branch_manager_cannot_edit_or_cancel_another_branch_request(): void
    {
        Notification::fake();

        $ownBranch = $this->location('branch');
        $otherBranch = $this->location('branch');
        $factory = $this->location('factory');
        $manager = $this->branchManager($ownBranch);
        $otherManager = $this->branchManager($otherBranch);
        $cake = $this->requestFor($otherBranch, $factory, $otherManager);

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.show', $cake))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.edit', $cake))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('showroom-cake-requests.update', $cake), [
                'factory_location_id' => $factory->id,
                'items' => [['cake_type' => 'vanilla', 'quantity' => 9]],
            ])->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'لا أملك هذا الفرع',
            ])->assertForbidden();

        $this->assertSame(ShowroomCakeRequestStatus::Pending, $cake->fresh()->status);
    }

    #[Test]
    public function cancellation_is_blocked_after_preparation_or_shipment_even_for_manager(): void
    {
        Notification::fake();

        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $manager = $this->branchManager($branch);
        $cake = $this->requestFor($branch, $factory, $manager);

        $cake->update(['status' => ShowroomCakeRequestStatus::Ready]);

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'تم تجهيز الطلب مسبقًا',
            ])->assertForbidden();

        $cake->update(['status' => ShowroomCakeRequestStatus::InProgress]);
        $cake->branchShipment()->create([
            'from_location_id' => $factory->id,
            'to_location_id' => $branch->id,
            'status' => 'pending_loading',
        ]);

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.index'))
            ->assertOk()
            ->assertDontSee('إلغاء مع السبب');

        $this->actingAs($manager)
            ->get(route('showroom-cake-requests.edit', $cake))
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'الشحنة قيد التحضير',
            ])->assertForbidden();

        $this->assertSame(ShowroomCakeRequestStatus::InProgress, $cake->fresh()->status);
        $this->assertNull($cake->fresh()->cancellation_reason);
    }

    #[Test]
    public function factory_status_cancellation_requires_a_reason_but_does_not_grant_branch_cancel_route(): void
    {
        Notification::fake();

        $branch = $this->location('branch');
        $factory = $this->location('factory');
        $manager = $this->branchManager($branch);
        $factoryUser = $this->factoryViewer($factory);
        $factoryUser->givePermissionTo(Permission::findOrCreate('showroom_cake_requests.cancel_factory', 'web'));

        $cake = $this->requestFor($branch, $factory, $manager);

        $this->actingAs($factoryUser)
            ->patch(route('showroom-cake-requests.cancel', $cake), [
                'cancellation_reason' => 'سبب المصنع',
            ])->assertForbidden();

        $this->actingAs($factoryUser)
            ->patch(route('showroom-cake-requests.status', $cake), [
                'status' => 'cancelled',
            ])->assertSessionHasErrors(['cancellation_reason']);

        $this->actingAs($factoryUser)
            ->patch(route('showroom-cake-requests.status', $cake), [
                'status' => 'cancelled',
                'cancellation_reason' => 'تعذر تنفيذ الكمية في المصنع',
            ])->assertRedirect();

        $this->assertSame(
            'تعذر تنفيذ الكمية في المصنع',
            $cake->fresh()->cancellation_reason
        );

        $this->assertSame(
            'إلغاء طلب كيك المعرض للفرع مع توضيح السبب',
            PermissionUi::permissionLabels()['showroom_cake_requests.cancel']
        );
    }

    private function location(string $type): Location
    {
        return Location::query()->create([
            'name' => 'Test ' . $type . ' ' . Str::random(6),
            'code' => 'SC-' . Str::upper(Str::random(10)),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function branchManager(Location $branch): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        $role = Role::findOrCreate('Branch Manager', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('showroom_cake_requests.view', 'web'),
            Permission::findOrCreate('showroom_cake_requests.create', 'web'),
            Permission::findOrCreate('showroom_cake_requests.cancel', 'web'),
        ]);
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function factoryViewer(Location $factory): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($factory->id, ['is_primary' => true]);
        $user->givePermissionTo(Permission::findOrCreate('showroom_cake_requests.view', 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function requestFor(Location $branch, Location $factory, User $creator): ShowroomCakeRequest
    {
        $cake = ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-CANCEL-' . Str::upper(Str::random(10)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::Pending,
            'created_by' => $creator->id,
        ]);
        $cake->items()->create(['cake_type' => 'chocolate', 'cake_size' => 'large', 'quantity' => 3]);

        return $cake;
    }
}

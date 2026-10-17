<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchOrderAdminBulkDeletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_soft_delete_selected_cancelled_cake_requests(): void
    {
        [$admin, $branch, $factory] = $this->setupRolesAndLocations();
        $first = $this->cake($branch, $factory, $admin, 'cancelled');
        $second = $this->cake($branch, $factory, $admin, 'cancelled');

        $this->actingAs($admin)->post(route('showroom-branch-requests.bulk-delete', ['type' => 'cake']), [
            'mode' => 'selected',
            'ids' => [$first->id, $second->id],
        ])->assertRedirect(route('showroom-cake-requests.index'))->assertSessionHasNoErrors();

        $this->assertSame(0, ShowroomCakeRequest::query()->count());
        $this->assertSame(2, ShowroomCakeRequest::withTrashed()->count());
        $this->assertNotNull(ShowroomCakeRequest::withTrashed()->findOrFail($first->id)->deleted_at);
        $this->actingAs($admin)->get(route('showroom-cake-requests.index'))
            ->assertOk()->assertDontSee($first->request_number);
    }

    #[Test]
    public function admin_can_delete_all_eligible_sweets_after_typed_confirmation(): void
    {
        [$admin, $branch, $factory] = $this->setupRolesAndLocations();
        $cancelled = $this->sweets($branch, $factory, $admin, 'cancelled');
        $completed = $this->sweets($branch, $factory, $admin, 'completed');

        $url = route('showroom-branch-requests.bulk-delete', ['type' => 'sweets']);

        $this->actingAs($admin)->post($url, [
            'mode' => 'all_eligible', 'confirmation' => 'wrong phrase',
        ])->assertSessionHasErrors('confirmation');
        $this->assertSame(2, ShowroomSweetsRequest::query()->count());

        $this->actingAs($admin)->post($url, [
            'mode' => 'all_eligible', 'confirmation' => 'حذف الطلبات الملغاة',
        ])->assertSessionHasNoErrors();

        $this->assertSoftDeleted('showroom_sweets_requests', ['id' => $cancelled->id]);
        $this->assertDatabaseHas('showroom_sweets_requests', [
            'id' => $completed->id, 'deleted_at' => null,
        ]);
    }

    #[Test]
    public function mixed_selected_batch_is_atomic_when_one_request_is_completed(): void
    {
        [$admin, $branch, $factory] = $this->setupRolesAndLocations();
        $cancelled = $this->cake($branch, $factory, $admin, 'cancelled');
        $completed = $this->cake($branch, $factory, $admin, 'completed');

        $this->actingAs($admin)->post(route('showroom-branch-requests.bulk-delete', ['type' => 'cake']), [
            'mode' => 'selected', 'ids' => [$cancelled->id, $completed->id],
        ])->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('showroom_cake_requests', [
            'id' => $cancelled->id, 'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('showroom_cake_requests', [
            'id' => $completed->id, 'deleted_at' => null,
        ]);
    }

    #[Test]
    public function shipment_and_raw_material_links_block_soft_deletion(): void
    {
        [$admin, $branch, $factory] = $this->setupRolesAndLocations();
        $shipped = $this->cake($branch, $factory, $admin, 'cancelled');
        $shipped->branchShipment()->create([
            'from_location_id' => $factory->id,
            'to_location_id' => $branch->id,
            'status' => 'pending_loading',
        ]);
        $materials = $this->sweets($branch, $factory, $admin, 'cancelled');
        StockRequest::query()->create([
            'request_number' => 'SR-TEST-'.Str::upper(Str::random(10)),
            'branch_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'showroom_sweets_request_id' => $materials->id,
            'status' => 'pending_factory_review',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('showroom-cake-requests.destroy', $shipped))
            ->assertSessionHasErrors('ids');
        $this->actingAs($admin)->delete(route('showroom-sweets-requests.destroy', $materials))
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('showroom_cake_requests', [
            'id' => $shipped->id, 'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('showroom_sweets_requests', [
            'id' => $materials->id, 'deleted_at' => null,
        ]);
    }

    #[Test]
    public function non_admin_with_delete_permissions_cannot_bulk_or_individually_delete(): void
    {
        [$admin, $branch, $factory] = $this->setupRolesAndLocations();
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $role = Role::findOrCreate('Branch Manager', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('showroom_cake_requests.delete', 'web'),
            Permission::findOrCreate('showroom_sweets_requests.delete', 'web'),
            Permission::findOrCreate('showroom_cake_requests.view', 'web'),
            Permission::findOrCreate('showroom_sweets_requests.view', 'web'),
        ]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cake = $this->cake($branch, $factory, $admin, 'cancelled');
        $sweets = $this->sweets($branch, $factory, $admin, 'cancelled');

        $this->actingAs($user)->post(route('showroom-branch-requests.bulk-delete', ['type' => 'cake']), [
            'mode' => 'selected', 'ids' => [$cake->id],
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('showroom-cake-requests.destroy', $cake))->assertForbidden();
        $this->actingAs($user)->delete(route('showroom-sweets-requests.destroy', $sweets))->assertForbidden();

        $this->assertSame(1, ShowroomCakeRequest::query()->count());
        $this->assertSame(1, ShowroomSweetsRequest::query()->count());
    }

    /** @return array{User, Location, Location} */
    private function setupRolesAndLocations(): array
    {
        $branch = Location::query()->create([
            'name' => 'Bulk test branch',
            'code' => 'BR-'.Str::upper(Str::random(8)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'Bulk test factory',
            'code' => 'FC-'.Str::upper(Str::random(8)),
            'type' => 'factory', 'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $admin->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $role = Role::findOrCreate('Admin', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('showroom_cake_requests.view', 'web'),
            Permission::findOrCreate('showroom_cake_requests.delete', 'web'),
            Permission::findOrCreate('showroom_sweets_requests.view', 'web'),
            Permission::findOrCreate('showroom_sweets_requests.delete', 'web'),
        ]);
        $admin->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return [$admin->fresh(), $branch, $factory];
    }

    private function cake(Location $branch, Location $factory, User $user, string $status): ShowroomCakeRequest
    {
        return ShowroomCakeRequest::query()->create([
            'request_number' => 'SCR-DEL-'.Str::upper(Str::random(9)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomCakeRequestStatus::from($status),
            'created_by' => $user->id,
        ]);
    }

    private function sweets(Location $branch, Location $factory, User $user, string $status): ShowroomSweetsRequest
    {
        return ShowroomSweetsRequest::query()->create([
            'request_number' => 'SSR-DEL-'.Str::upper(Str::random(9)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => ShowroomSweetsRequestStatus::from($status),
            'created_by' => $user->id,
        ]);
    }
}

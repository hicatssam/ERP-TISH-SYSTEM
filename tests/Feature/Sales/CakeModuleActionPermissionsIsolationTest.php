<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\User;
use App\Support\PermissionUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CakeModuleActionPermissionsIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cake_action_names_are_registered_and_labeled_in_separate_groups(): void
    {
        $special = ['view', 'view_all', 'create', 'edit', 'delete', 'archive', 'restore', 'comment', 'attachment', 'cancel', 'complete', 'review', 'accept', 'reject', 'request_modification', 'schedule', 'prepare', 'decorate', 'quality_check', 'dispatch', 'receive'];
        $branch = ['view', 'view_all', 'create', 'edit', 'reservations', 'update_status', 'accept', 'ready', 'cancel', 'cancel_factory', 'delete'];

        foreach (['cake_orders' => $special, 'showroom_cake_requests' => $branch] as $group => $actions) {
            foreach ($actions as $action) {
                $permission = $group.'.'.$action;
                $this->assertTrue(Permission::findOrCreate($permission, 'web')->exists);
                $this->assertArrayHasKey($permission, PermissionUi::permissionLabels());
            }
        }
        $this->assertNotSame(
            PermissionUi::groupLabels()['cake_orders'],
            PermissionUi::groupLabels()['showroom_cake_requests']
        );
    }

    #[Test]
    public function branch_cake_actions_do_not_grant_special_cake_actions_and_vice_versa(): void
    {
        $branch = Location::query()->create([
            'name' => 'Cake actions branch', 'code' => 'CAK-ISOL-1',
            'type' => 'branch', 'is_active' => true,
        ]);
        $branchUser = User::factory()->create();
        $branchUser->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['showroom_cake_requests.view', 'showroom_cake_requests.create'] as $name) Permission::findOrCreate($name, 'web');
        $branchUser->givePermissionTo(['showroom_cake_requests.view', 'showroom_cake_requests.create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse($branchUser->can('cake_orders.view'));
        $this->assertFalse($branchUser->can('cake_orders.create'));
        $this->assertFalse($branchUser->can('showroom_cake_requests.edit'));
        $this->assertFalse($branchUser->can('showroom_cake_requests.reservations'));

        $this->actingAs($branchUser)->get(route('showroom-cake-requests.create'))->assertOk();
        $this->actingAs($branchUser)->get(route('cake-orders.create'))->assertForbidden();

        $specialUser = User::factory()->create();
        $specialUser->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['cake_orders.view', 'cake_orders.create'] as $name) Permission::findOrCreate($name, 'web');
        $specialUser->givePermissionTo(['cake_orders.view', 'cake_orders.create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($specialUser)->get(route('cake-orders.create'))->assertOk();
        $this->actingAs($specialUser)->get(route('showroom-cake-requests.create'))->assertForbidden();
    }

    #[Test]
    public function new_permissions_backfill_only_from_the_corresponding_legacy_group(): void
    {
        $role = \Spatie\Permission\Models\Role::findOrCreate('Isolated Cake Legacy', 'web');
        foreach (['showroom_cake_requests.create', 'showroom_cake_requests.update_status'] as $name) Permission::findOrCreate($name, 'web');
        $role->givePermissionTo(['showroom_cake_requests.create', 'showroom_cake_requests.update_status']);
        $migration = require database_path('migrations/2026_10_24_160000_separate_special_and_branch_cake_action_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('showroom_cake_requests.edit'));
        $this->assertTrue($role->fresh()->hasPermissionTo('showroom_cake_requests.reservations'));
        $this->assertTrue($role->fresh()->hasPermissionTo('showroom_cake_requests.accept'));
        $this->assertTrue($role->fresh()->hasPermissionTo('showroom_cake_requests.ready'));
        $this->assertFalse($role->fresh()->getPermissionNames()->contains('cake_orders.create'));
        $this->assertFalse($role->fresh()->getPermissionNames()->contains('cake_orders.attachment'));
    }
}

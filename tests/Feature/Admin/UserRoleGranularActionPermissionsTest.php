<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Location;
use App\Support\PermissionUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserRoleGranularActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $actor = User::factory()->create();
        $location = Location::query()->create([
            'name' => 'Permission test branch '.$actor->id,
            'code' => 'PERM-'.$actor->id,
            'type' => 'branch', 'is_active' => true,
        ]);
        $actor->employee->locations()->attach($location->id, ['is_primary' => true]);
        $role = Role::findOrCreate('General Manager', 'web');
        $role->givePermissionTo($permissions);
        $actor->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor;
    }

    #[Test]
    public function each_sensitive_account_and_role_action_requires_its_own_permission(): void
    {
        $routes = [
            'users.index' => 'users.view',
            'employees.create-user' => 'users.create',
            'users.edit' => 'users.update',
            'users.update' => 'users.update',
            'users.toggle' => 'users.toggle',
            'users.reset-password' => 'users.reset_password',
            'users.assign-role' => 'users.assign_role',
            'users.destroy' => 'users.delete',
            'roles.index' => 'roles.view',
            'roles.show' => 'roles.view',
            'roles.audit' => 'roles.audit',
            'roles.create' => 'roles.create',
            'roles.store' => 'roles.create',
            'roles.edit' => 'roles.update',
            'roles.update' => 'roles.update',
            'roles.destroy' => 'roles.delete',
        ];

        foreach ($routes as $name => $permission) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, 'Missing route: '.$name);
            $this->assertContains('can:'.$permission, $route->gatherMiddleware(), $name);
            $this->assertArrayHasKey($permission, PermissionUi::permissionLabels());
        }
    }

    #[Test]
    public function view_permissions_do_not_grant_sensitive_controls_or_endpoint_access(): void
    {
        $actor = $this->manager(['users.view', 'roles.view']);
        $target = User::factory()->create();
        $role = Role::findOrCreate('Test cashier role', 'web');

        $this->actingAs($actor)->get(route('users.index'))->assertOk()
            ->assertDontSee('ضبط كلمة المرور')
            ->assertDontSee('تغيير الدور');
        $this->get(route('roles.index'))->assertOk()
            ->assertDontSee('إضافة دور')
            ->assertDontSee('تدقيق الصلاحيات')
            ->assertDontSee('حذف دور');

        $this->get(route('users.edit', $target))->assertForbidden();
        $this->post(route('users.toggle', $target))->assertForbidden();
        $this->post(route('users.reset-password', $target))->assertForbidden();
        $this->post(route('users.assign-role', $target), ['role' => $role->name])->assertForbidden();
        $this->get(route('roles.edit', $role))->assertForbidden();
        $this->get(route('roles.audit'))->assertForbidden();
        $this->delete(route('roles.destroy', $role))->assertForbidden();
    }

    #[Test]
    public function password_reset_can_be_granted_without_account_edit_or_toggle(): void
    {
        $actor = $this->manager(['users.view', 'users.reset_password']);
        $target = User::factory()->create();

        $this->actingAs($actor)->get(route('users.index'))->assertOk()
            ->assertSee('ضبط كلمة المرور')
            ->assertDontSee('تغيير الدور');
        $this->get(route('users.edit', $target))->assertForbidden();
        $this->post(route('users.toggle', $target))->assertForbidden();
        $this->post(route('users.assign-role', $target), ['role' => 'Admin'])->assertForbidden();
    }

    #[Test]
    public function delegated_user_operator_cannot_disable_or_reset_an_admin_account(): void
    {
        $actor = $this->manager(['users.view', 'users.update', 'users.toggle', 'users.reset_password']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $passwordBefore = $admin->password;

        $this->actingAs($actor)->post(route('users.toggle', $admin))->assertForbidden();
        $this->post(route('users.reset-password', $admin))->assertForbidden();
        $this->get(route('users.edit', $admin))->assertForbidden();

        $this->assertSame($passwordBefore, $admin->fresh()->password);
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    #[Test]
    public function non_admin_role_editor_cannot_grant_permissions_they_do_not_have(): void
    {
        $actor = $this->manager(['roles.view', 'roles.create', 'roles.update']);
        $target = Role::findOrCreate('Limited cashier permissions', 'web');
        Permission::findOrCreate('payments.refund', 'web');

        $this->actingAs($actor)->post(route('roles.store'), [
            'name' => 'Escalated cash supervisor',
            'permissions' => ['payments.refund'],
        ])->assertForbidden();

        $this->put(route('roles.update', $target), [
            'name' => $target->name,
            'permissions' => ['payments.refund'],
        ])->assertForbidden();

        $this->assertFalse(Role::query()->where('name', 'Escalated cash supervisor')->exists());
        $this->assertFalse($target->fresh()->getPermissionNames()->contains('payments.refund'));
    }

    #[Test]
    public function legacy_roles_backfill_into_separate_action_grants_without_role_escalation(): void
    {
        $first = Role::findOrCreate('Account Manager Legacy', 'web');
        $first->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        $second = Role::findOrCreate('Security Supervisor Legacy', 'web');
        $second->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));
        $both = Role::findOrCreate('Operations Superuser Legacy', 'web');
        $both->givePermissionTo(['users.manage', 'roles.manage']);

        $migration = require database_path('migrations/2026_10_27_180000_separate_users_and_roles_action_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertTrue($first->fresh()->hasPermissionTo('users.reset_password', 'web'));
        $this->assertFalse($first->fresh()->getPermissionNames()->contains('users.assign_role'));
        $this->assertFalse($first->fresh()->getPermissionNames()->contains('roles.delete'));
        $this->assertTrue($second->fresh()->hasPermissionTo('roles.audit', 'web'));
        $this->assertFalse($second->fresh()->getPermissionNames()->contains('users.toggle'));
        $this->assertTrue($both->fresh()->hasPermissionTo('users.assign_role', 'web'));
    }
}

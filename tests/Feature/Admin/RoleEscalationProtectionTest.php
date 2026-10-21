<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleEscalationProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeNonAdminManager(): User
    {
        $location = Location::create([
            'name' => 'Test branch', 'code' => 'ROLE-SEC', 'type' => 'branch', 'is_active' => true,
        ]);
        $employee = Employee::create([
            'employee_number' => 'EMP-ROLE-SEC',
            'full_name' => 'Test manager',
            'employment_status' => 'active',
        ]);
        $employee->locations()->attach($location->id, ['is_primary' => true]);
        $actor = User::factory()->create(['employee_id' => $employee->id]);
        $role = Role::findOrCreate('General Manager', 'web');
        $role->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        $actor->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $actor;
    }

    #[Test]
    public function general_manager_cannot_assign_admin_role_to_another_user(): void
    {
        $actor = $this->makeNonAdminManager();
        $target = User::factory()->create();
        Role::findOrCreate('Admin', 'web');

        $this->actingAs($actor)->post(route('users.assign-role', $target), [
            'role' => 'Admin',
        ])->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('Admin'));
    }

    #[Test]
    public function non_admin_cannot_remove_admin_role_from_an_admin_account(): void
    {
        $actor = $this->makeNonAdminManager();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        Role::findOrCreate('Cashier', 'web');

        $this->actingAs($actor)->post(route('users.assign-role', $admin), [
            'role' => 'Cashier',
        ])->assertForbidden();
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }
}

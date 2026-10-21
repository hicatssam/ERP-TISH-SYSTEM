<?php

namespace Tests\Feature\Admin;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LocationEmployeeStatusActionsTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'web');
        foreach (['locations.manage', 'employees.manage', 'employees.view_all', 'employees.update'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $user;
    }

    #[Test]
    public function location_toggle_uses_post_and_does_not_double_toggle_stale_requests(): void
    {
        $user = $this->administrator();
        $location = Location::create(['name' => 'فرع اختبار', 'code' => 'BR-LACT', 'type' => 'branch', 'is_active' => true]);

        $this->actingAs($user)->get(route('locations.index'))->assertOk()
            ->assertSee('تأكيد تعطيل الموقع')->assertSee('expected_active');

        $this->post(route('locations.toggle', $location), ['expected_active' => '1'])->assertRedirect();
        $this->assertFalse((bool) $location->fresh()->is_active);

        $this->post(route('locations.toggle', $location), ['expected_active' => '1'])
            ->assertSessionHas('error');
        $this->assertFalse((bool) $location->fresh()->is_active);
    }

    #[Test]
    public function terminated_employee_cannot_be_reactivated_using_quick_action(): void
    {
        $user = $this->administrator();
        $employee = Employee::create([
            'employee_number' => 'EMP-ST-001',
            'full_name' => 'موظف منتهي الخدمة',
            'employment_status' => EmploymentStatus::Terminated,
        ]);
        $this->actingAs($user)->post(route('employees.toggle', $employee))
            ->assertSessionHas('error');
        $this->assertSame(EmploymentStatus::Terminated, $employee->fresh()->employment_status);
    }
}

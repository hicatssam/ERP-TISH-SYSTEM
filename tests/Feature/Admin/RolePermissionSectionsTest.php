<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermissionUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSectionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_documented_permission_has_an_arabic_module_and_an_action_section(): void
    {
        $labels = PermissionUi::permissionLabels();
        $groups = PermissionUi::groupLabels();

        $this->assertGreaterThanOrEqual(290, count($labels));

        foreach ($labels as $code => $label) {
            $group = explode('.', $code)[0];
            $this->assertArrayHasKey($group, $groups, "Module label missing for: {$code}");
            $this->assertNotSame('', trim($label));
            $this->assertNotSame('', trim(PermissionUi::permissionSection($code)));
        }

        $this->assertNotSame(
            PermissionUi::permissionSection('cake_orders.archive'),
            PermissionUi::permissionSection('showroom_cake_requests.reservations')
        );
        $this->assertSame('مراحل تصنيع وتوصيل الحلويات', PermissionUi::permissionSection('showroom_sweets_requests.dispatch'));
        $this->assertSame('الإجازات', PermissionUi::permissionSection('attendance.leaves.approve'));
        $this->assertSame('الفترات المالية', PermissionUi::permissionSection('financial.periods.override_close'));
        $this->assertSame('المحادثات المباشرة', PermissionUi::permissionSection('chat.direct.start_all'));
        $this->assertSame('صلاحيات قديمة — للمراجعة', PermissionUi::permissionSection('cake_orders.manage'));
        $this->assertTrue(PermissionUi::isBroadPermission('chat.view_all_branches'));
        $this->assertFalse(PermissionUi::isBroadPermission('cake_orders.view'));
    }

    #[Test]
    public function role_create_edit_and_view_render_every_permission_once_without_changing_stored_grants(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));
        $admin->assignRole($adminRole);

        $role = Role::findOrCreate('Cake Operations Supervisor', 'web');
        $special = Permission::findOrCreate('cake_orders.archive', 'web');
        $branch = Permission::findOrCreate('showroom_cake_requests.reservations', 'web');
        $other = Permission::findOrCreate('payroll.pay', 'web');
        $legacy = Permission::findOrCreate('cake_orders.manage', 'web');
        $role->givePermissionTo([$special, $legacy]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $before = $role->fresh()->permissions->pluck('name')->sort()->values()->all();

        foreach (['roles.create', 'roles.edit'] as $name) {
            $url = $name === 'roles.create' ? route($name) : route($name, $role);
            $response = $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('ابحث باسم الإجراء أو كود الصلاحية')
                ->assertSee('طلبات الكيك الخاصة')
                ->assertSee('طلبات كيك الفروع')
                ->assertSee('الرواتب والأجور')
                ->assertSee('صلاحية قديمة')
                ->assertSee('name="permissions[]"', false);

            foreach ([$special, $branch, $other, $legacy] as $permission) {
                $this->assertSame(
                    1,
                    substr_count($response->getContent(), 'value="'.$permission->name.'"'),
                    'Duplicate or omitted checkbox: '.$permission->name
                );
            }
        }

        $this->actingAs($admin)->get(route('roles.show', $role))
            ->assertOk()
            ->assertSee('أرشفة الطلب الخاص ومرفقاته')
            ->assertSee('طلبات كيك الفروع والكمية المحجوزة')
            ->assertSee('cake_orders.archive')
            ->assertSee('showroom_cake_requests.reservations');

        $this->actingAs($admin)->get(route('roles.audit'))
            ->assertOk()
            ->assertSee('التقرير يعرض الأدوار وصلاحياتها')
            ->assertSee('أرشفة الطلب الخاص ومرفقاته');

        $this->assertSame($before, $role->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    #[Test]
    public function editing_one_roles_permissions_does_not_grant_the_other_cake_module(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));
        $admin->assignRole($adminRole);
        $role = Role::findOrCreate('Bakery Supervisor', 'web');
        Permission::findOrCreate('cake_orders.view', 'web');
        Permission::findOrCreate('showroom_cake_requests.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => $role->name,
            'permissions' => ['cake_orders.view'],
        ])->assertRedirect(route('roles.index'));

        $names = $role->fresh()->permissions->pluck('name')->all();
        $this->assertSame(['cake_orders.view'], $names);
        $this->assertNotContains('showroom_cake_requests.view', $names);
    }
}

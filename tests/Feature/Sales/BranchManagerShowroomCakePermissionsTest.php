<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\User;
use App\Support\PermissionUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchManagerShowroomCakePermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_manager_gets_showroom_cake_access_without_factory_status_or_special_cake_permissions(): void
    {
        $branch = Location::query()->create([
            'name' => 'Showroom cakes branch',
            'code' => 'SCBR-' . Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        $role = Role::findOrCreate('Branch Manager', 'web');
        $existing = Permission::findOrCreate('products.view', 'web');
        $role->givePermissionTo($existing);
        $user->assignRole($role);

        $migration = require database_path(
            'migrations/2026_10_14_130000_grant_branch_manager_showroom_cake_permissions.php'
        );
        $migration->up();
        $migration->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $this->assertTrue($user->can('showroom_cake_requests.view'));
        $this->assertTrue($user->can('showroom_cake_requests.create'));
        $this->assertTrue($user->can('products.view'));
        $this->assertFalse($user->can('showroom_cake_requests.update_status'));
        $this->assertFalse($user->can('showroom_cake_requests.view_all'));
        $this->assertFalse($user->can('cake_orders.create'));

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('cake-orders.create'))
            ->assertForbidden();
    }

    #[Test]
    public function role_editor_names_showroom_cake_permissions_separately(): void
    {
        $this->assertSame(
            'طلبات كيك الفروع',
            PermissionUi::groupLabels()['showroom_cake_requests']
        );
        $this->assertSame(
            'إنشاء طلب كيك للفرع',
            PermissionUi::permissionLabels()['showroom_cake_requests.create']
        );
        $this->assertSame(
            'طلبات الكيك الخاصة',
            PermissionUi::groupLabels()['cake_orders']
        );
    }
}

<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchManagerDailyWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_manager_can_access_four_workflows_without_gaining_accountant_or_factory_approval(): void
    {
        $branch = Location::query()->create([
            'name' => 'Daily workflow branch',
            'code' => 'DW-' . Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        $role = Role::findOrCreate('Branch Manager', 'web');
        $role->givePermissionTo(Permission::findOrCreate('products.view', 'web'));
        $user->assignRole($role);

        $migration = require database_path(
            'migrations/2026_10_27_120000_grant_branch_manager_daily_workflow_permissions.php'
        );
        $migration->up();
        $migration->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        foreach ([
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
            'cake_orders.view',
            'cake_orders.create',
            'showroom_cake_requests.view',
            'showroom_cake_requests.create',
            'showroom_daily_reports.view',
            'showroom_daily_reports.manage_branch',
            'products.view',
        ] as $permission) {
            $this->assertTrue($user->can($permission), $permission);
        }

        foreach ([
            'showroom_sweets_requests.view_all',
            'showroom_sweets_requests.delete',
            'cake_orders.view_all',
            'cake_orders.delete',
            'showroom_cake_requests.view_all',
            'showroom_cake_requests.accept',
            'showroom_cake_requests.ready',
            'showroom_daily_reports.review',
        ] as $permission) {
            $this->assertFalse($role->fresh()->getPermissionNames()->contains($permission), $permission);
        }

        foreach ([
            'showroom-sweets-requests.create',
            'cake-orders.create',
            'showroom-cake-requests.create',
            'showroom-daily-reports.index',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    #[Test]
    public function dashboard_shortcuts_match_branch_manager_operations_and_require_permission_checks(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard/partials/quick-access.blade.php'));

        foreach ([
            'showroom-sweets-requests.create',
            'cake-orders.create',
            'showroom-cake-requests.create',
            'showroom-daily-reports.index',
        ] as $routeName) {
            $this->assertStringContainsString("'route' => '".$routeName."'", $view);
        }

        $this->assertStringContainsString("'permission' => 'showroom_daily_reports.manage_branch'", $view);
        $this->assertStringContainsString('ترحيل التقرير اليومي للمحاسبة', $view);
        $this->assertStringContainsString("&& \$dashboardUser->can(\$shortcut['permission'])", $view);
    }
}

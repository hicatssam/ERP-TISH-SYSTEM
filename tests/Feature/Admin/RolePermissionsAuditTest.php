<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermissionUi;
use App\Support\Authorization\PermissionLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionsAuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function source_permission_labels_cover_the_seed_matrix_and_protected_routes(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertNotFalse($source);
        $this->assertSame(
            1,
            preg_match('/\\$featurePermissions = collect\\(\\[([\\s\\S]*?)\\]\\);/', $source, $matches)
        );

        preg_match_all("/'([a-z][a-z0-9_.-]+)'/", $matches[1], $names);
        $labels = PermissionUi::permissionLabels();

        foreach (array_unique($names[1]) as $name) {
            $this->assertArrayHasKey($name, $labels, "Missing Arabic label: {$name}");
        }

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                    continue;
                }

                $permission = trim(explode(',', substr($middleware, 4))[0]);
                if (! str_contains($permission, '.')) {
                    continue;
                }

                $this->assertArrayHasKey($permission, $labels, "Missing route label: {$permission}");
            }
        }

        $this->assertSame(
            PermissionUi::groupLabels(),
            PermissionLabels::groupLabels()
        );
        $this->assertSame(
            PermissionUi::permissionLabels(),
            PermissionLabels::permissionLabels()
        );
        $this->assertSame(
            PermissionUi::roleLabels(),
            PermissionLabels::roleLabels()
        );
    }

    #[Test]
    public function audit_reports_actual_role_grants_and_unknown_codes_without_mutations(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::findOrCreate('Admin', 'web');
        $roleManage = Permission::findOrCreate('roles.manage', 'web');
        $adminRole->givePermissionTo($roleManage);
        $admin->assignRole($adminRole);

        $testRole = Role::findOrCreate('Branch Manager', 'web');
        $unknown = Permission::findOrCreate('custom_area.future_power', 'web');
        $global = Permission::findOrCreate('chat.view_all_branches', 'web');
        $testRole->givePermissionTo([$unknown, $global]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin)
            ->get(route('roles.audit'))
            ->assertOk()
            ->assertSee('Branch Manager')
            ->assertSee('صلاحية غير موثقة: custom_area.future_power')
            ->assertSee('chat.view_all_branches');

        $this->assertEqualsCanonicalizing(
            ['custom_area.future_power', 'chat.view_all_branches'],
            $testRole->fresh()->permissions->pluck('name')->all()
        );
    }

    #[Test]
    public function super_admin_role_cannot_be_renamed_or_deleted(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::findOrCreate('Admin', 'web');
        $roleManage = Permission::findOrCreate('roles.manage', 'web');
        $adminRole->givePermissionTo($roleManage);
        $admin->assignRole($adminRole);

        $super = Role::findOrCreate('super-admin', 'web');
        $this->actingAs($admin)->delete(route('roles.destroy', $super))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['id' => $super->id, 'name' => 'super-admin']);

        $this->actingAs($admin)->put(route('roles.update', $super), [
            'name' => 'Renamed Administrator',
            'permissions' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('roles', ['id' => $super->id, 'name' => 'super-admin']);
    }
}

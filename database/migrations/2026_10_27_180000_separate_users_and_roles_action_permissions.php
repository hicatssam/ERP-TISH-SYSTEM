<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Convert the legacy users.manage / roles.manage grants into independently
 * removable per-action rights. Preserve original grants as-is for rollback
 * compatibility; permission checks on these routes use only new action names.
 */
return new class extends Migration
{
    public function up(): void
    {
        $usersActions = [
            'users.view', 'users.create', 'users.update', 'users.toggle',
            'users.reset_password', 'users.delete',
        ];
        $rolesActions = [
            'roles.view', 'roles.audit', 'roles.create', 'roles.update', 'roles.delete',
        ];

        foreach ([...$usersActions, ...$rolesActions, 'users.assign_role'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $hasUsers = Permission::query()
            ->where('name', 'users.manage')->where('guard_name', 'web')->exists();
        $hasRoles = Permission::query()
            ->where('name', 'roles.manage')->where('guard_name', 'web')->exists();

        Role::query()->where('guard_name', 'web')->with('permissions')->chunkById(100, function ($roles) use (
            $usersActions, $rolesActions, $hasUsers, $hasRoles
        ): void {
            foreach ($roles as $role) {
                $grantUsers = $hasUsers && $role->hasPermissionTo('users.manage', 'web');
                $grantRoles = $hasRoles && $role->hasPermissionTo('roles.manage', 'web');

                if ($grantUsers) {
                    $role->givePermissionTo($usersActions);
                }
                if ($grantRoles) {
                    $role->givePermissionTo($rolesActions);
                }
                if ($grantUsers && $grantRoles) {
                    $role->givePermissionTo('users.assign_role');
                }
            }
        });

        // Also preserve exceptional direct user grants, not just role grants.
        User::query()->with('permissions', 'roles')->chunkById(100, function ($users) use (
            $usersActions, $rolesActions, $hasUsers, $hasRoles
        ): void {
            foreach ($users as $user) {
                $direct = $user->permissions->pluck('name');
                $usersDirect = $hasUsers && $direct->contains('users.manage');
                $rolesDirect = $hasRoles && $direct->contains('roles.manage');

                if ($usersDirect) {
                    $user->givePermissionTo($usersActions);
                }
                if ($rolesDirect) {
                    $user->givePermissionTo($rolesActions);
                }
                // A direct grant paired with a role grant must retain the
                // ability to assign roles, but never infer it from one side.
                $canManageUsers = $hasUsers && ($usersDirect || $user->hasPermissionTo('users.manage', 'web'));
                $canManageRoles = $hasRoles && ($rolesDirect || $user->hasPermissionTo('roles.manage', 'web'));
                if ($canManageUsers && $canManageRoles && ($usersDirect || $rolesDirect)) {
                    $user->givePermissionTo('users.assign_role');
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permissions and grants on rollback: removing them could
        // otherwise lock administrators out of production.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

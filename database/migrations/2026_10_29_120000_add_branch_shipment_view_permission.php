<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('branch_shipments.view', 'web');

        // Existing branch personnel receive read-only access to their branch's
        // inbound trips. Query and detail authorizations still enforce branch
        // location boundaries; no dispatcher management permission is granted.
        foreach (['Branch Manager', 'Branch Employee'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role !== null && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not silently revoke existing role permissions during rollback.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

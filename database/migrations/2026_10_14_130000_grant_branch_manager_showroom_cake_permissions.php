<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'showroom_cake_requests.view',
            'showroom_cake_requests.create',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $branchManager = Role::query()
            ->where('name', 'Branch Manager')
            ->where('guard_name', 'web')
            ->first();

        if ($branchManager) {
            // Add only showroom-cake access. Do not reset custom role settings
            // or grant factory-only status transitions or special-cake access.
            $branchManager->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Existing installations may have assigned either permission manually.
        // Preserve those grants on rollback instead of revoking user access.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Operational access only, all enforced by the existing branch-scoped
        // controllers. No delete, all-branches, factory or accounting approvals.
        $permissions = [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
            'cake_orders.view',
            'cake_orders.create',
            'showroom_cake_requests.view',
            'showroom_cake_requests.create',
            'showroom_daily_reports.view',
            'showroom_daily_reports.manage_branch',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $branchManager = Role::query()
            ->where('name', 'Branch Manager')
            ->where('guard_name', 'web')
            ->first();

        if ($branchManager !== null) {
            $branchManager->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not silently revoke grants a role administrator may have customized.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

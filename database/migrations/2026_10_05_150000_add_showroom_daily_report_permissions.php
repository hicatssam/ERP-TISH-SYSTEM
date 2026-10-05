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
            'showroom_daily_reports.view',
            'showroom_daily_reports.manage_branch',
            'showroom_daily_reports.review',
            'showroom_daily_reports.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (['Admin', 'super-admin'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        $accountant = Role::query()
            ->where('name', 'Accountant')
            ->where('guard_name', 'web')
            ->first();

        if ($accountant) {
            $accountant->givePermissionTo([
                'showroom_daily_reports.view',
                'showroom_daily_reports.review',
                'showroom_daily_reports.export',
            ]);
        }

        $branchManager = Role::query()
            ->where('name', 'Branch Manager')
            ->where('guard_name', 'web')
            ->first();

        if ($branchManager) {
            $branchManager->givePermissionTo([
                'showroom_daily_reports.view',
                'showroom_daily_reports.manage_branch',
                'showroom_daily_reports.export',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'showroom_daily_reports.view',
            'showroom_daily_reports.manage_branch',
            'showroom_daily_reports.review',
            'showroom_daily_reports.export',
        ] as $permissionName) {
            Permission::query()
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

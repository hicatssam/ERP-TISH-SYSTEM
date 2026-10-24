<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill from existing permissions in the SAME module only.
        $map = [
            'cake_orders.archive' => 'cake_orders.delete',
            'cake_orders.restore' => 'cake_orders.delete',
            'cake_orders.comment' => 'cake_orders.view',
            'cake_orders.attachment' => 'cake_orders.view',
            'showroom_cake_requests.edit' => 'showroom_cake_requests.create',
            'showroom_cake_requests.reservations' => 'showroom_cake_requests.create',
            'showroom_cake_requests.accept' => 'showroom_cake_requests.update_status',
            'showroom_cake_requests.ready' => 'showroom_cake_requests.update_status',
            'showroom_cake_requests.cancel_factory' => 'showroom_cake_requests.update_status',
        ];

        foreach (array_keys($map) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Role::query()->where('guard_name', 'web')->with('permissions')->get() as $role) {
            foreach ($map as $new => $old) {
                if (Permission::query()->where('name', $old)->where('guard_name', 'web')->exists()
                    && $role->hasPermissionTo($old, 'web')) {
                    $role->givePermissionTo($new);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep all production role grants intact on rollback.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

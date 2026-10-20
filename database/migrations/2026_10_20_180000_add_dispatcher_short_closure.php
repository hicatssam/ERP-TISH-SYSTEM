<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branch_shipments', function (Blueprint $table): void {
            $table->text('closure_reason')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
        });

        Permission::findOrCreate('branch_shipments.close_short', 'web');
        Role::query()->where('guard_name', 'web')->whereIn('name', ['Dispatcher', 'Admin'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo('branch_shipments.close_short'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('branch_shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_at', 'closure_reason']);
        });
        // Retain permission/role assignments to avoid destructive rollback of user-managed grants.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_cake_requests', function (Blueprint $table): void {
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('showroom_cake_requests.cancel', 'web');
        $branchManager = Role::query()
            ->where('name', 'Branch Manager')
            ->where('guard_name', 'web')
            ->first();

        // Existing production Branch Manager roles are updated without resetting
        // any customer-specific grants or giving managers factory status permissions.
        $branchManager?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('showroom_cake_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        // Preserve explicitly granted permissions during rollback.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

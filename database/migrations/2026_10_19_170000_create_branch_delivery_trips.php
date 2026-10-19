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
            $table->boolean('multi_trip_enabled')->default(false);
        });

        Schema::create('branch_shipment_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_shipment_id')->constrained('branch_shipments')->restrictOnDelete();
            $table->unsignedInteger('trip_number');
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('loaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('loaded_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('status', 20)->default('loaded');
            $table->text('notes')->nullable();
            $table->text('receiving_notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_shipment_id', 'trip_number'], 'branch_trip_number_unique');
            $table->index(['branch_shipment_id', 'status']);
        });

        Schema::create('branch_shipment_trip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_shipment_trip_id')->constrained('branch_shipment_trips')->restrictOnDelete();
            $table->unsignedBigInteger('branch_receiving_invoice_item_id');
            $table->foreign('branch_receiving_invoice_item_id', 'bsti_invoice_item_fk')
                ->references('id')->on('branch_receiving_invoice_items')->restrictOnDelete();
            $table->decimal('loaded_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['branch_shipment_trip_id', 'branch_receiving_invoice_item_id'], 'branch_trip_item_unique');
        });

        Permission::findOrCreate('branch_shipments.manage', 'web');
        foreach (['Dispatcher', 'Factory Manager', 'Admin'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')
                ->get()->each(fn (Role $role) => $role->givePermissionTo('branch_shipments.manage'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_shipment_trip_items');
        Schema::dropIfExists('branch_shipment_trips');
        Schema::table('branch_shipments', fn (Blueprint $table) => $table->dropColumn('multi_trip_enabled'));
    }
};

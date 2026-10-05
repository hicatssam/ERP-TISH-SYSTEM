<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_shipments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('source');
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->string('status', 40)->default('pending_loading')->index();

            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();

            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();

            $table->foreignId('arrived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'branch_shipments_source_unique');
        });

        Schema::create('branch_receiving_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('branch_shipment_id')->unique()->constrained('branch_shipments')->cascadeOnDelete();
            $table->morphs('source');

            $table->foreignId('sending_location_id')->constrained('locations');
            $table->foreignId('receiving_location_id')->constrained('locations');
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_items_sent', 14, 3)->default(0);
            $table->decimal('total_items_received', 14, 3)->default(0);
            $table->decimal('total_difference', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_receiving_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_receiving_invoice_id')
                ->constrained('branch_receiving_invoices')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('source_item_id')->nullable()->index();
            $table->string('description', 500);
            $table->string('unit', 60)->nullable();
            $table->decimal('sent_quantity', 14, 3)->default(0);
            $table->decimal('received_quantity', 14, 3)->nullable();
            $table->decimal('difference_quantity', 14, 3)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_receiving_invoice_items');
        Schema::dropIfExists('branch_receiving_invoices');
        Schema::dropIfExists('branch_shipments');
    }
};

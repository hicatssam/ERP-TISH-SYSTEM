<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showroom_daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('business_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'business_date'], 'showroom_daily_report_location_date_unique');
            $table->index(['business_date', 'status']);
        });

        Schema::create('showroom_daily_report_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('showroom_daily_reports')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 190);
            $table->unsignedInteger('sort_order')->default(0);

            $table->decimal('opening_kg', 12, 3)->default(0);
            $table->decimal('received_trays', 10, 2)->default(0);
            $table->decimal('received_kg', 12, 3)->default(0);
            $table->decimal('sales_kg', 12, 3)->default(0);
            $table->decimal('damaged_kg', 12, 3)->default(0);
            $table->decimal('returned_kg', 12, 3)->default(0);
            $table->decimal('expected_closing_kg', 12, 3)->default(0);
            $table->decimal('actual_closing_kg', 12, 3)->nullable();
            $table->decimal('variance_kg', 12, 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'product_id'], 'showroom_daily_report_product_unique');
            $table->index(['product_id', 'report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showroom_daily_report_items');
        Schema::dropIfExists('showroom_daily_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showroom_cake_daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('business_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'business_date'], 'showroom_cake_daily_location_date_unique');
            $table->index(['business_date', 'status']);
        });

        Schema::create('showroom_cake_daily_report_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('showroom_cake_daily_reports')->cascadeOnDelete();
            $table->string('category_key', 60);
            $table->string('category_name', 120);
            $table->unsignedInteger('sort_order')->default(0);

            $table->integer('opening_qty')->default(0);
            $table->integer('received_qty')->default(0);
            $table->integer('total_qty')->default(0);
            $table->integer('sold_qty')->default(0);
            $table->integer('actual_closing_qty')->nullable();
            $table->integer('variance_qty')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'category_key'], 'showroom_cake_daily_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showroom_cake_daily_report_items');
        Schema::dropIfExists('showroom_cake_daily_reports');
    }
};

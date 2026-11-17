<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_dining_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations');
            $table->string('name', 150);
            $table->unsignedInteger('guest_count')->default(1);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'status']);
        });

        Schema::table('restaurant_table_sessions', function (Blueprint $table): void {
            $table->foreignId('restaurant_dining_group_id')
                ->nullable()
                ->after('restaurant_table_id')
                ->constrained('restaurant_dining_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_table_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('restaurant_dining_group_id');
        });
        Schema::dropIfExists('restaurant_dining_groups');
    }
};

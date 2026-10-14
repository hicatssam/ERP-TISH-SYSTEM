<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table): void {
            // Keep legacy cake_size for existing integrations and historic orders.
            $table->json('cake_sizes')->nullable()->after('cake_size');
        });
    }

    public function down(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table): void {
            $table->dropColumn('cake_sizes');
        });
    }
};

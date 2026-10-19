<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_shipments', function (Blueprint $table): void {
            $table->dateTime('planned_loading_at')->nullable()->after('multi_trip_enabled');
            $table->dateTime('planned_receiving_at')->nullable()->after('planned_loading_at');
        });
    }

    public function down(): void
    {
        Schema::table('branch_shipments', function (Blueprint $table): void {
            $table->dropColumn(['planned_loading_at', 'planned_receiving_at']);
        });
    }
};

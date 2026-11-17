<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            // NULL on historical expenses means "unclassified", not "variable".
            $table->string('cost_behavior', 24)->nullable()->after('expense_category_id');
            $table->string('cost_center', 32)->nullable()->after('cost_behavior');
            $table->index(['location_id', 'cost_behavior', 'cost_center'], 'expenses_cost_behavior_center_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('expenses_cost_behavior_center_idx');
            $table->dropColumn(['cost_behavior', 'cost_center']);
        });
    }
};

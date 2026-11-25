<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive compatibility upgrade for installations whose production_orders
 * and production_order_items predate the QC/FEFO-oriented workflow.
 *
 * Do not drop/rename historic columns or change their constraints here:
 * historic reporting and existing records may still rely on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_orders')) {
            $columns = [
                'quality_required' => fn (Blueprint $table) => $table->boolean('quality_required')->default(false),
                'materials_consumed_at' => fn (Blueprint $table) => $table->timestamp('materials_consumed_at')->nullable(),
                'submitted_quality_at' => fn (Blueprint $table) => $table->timestamp('submitted_quality_at')->nullable(),
                'rejected_at' => fn (Blueprint $table) => $table->timestamp('rejected_at')->nullable(),
                'cancellation_reason' => fn (Blueprint $table) => $table->text('cancellation_reason')->nullable(),
                'rejection_reason' => fn (Blueprint $table) => $table->text('rejection_reason')->nullable(),
            ];
            foreach ($columns as $name => $define) {
                if (! Schema::hasColumn('production_orders', $name)) {
                    Schema::table('production_orders', $define);
                }
            }
        }

        if (Schema::hasTable('production_order_items')) {
            $columns = [
                'planned_quantity' => fn (Blueprint $table) => $table->decimal('planned_quantity', 14, 4)->default(0),
                'reserved_quantity' => fn (Blueprint $table) => $table->decimal('reserved_quantity', 14, 4)->default(0),
                'actual_quantity' => fn (Blueprint $table) => $table->decimal('actual_quantity', 14, 4)->nullable(),
                'actual_unit_cost' => fn (Blueprint $table) => $table->decimal('actual_unit_cost', 16, 4)->default(0),
                'consumed_at' => fn (Blueprint $table) => $table->timestamp('consumed_at')->nullable(),
                'waste_percent' => fn (Blueprint $table) => $table->decimal('waste_percent', 8, 3)->default(0),
            ];
            foreach ($columns as $name => $define) {
                if (! Schema::hasColumn('production_order_items', $name)) {
                    Schema::table('production_order_items', $define);
                }
            }

            // Preserve legacy values instead of initializing the new workflow
            // fields to zero on historic rows. Only fill newly empty values.
            if (Schema::hasColumn('production_order_items', 'planned_stock_quantity')) {
                DB::table('production_order_items')
                    ->where('planned_quantity', 0)
                    ->update(['planned_quantity' => DB::raw('planned_stock_quantity')]);
            }
            if (Schema::hasColumn('production_order_items', 'actual_stock_quantity')) {
                DB::table('production_order_items')
                    ->whereNull('actual_quantity')
                    ->whereNotNull('actual_stock_quantity')
                    ->update(['actual_quantity' => DB::raw('actual_stock_quantity')]);
            }
            if (Schema::hasColumn('production_order_items', 'issued_stock_quantity')) {
                DB::table('production_order_items')
                    ->where('reserved_quantity', 0)
                    ->where('issued_stock_quantity', '>', 0)
                    ->update(['reserved_quantity' => DB::raw('issued_stock_quantity')]);
            }
            if (Schema::hasColumn('production_order_items', 'issued_unit_cost')) {
                DB::table('production_order_items')
                    ->where('actual_unit_cost', 0)
                    ->whereNotNull('issued_unit_cost')
                    ->update(['actual_unit_cost' => DB::raw('issued_unit_cost')]);
            }
            if (Schema::hasColumn('production_order_items', 'actual_total_cost')) {
                DB::table('production_order_items')
                    ->where('actual_cost', 0)
                    ->whereNotNull('actual_total_cost')
                    ->update(['actual_cost' => DB::raw('actual_total_cost')]);
            }
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: historical data backfilled into new
        // columns must not be lost by a migration rollback.
    }
};

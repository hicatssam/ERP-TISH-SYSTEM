<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('id')
                ->constrained('locations')
                ->restrictOnDelete();
        });

        // Existing menu rows came from the first version where menu definition was global.
        // Assign each one to the first branch/location already linked to its Product.
        DB::table('restaurant_menu_items')
            ->orderBy('id')
            ->get()
            ->each(function ($menuItem): void {
                $locationId = DB::table('location_products')
                    ->where('product_id', $menuItem->product_id)
                    ->orderByDesc('is_available')
                    ->orderBy('id')
                    ->value('location_id');

                if ($locationId) {
                    DB::table('restaurant_menu_items')
                        ->where('id', $menuItem->id)
                        ->update(['location_id' => $locationId]);
                }
            });

        // In MySQL, a foreign key on product_id needs a product_id-leading
        // index. Install a non-unique supporting index BEFORE dropping the
        // legacy global unique index or InnoDB rejects the migration (1553).
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->index('product_id', 'restaurant_menu_product_fk_idx');
        });

        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->dropUnique('restaurant_menu_items_product_id_unique');
            $table->unique(
                ['location_id', 'product_id'],
                'restaurant_menu_location_product_unique'
            );
            $table->index(
                ['location_id', 'is_active', 'show_in_pos'],
                'restaurant_menu_pos_scope_idx'
            );
        });
    }

    public function down(): void
    {
        // Reverting the branch-scoped schema would collapse multiple branch
        // menu records for one product. Reject rollback instead of destroying
        // real customer menu configurations.
        $duplicated = DB::table('restaurant_menu_items')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicated) {
            throw new RuntimeException(
                'Cannot revert branch-scoped restaurant menu while the same product has multiple branch rows.'
            );
        }

        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->unique('product_id', 'restaurant_menu_items_product_id_unique');
        });
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->dropIndex('restaurant_menu_pos_scope_idx');
            $table->dropUnique('restaurant_menu_location_product_unique');
            $table->dropConstrainedForeignId('location_id');
            $table->dropIndex('restaurant_menu_product_fk_idx');
        });
    }
};

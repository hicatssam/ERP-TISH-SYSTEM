<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_menu_items', 'is_takeaway_item')) {
                $table->boolean('is_takeaway_item')
                    ->default(false)
                    ->after('show_in_delivery')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_menu_items', 'is_takeaway_item')) {
                $table->dropColumn('is_takeaway_item');
            }
        });
    }
};

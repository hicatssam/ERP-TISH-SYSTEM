<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'ingredients_ar')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('ingredients_ar')->nullable();
            });
        }

        if (!Schema::hasColumn('products', 'ingredients_en')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('ingredients_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['ingredients_ar', 'ingredients_en'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

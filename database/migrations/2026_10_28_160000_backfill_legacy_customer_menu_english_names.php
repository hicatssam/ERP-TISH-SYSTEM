<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only the two legacy Arabic-only records confirmed in the public
        // menu screenshot. Preserve name_ar and never overwrite translations
        // already saved by an operator.
        if (Schema::hasTable('categories')) {
            foreach (DB::table('categories')->where('name', 'حلويات شرقية')->get(['id', 'name', 'name_ar']) as $category) {
                DB::table('categories')->where('id', $category->id)->update([
                    'name' => 'Eastern Sweets',
                    'name_ar' => filled($category->name_ar) ? $category->name_ar : 'حلويات شرقية',
                ]);
            }
        }

        if (Schema::hasTable('products')) {
            foreach (DB::table('products')->where('name', 'عش بلبل أصابع')->get(['id', 'name', 'name_ar']) as $product) {
                DB::table('products')->where('id', $product->id)->update([
                    'name' => "Bird's Nest Pastry Fingers",
                    'name_ar' => filled($product->name_ar) ? $product->name_ar : 'عش بلبل أصابع',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Production catalog text is business data. Do not revert any name
        // that editors may have corrected after this migration ran.
    }
};

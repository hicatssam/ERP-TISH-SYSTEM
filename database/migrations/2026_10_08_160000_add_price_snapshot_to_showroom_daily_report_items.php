<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            $table->decimal('selling_price_per_kg', 12, 2)->nullable();
            $table->boolean('selling_price_is_reference')->default(false);
        });

        // Historical reports have no price snapshot. Mark their current branch
        // price as a reference, never as the original invoiced selling price.
        DB::table('showroom_daily_report_items as items')
            ->join('showroom_daily_reports as reports', 'reports.id', '=', 'items.report_id')
            ->leftJoin('products', 'products.id', '=', 'items.product_id')
            ->leftJoin('location_products as prices', function ($join): void {
                $join->on('prices.product_id', '=', 'items.product_id')
                    ->on('prices.location_id', '=', 'reports.location_id');
            })
            ->select('items.id', DB::raw('COALESCE(prices.local_selling_price, products.base_selling_price) as price'))
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    DB::table('showroom_daily_report_items')->where('id', $item->id)->update([
                        'selling_price_per_kg' => $item->price,
                        'selling_price_is_reference' => true,
                    ]);
                }
            }, 'items.id', 'id');
    }

    public function down(): void
    {
        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            $table->dropColumn(['selling_price_per_kg', 'selling_price_is_reference']);
        });
    }
};

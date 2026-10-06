<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('showroom_daily_reports')
            || ! Schema::hasTable('showroom_daily_report_items')
            || ! Schema::hasColumn('showroom_daily_report_items', 'takeaway_sales_kg')
        ) {
            return;
        }

        DB::table('showroom_daily_reports')
            ->select(['id', 'location_id', 'business_date'])
            ->orderBy('id')
            ->chunkById(100, function ($reports): void {
                foreach ($reports as $report) {
                    $start = CarbonImmutable::parse($report->business_date)
                        ->startOfDay();
                    $end = $start->addDay();

                    $totals = DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->where('orders.location_id', $report->location_id)
                        ->whereNull('orders.deleted_at')
                        ->where('orders.restaurant_service_type', 'takeaway')
                        ->whereIn('orders.status', ['confirmed', 'completed'])
                        ->where(function ($query) use ($start, $end): void {
                            $query
                                ->where(function ($confirmed) use ($start, $end): void {
                                    $confirmed
                                        ->whereNotNull('orders.confirmed_at')
                                        ->where('orders.confirmed_at', '>=', $start)
                                        ->where('orders.confirmed_at', '<', $end);
                                })
                                ->orWhere(function ($legacy) use ($start, $end): void {
                                    $legacy
                                        ->whereNull('orders.confirmed_at')
                                        ->where('orders.created_at', '>=', $start)
                                        ->where('orders.created_at', '<', $end);
                                });
                        })
                        ->groupBy('order_items.product_id')
                        ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total_quantity')
                        ->pluck('total_quantity', 'product_id');

                    foreach ($totals as $productId => $quantity) {
                        DB::table('showroom_daily_report_items')
                            ->where('report_id', $report->id)
                            ->where('product_id', $productId)
                            ->update([
                                'takeaway_sales_kg' => round((float) $quantity, 3),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Historical Take Away values are derived system data; no rollback needed.
    }
};

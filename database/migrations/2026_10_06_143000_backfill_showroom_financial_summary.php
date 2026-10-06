<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('showroom_daily_reports')
            || ! Schema::hasTable('invoices')
            || ! Schema::hasTable('cash_sessions')
        ) {
            return;
        }

        $reports = DB::table('showroom_daily_reports')
            ->orderBy('location_id')
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $previousByLocation = [];

        foreach ($reports as $report) {
            $start = CarbonImmutable::parse(
                $report->business_date
            )->startOfDay();

            $end = $start->addDay();

            $previousPackageBalance = (float) (
                $previousByLocation[$report->location_id]
                    ?? 0
            );

            $reportSalesTotal = (float) DB::table('invoices')
                ->where('location_id', $report->location_id)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->where('order_type', 'order')
                ->where('issued_at', '>=', $start)
                ->where('issued_at', '<', $end)
                ->sum('total_amount');

            $cashierBalanceTotal = (float) DB::table('cash_sessions')
                ->where('location_id', $report->location_id)
                ->where('opened_at', '>=', $start)
                ->where('opened_at', '<', $end)
                ->selectRaw(
                    'COALESCE(SUM(COALESCE(actual_cash, expected_cash)), 0) as total'
                )
                ->value('total');

            $reservationsSettlements = (float) (
                $report->accounting_total_secondary
                ?? 0
            );

            $discounts = (float) (
                $report->customer_discount_amount
                ?? 0
            );

            $financialTotal = round(
                $reportSalesTotal
                + $reservationsSettlements
                - $discounts,
                2
            );

            $netBooking = round(
                $cashierBalanceTotal
                - $financialTotal,
                2
            );

            DB::table('showroom_daily_reports')
                ->where('id', $report->id)
                ->update([
                    'previous_package_balance' =>
                        round($previousPackageBalance, 2),
                    'report_sales_total' =>
                        round($reportSalesTotal, 2),
                    'financial_report_total' =>
                        $financialTotal,
                    'cashier_balance_total' =>
                        round($cashierBalanceTotal, 2),
                    'net_booking_total' =>
                        $netBooking,
                ]);

            $previousByLocation[$report->location_id] = (float) (
                $report->trays_packages_balance
                ?? 0
            );
        }
    }

    public function down(): void
    {
        // Snapshot backfill is intentionally preserved.
    }
};

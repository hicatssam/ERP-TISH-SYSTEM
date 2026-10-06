<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('showroom_daily_reports', 'previous_package_balance')) {
                $table->decimal('previous_package_balance', 14, 2)
                    ->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'report_sales_total')) {
                $table->decimal('report_sales_total', 14, 2)
                    ->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'financial_report_total')) {
                $table->decimal('financial_report_total', 14, 2)
                    ->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'cashier_balance_total')) {
                $table->decimal('cashier_balance_total', 14, 2)
                    ->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'net_booking_total')) {
                $table->decimal('net_booking_total', 14, 2)
                    ->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'previous_package_balance',
                'report_sales_total',
                'financial_report_total',
                'cashier_balance_total',
                'net_booking_total',
            ]);
        });
    }
};

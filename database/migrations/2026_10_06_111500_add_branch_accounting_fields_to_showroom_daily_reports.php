<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->decimal('credit_debt_amount', 14, 2)->default(0)->after('notes');
            $table->decimal('empty_packages_balance', 14, 2)->default(0)->after('credit_debt_amount');
            $table->decimal('tahlayat_amount', 14, 2)->default(0)->after('empty_packages_balance');
            $table->decimal('customer_discount_total', 14, 2)->default(0)->after('tahlayat_amount');
            $table->decimal('prepaid_discount_amount', 14, 2)->default(0)->after('customer_discount_total');
            $table->decimal('reservations_amount', 14, 2)->default(0)->after('prepaid_discount_amount');
            $table->decimal('report_total_amount', 14, 2)->default(0)->after('reservations_amount');
        });
    }

    public function down(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'credit_debt_amount',
                'empty_packages_balance',
                'tahlayat_amount',
                'customer_discount_total',
                'prepaid_discount_amount',
                'reservations_amount',
                'report_total_amount',
            ]);
        });
    }
};

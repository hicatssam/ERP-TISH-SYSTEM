<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('showroom_daily_reports', 'debts_amount')) {
                $table->decimal('debts_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'trays_packages_balance')) {
                $table->decimal('trays_packages_balance', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'customer_treats_amount')) {
                $table->decimal('customer_treats_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'customer_discount_amount')) {
                $table->decimal('customer_discount_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'previous_prepaid_amount')) {
                $table->decimal('previous_prepaid_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'accounting_total_primary')) {
                $table->decimal('accounting_total_primary', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'deferred_payment_amount')) {
                $table->decimal('deferred_payment_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'special_reservations_amount')) {
                $table->decimal('special_reservations_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'debt_repayment_amount')) {
                $table->decimal('debt_repayment_amount', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'accounting_total_secondary')) {
                $table->decimal('accounting_total_secondary', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('showroom_daily_reports', 'returned_for_correction_at')) {
                $table->timestamp('returned_for_correction_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'debts_amount',
                'trays_packages_balance',
                'customer_treats_amount',
                'customer_discount_amount',
                'previous_prepaid_amount',
                'accounting_total_primary',
                'deferred_payment_amount',
                'special_reservations_amount',
                'debt_repayment_amount',
                'accounting_total_secondary',
                'returned_for_correction_at',
            ]);
        });
    }
};

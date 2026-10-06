<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('showroom_daily_reports')) {
            return;
        }

        $required = [
            'credit_debt_amount',
            'empty_packages_balance',
            'tahlayat_amount',
            'customer_discount_total',
            'prepaid_discount_amount',
            'reservations_amount',
            'report_total_amount',
            'debts_amount',
            'trays_packages_balance',
            'customer_treats_amount',
            'customer_discount_amount',
            'previous_prepaid_amount',
            'special_reservations_amount',
            'accounting_total_primary',
            'accounting_total_secondary',
        ];

        foreach ($required as $column) {
            if (! Schema::hasColumn('showroom_daily_reports', $column)) {
                return;
            }
        }

        DB::table('showroom_daily_reports')
            ->orderBy('id')
            ->chunkById(100, function ($reports): void {
                foreach ($reports as $report) {
                    $debts = (float) ($report->debts_amount ?: $report->credit_debt_amount ?: 0);
                    $trays = (float) ($report->trays_packages_balance ?: $report->empty_packages_balance ?: 0);
                    $treats = (float) ($report->customer_treats_amount ?: $report->tahlayat_amount ?: 0);
                    $discount = (float) ($report->customer_discount_amount ?: $report->customer_discount_total ?: 0);
                    $prepaid = (float) ($report->previous_prepaid_amount ?: $report->prepaid_discount_amount ?: 0);
                    $specialReservations = (float) ($report->special_reservations_amount ?: $report->reservations_amount ?: 0);

                    DB::table('showroom_daily_reports')
                        ->where('id', $report->id)
                        ->update([
                            'debts_amount' => $debts,
                            'trays_packages_balance' => $trays,
                            'customer_treats_amount' => $treats,
                            'customer_discount_amount' => $discount,
                            'previous_prepaid_amount' => $prepaid,
                            'special_reservations_amount' => $specialReservations,
                            'accounting_total_primary' => round(
                                $debts + $trays + $treats - $discount - $prepaid,
                                2
                            ),
                            'accounting_total_secondary' => round(
                                (float) ($report->deferred_payment_amount ?? 0)
                                + $specialReservations
                                - (float) ($report->debt_repayment_amount ?? 0),
                                2
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no rollback: this migration preserves user-entered data.
    }
};

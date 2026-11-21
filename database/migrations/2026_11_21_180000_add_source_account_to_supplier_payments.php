<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_payment_accounts', function (Blueprint $table): void {
            // Existing customer-facing receiving accounts are NOT implicitly
            // authorized for outward transfers.
            $table->boolean('can_disburse')->default(false);
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->foreignId('source_payment_account_id')->nullable()
                ->constrained('location_payment_accounts')->restrictOnDelete();
            $table->string('beneficiary_name', 255)->nullable();
            $table->string('beneficiary_account', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_payment_account_id');
            $table->dropColumn(['beneficiary_name', 'beneficiary_account']);
        });
        Schema::table('location_payment_accounts', function (Blueprint $table): void {
            $table->dropColumn('can_disburse');
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->uuid('request_token')->nullable();
            $table->unique(['payment_id', 'request_token'], 'refund_payment_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique('refund_payment_token_unique');
            $table->dropColumn('request_token');
        });
    }
};

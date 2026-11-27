<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $account = DB::table('accounting_accounts')->where('code', '6300')->first();
        if ($account && ($account->name !== 'هدر المواد والوجبات' || $account->type !== 'expense')) {
            throw new RuntimeException('Accounting code 6300 is already assigned to another account.');
        }
        if (! $account) {
            DB::table('accounting_accounts')->insert([
                'code' => '6300', 'name' => 'هدر المواد والوجبات',
                'type' => 'expense', 'subtype' => null,
                'is_active' => true, 'is_system' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::table('order_inventory_consumptions', function (Blueprint $table) {
            $table->timestamp('wasted_at')->nullable();
            $table->index(['location_id', 'wasted_at'], 'order_consumption_waste_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_inventory_consumptions', function (Blueprint $table) {
            $table->dropIndex('order_consumption_waste_idx');
            $table->dropColumn('wasted_at');
        });
        // Preserve the account and any journal entries for financial history.
    }
};

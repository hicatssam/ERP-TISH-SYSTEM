<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        if (! DB::table('accounting_accounts')->where('code', '2300')->exists()) {
            DB::table('accounting_accounts')->insert([
                'code' => '2300',
                'name' => 'تسوية التحويلات بين الفروع',
                'type' => 'liability',
                'subtype' => null,
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve accounting history and prevent deletion of a referenced
        // clearing account during a rollback.
    }
};

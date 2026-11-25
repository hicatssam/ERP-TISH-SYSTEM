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

        DB::table('accounting_accounts')->insertOrIgnore([
            'code' => '2400',
            'name' => 'دفعات مقدمة من العملاء',
            'type' => 'liability',
            'subtype' => null,
            'is_active' => true,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Never remove an account that may contain posted accounting history.
    }
};

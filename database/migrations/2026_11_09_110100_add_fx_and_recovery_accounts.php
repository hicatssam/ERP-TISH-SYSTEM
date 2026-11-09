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

        foreach ([
            ['1160', 'ذمم استرداد ومبالغ قابلة للتحصيل', 'asset'],
            ['4200', 'أرباح فروقات الصرف المحققة', 'revenue'],
            ['6200', 'خسائر فروقات الصرف المحققة', 'expense'],
        ] as [$code, $name, $type]) {
            if (DB::table('accounting_accounts')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('accounting_accounts')->insert([
                'code' => $code,
                'name' => $name,
                'type' => $type,
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
        // Do not delete accounts referenced by historical journal entries.
    }
};

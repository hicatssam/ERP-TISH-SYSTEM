<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();

        foreach ([
            'attendance_enabled' => '1',
            'attendance_biometric_enabled' => '1',
            'attendance_employee_face_punch_enabled' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => 'boolean',
                    'group' => 'attendance',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $baseUrlExists = DB::table('system_settings')
            ->where('key', 'compreface_base_url')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->exists();

        if (! $baseUrlExists) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'compreface_base_url'],
                [
                    'value' => 'http://127.0.0.1:8001',
                    'type' => 'string',
                    'group' => 'attendance',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Feature activation is intentionally not rolled back to avoid
        // unexpectedly disabling attendance on a live installation.
    }
};

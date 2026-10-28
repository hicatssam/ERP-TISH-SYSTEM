<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Keep any previously entered custom text intact on repeated deployments.
        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'brand_custom_text_ar',
                'value' => '',
                'type' => 'text',
                'group' => 'branding',
                'label' => 'النص المخصص بالعربية',
                'description' => 'نص عربي مستقل للعلامة التجارية، غير الشعار النصي المختصر أو نص الفوتر.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'brand_custom_text_en',
                'value' => '',
                'type' => 'text',
                'group' => 'branding',
                'label' => 'النص المخصص بالإنجليزية',
                'description' => 'نص إنجليزي مستقل للعلامة التجارية، غير الشعار النصي المختصر أو نص الفوتر.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // Do not erase user-authored branding copy when rolling back code.
    }
};

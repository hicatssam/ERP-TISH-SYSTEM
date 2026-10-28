<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Preserve existing Arabic home title/subtitle and previous custom settings.
        // A blank English title/subtitle falls back to legacy text and translations.
        $entries = [
            ['customer_menu_title_en', '', 'العنوان الرئيسي للمنيو — English', 'عنوان واجهة الطلب باللغة الإنجليزية.'],
            ['customer_menu_subtitle_en', '', 'النص المساعد للمنيو — English', 'وصف واجهة الطلب باللغة الإنجليزية.'],
            ['customer_menu_search_placeholder_ar', 'ابحث عن صنفك المفضل...', 'نص البحث — عربي', 'النص الظاهر داخل خانة البحث عن الأصناف في منيو العميل.'],
            ['customer_menu_search_placeholder_en', 'Search your favorite items...', 'نص البحث — English', 'Search placeholder on the customer menu and full catalog.'],
        ];

        foreach ($entries as [$key, $value, $label, $description]) {
            DB::table('system_settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'group' => 'customer_menu',
                'label' => $label,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep any copy entered by administrators, even if application code is rolled back.
    }
};

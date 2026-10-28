<?php

namespace App\Support;

use App\Models\SystemSetting;

final class CustomerMenuCopy
{
    public static function homeTitle(): string
    {
        return self::localized('customer_menu_title', 'customer_menu_title_en', 'أهلاً بك', [
            'أهلاً بك' => 'Welcome',
            'اطلب مباشرة' => 'Order directly',
        ]);
    }

    public static function homeSubtitle(): string
    {
        return self::localized('customer_menu_subtitle', 'customer_menu_subtitle_en', 'شو بتحب تأكل اليوم؟', [
            'شو بتحب تأكل اليوم؟' => 'What would you like today?',
            'اختر طلبك وسنجهزه لك' => "Choose your order and we'll prepare it for you",
        ]);
    }

    public static function searchPlaceholder(): string
    {
        return self::localized(
            'customer_menu_search_placeholder_ar',
            'customer_menu_search_placeholder_en',
            'ابحث عن صنفك المفضل...',
            ['ابحث عن صنفك المفضل...' => 'Search your favorite items...'],
            'Search your favorite items...'
        );
    }

    /**
     * Configured translations take priority. Existing Arabic title/subtitle
     * remain untouched; known defaults have a safe English fallback.
     */
    private static function localized(
        string $arabicKey,
        string $englishKey,
        string $arabicDefault,
        array $legacyTranslations = [],
        string $englishDefault = ''
    ): string {
        $arabic = trim((string) SystemSetting::get($arabicKey, $arabicDefault));
        if (! CustomerMenuLanguage::isEnglish()) {
            return $arabic;
        }

        $english = trim((string) SystemSetting::get($englishKey, $englishDefault));

        return $english !== '' ? $english : ($legacyTranslations[$arabic] ?? $arabic);
    }
}

<?php

namespace App\Support;

/**
 * Public customer menu locale only. Never changes admin/POS language,
 * inventory, product IDs, prices or persisted customer orders.
 */
final class CustomerMenuLanguage
{
    public static function current(): string
    {
        $query = request()->query('lang');
        if (in_array($query, ['ar', 'en'], true)) {
            return $query;
        }

        return request()->cookie('dahab_menu_lang') === 'en' ? 'en' : 'ar';
    }

    public static function isEnglish(): bool
    {
        return self::current() === 'en';
    }

    public static function direction(): string
    {
        return self::isEnglish() ? 'ltr' : 'rtl';
    }

    /** Use the catalog's existing English and Arabic fields; never machine-translate product names. */
    public static function name(?string $english, ?string $arabic): string
    {
        $first = self::isEnglish() ? $english : $arabic;
        $fallback = self::isEnglish() ? $arabic : $english;

        return trim((string) ($first ?: $fallback));
    }

    public static function englishName(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (filled($candidate) && ! preg_match('/\p{Arabic}/u', (string) $candidate)) {
                return trim((string) $candidate);
            }
        }

        return trim((string) (collect($candidates)->first(fn ($value) => filled($value)) ?? ''));
    }
}

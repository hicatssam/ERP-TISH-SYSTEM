<?php

namespace App\Support;

/**
 * Arabic presentation of cake attributes used by the unified production report.
 *
 * This never changes stored values or SQL grouping. Custom free-text values
 * remain intact, while recognized system codes are translated consistently.
 */
final class CakeProductionLabels
{
    private const TYPES = [
        'chocolate' => 'شوكولاتة',
        'vanilla' => 'فانيلا',
        'red_velvet' => 'ريد فيلفيت',
        'caramel' => 'كراميل',
        'fruit' => 'فاكهة',
        'strawberry' => 'فراولة',
        'coffee' => 'قهوة',
        'oreo' => 'أوريو',
        'lotus' => 'لوتس',
        'other' => 'أخرى',
        'custom' => 'حسب الطلب',
    ];

    private const SIZES = [
        'standard' => 'ستاندر',
        'mini' => 'ميني',
        'small' => 'صغير',
        'medium' => 'وسط',
        'large' => 'كبير',
        'x_large' => 'كبير جداً',
        'extra_large' => 'كبير جداً',
    ];

    private const SHAPES = [
        'round' => 'دائري',
        'slab' => 'بلاطة',
        'square' => 'مربع',
        'rectangle' => 'مستطيل',
        'rectangular' => 'مستطيل',
        'oval' => 'بيضاوي',
        'heart' => 'قلب',
        'wedding_tiers' => 'طوابق أفراح',
        'standard_tiers' => 'طوابق ستاندر',
        'other' => 'أخرى',
        'custom' => 'شكل خاص',
    ];

    private const FLAVORS = [
        'chocolate' => 'شوكولاتة',
        'vanilla' => 'فانيلا',
        'strawberry' => 'فراولة',
        'caramel' => 'كراميل',
        'nutella' => 'نوتيلا',
        'pistachio' => 'فستق',
        'oreo' => 'أوريو',
        'lotus' => 'لوتس',
        'coffee' => 'قهوة',
        'fruit' => 'فواكه',
        'other' => 'أخرى',
    ];

    public static function flavor(?string $value): string
    {
        return self::label($value, self::FLAVORS);
    }

    public static function sizes(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return self::size($value);
        }

        $sizes = array_filter(array_map(
            static fn ($size) => is_scalar($size) ? self::size((string) $size) : null,
            $decoded
        ));

        return $sizes ? implode('، ', $sizes) : '—';
    }

    public static function type(?string $value): string
    {
        return self::label($value, self::TYPES);
    }

    public static function size(?string $value): string
    {
        $label = self::label($value, self::SIZES);

        // A slab can be stored as 40x30, 40X30 or 40×30.
        if (preg_match('/^\d+\s*[xX×]\s*\d+$/u', $label)) {
            return preg_replace('/\s*[xX×]\s*/u', ' × ', $label);
        }

        return $label;
    }

    public static function shape(?string $value): string
    {
        return self::label($value, self::SHAPES);
    }

    private static function label(?string $value, array $labels): string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return 'غير محدد';
        }

        return $labels[strtolower($clean)] ?? $clean;
    }
}

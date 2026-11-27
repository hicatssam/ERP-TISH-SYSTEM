<?php

namespace App\Support;

final class MenuIconLibrary
{
    /**
     * Central icon catalog.
     * The key is stored in DB, the label is only for the admin picker.
     */
    public static function options(): array
    {
        return [
            'tish-all' => 'TISH · الكل',
            'tish-beef-burger' => 'TISH · برجر لحم',
            'tish-chicken-burger' => 'TISH · برجر دجاج',
            'tish-veggie-burger' => 'TISH · برجر نباتي',
            'tish-fries' => 'TISH · الفرايز',
            'tish-appetizers' => 'TISH · المقبلات',
            'tish-sauces' => 'TISH · الصوصات',
            'tish-cold-drinks' => 'TISH · المشروبات الباردة',
            'burger' => 'برجر لحم',
            'burger-chicken' => 'برجر دجاج',
            'burger-veggie' => 'برجر نباتي',
            'sauce' => 'صوصات',
            'appetizers' => 'مقبلات',
            'pizza' => 'بيتزا',
            'coffee' => 'قهوة',
            'cake' => 'كيك',
            'croissant' => 'مخبوزات',
            'donut' => 'دونات',
            'icecream' => 'آيس كريم',
            'drink' => 'مشروبات',
            'juice' => 'عصائر',
            'fries' => 'بطاطا',
            'chicken' => 'دجاج',
            'salad' => 'سلطات',
            'sandwich' => 'ساندويتش',
            'chocolate' => 'شوكولاتة',
            'gift' => 'هدايا',
            'dessert' => 'حلويات',
            'tea' => 'شاي',
            'breakfast' => 'فطور',
            'sparkles' => 'أخرى',
            'custom' => 'أيقونة مرفوعة',
        ];
    }

    /** Original TISH artwork, served locally; never depends on Drive access. */
    public static function artwork(?string $key): ?string
    {
        return match ($key) {
            'tish-all' => 'assets/tish-category-icons/01-tish-all.png',
            'tish-beef-burger' => 'assets/tish-category-icons/02-tish-beef-burger.png',
            'tish-chicken-burger' => 'assets/tish-category-icons/03-tish-chicken-burger.png',
            'tish-veggie-burger' => 'assets/tish-category-icons/04-tish-veggie-burger.png',
            'tish-fries' => 'assets/tish-category-icons/05-tish-fries.png',
            'tish-appetizers' => 'assets/tish-category-icons/06-tish-appetizers.png',
            'tish-sauces' => 'assets/tish-category-icons/07-tish-sauces.png',
            'tish-cold-drinks' => 'assets/tish-category-icons/08-tish-cold-drinks.png',
            default => null,
        };
    }

    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $key): string
    {
        return self::options()[$key ?? ''] ?? 'أخرى';
    }
}

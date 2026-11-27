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

    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $key): string
    {
        return self::options()[$key ?? ''] ?? 'أخرى';
    }
}

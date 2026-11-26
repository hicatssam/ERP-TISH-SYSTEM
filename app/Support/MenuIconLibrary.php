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
            'burger' => 'برغر',
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
            'sauce' => 'صوصات',
            'appetizer' => 'مقبلات',
            'cold-drink' => 'مشروبات باردة',
            'utensils' => 'أدوات طعام',
            'sparkles' => 'أخرى',
        ];
    }

    public static function fontAwesome(?string $key): string
    {
        return [
            'burger' => 'fa-burger', 'pizza' => 'fa-pizza-slice',
            'coffee' => 'fa-mug-hot', 'cake' => 'fa-cake-candles',
            'croissant' => 'fa-bread-slice', 'donut' => 'fa-cookie-bite',
            'icecream' => 'fa-ice-cream', 'drink' => 'fa-bottle-water',
            'juice' => 'fa-glass-water', 'fries' => 'fa-box',
            'chicken' => 'fa-drumstick-bite', 'salad' => 'fa-bowl-food',
            'sandwich' => 'fa-burger', 'chocolate' => 'fa-cookie',
            'gift' => 'fa-gift', 'dessert' => 'fa-cookie-bite',
            'tea' => 'fa-mug-saucer', 'breakfast' => 'fa-egg',
            'sauce' => 'fa-jar', 'appetizer' => 'fa-bowl-rice',
            'cold-drink' => 'fa-glass-water', 'utensils' => 'fa-utensils',
            'sparkles' => 'fa-wand-magic-sparkles',
        ][$key ?? ''] ?? 'fa-wand-magic-sparkles';
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

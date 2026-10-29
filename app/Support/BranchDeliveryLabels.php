<?php

namespace App\Support;

/**
 * Arabic labels for branch delivery documents.
 *
 * These are read-only presentation mappings. Existing invoice snapshots,
 * stock movements, product names, quantities, and internal codes are untouched.
 */
final class BranchDeliveryLabels
{
    private const UNITS = [
        'pc' => 'حبة', 'pcs' => 'حبة', 'piece' => 'حبة', 'pieces' => 'حبة',
        'each' => 'حبة', 'unit' => 'وحدة', 'units' => 'وحدة',
        'cake' => 'كيك',
        'box' => 'علبة', 'boxes' => 'علبة',
        'tray' => 'صينية', 'trays' => 'صينية',
        'plate' => 'صدر', 'plates' => 'صدر',
        'kg' => 'كغم', 'kgs' => 'كغم', 'kilogram' => 'كغم', 'kilograms' => 'كغم',
        'g' => 'غرام', 'gram' => 'غرام', 'grams' => 'غرام',
        'pack' => 'عبوة', 'packs' => 'عبوة',
    ];

    public static function unit(?string $value): string
    {
        $unit = trim((string) $value);

        return self::UNITS[mb_strtolower($unit)] ?? ($unit === '' ? '—' : $unit);
    }

    public static function itemDescription(?string $description): string
    {
        $description = trim((string) $description);
        if ($description === '') {
            return '—';
        }

        // Cake invoices store the cake attributes separated by an em dash.
        // Translate only exact known codes; never translate a custom product
        // name or alter the stored invoice description.
        $segments = preg_split('/\s+—\s+/u', $description) ?: [$description];

        return implode(' — ', array_map(static function (string $value): string {
            $value = trim($value);
            $translated = CakeProductionLabels::shape($value);

            if ($translated !== $value) {
                return $translated;
            }

            foreach ([
                CakeProductionLabels::size($value),
                CakeProductionLabels::type($value),
                CakeProductionLabels::flavor($value),
            ] as $label) {
                if ($label !== $value) {
                    return $label;
                }
            }

            return $value;
        }, $segments));
    }
}

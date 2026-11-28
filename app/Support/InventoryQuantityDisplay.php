<?php

namespace App\Support;

/** Presentation only: never writes stock or changes the underlying base quantity. */
final class InventoryQuantityDisplay
{
    public static function forBatch(object $batch): array
    {
        return self::format($batch->available_quantity, $batch->unit_code ?? null,
            $batch->legacy_unit ?? null, $batch->unit_name_ar ?? null,
            $batch->unit_name ?? null, $batch->unit_symbol ?? null);
    }

    public static function format(float|int|string $quantity, ?string $code = null, ?string $legacy = null,
        ?string $arabic = null, ?string $name = null, ?string $symbol = null): array
    {
        $key = mb_strtolower(trim($code ?: ($legacy ?? '')));
        $unit = match ($key) {
            'kg', 'kilogram', 'كيلو', 'كيلوغرام', 'كيلو غرام', 'كغم', 'كجم' => 'كغم',
            'g', 'gram', 'غرام', 'جرام', 'غم' => 'غرام',
            'l', 'liter', 'litre', 'لتر' => 'لتر',
            'ml', 'milliliter', 'millilitre', 'مل', 'ملليلتر' => 'مل',
            'piece', 'pcs', 'pc', 'each', 'قطعة', 'حبة' => 'قطعة',
            'cup', 'كوب' => 'كوب',
            'pack', 'packet', 'عبوة' => 'عبوة',
            'box', 'صندوق' => 'صندوق',
            default => trim($arabic ?: ($symbol ?: ($name ?: ($code ?: ($legacy ?? ''))))),
        };
        $known = $unit !== '';
        $baseUnit = $known ? $unit : 'وحدة غير محددة';
        $amount = self::number((float) $quantity);
        $original = $amount.' '.$baseUnit;
        // Inventory batches are stored to three decimals. Converting to the
        // smaller metric unit below one avoids leading zeros without rounding stock.
        if (abs((float) $quantity) > 0 && abs((float) $quantity) < 1) {
            if ($unit === 'كغم') {
                $amount = self::number((float) $quantity * 1000);
                $unit = 'غرام';
            } elseif ($unit === 'لتر') {
                $amount = self::number((float) $quantity * 1000);
                $unit = 'مل';
            }
        }
        return ['amount'=>$amount, 'unit'=>$known ? $unit : $baseUnit,
            'base_unit'=>$baseUnit, 'original'=>$original, 'known'=>$known];
    }

    public static function text(object $batch): string
    {
        $value = self::forBatch($batch);
        return $value['amount'].' '.$value['unit'];
    }

    private static function number(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ','), '0'), '.');
    }
}

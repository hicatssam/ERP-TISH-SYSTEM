<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * A factory-friendly cross-tab for the unified cake report.
 *
 * Uses the already-aggregated report rows; does not change source statuses,
 * production counts, or the special-order counting rules.
 */
final class CakeProductionMatrix
{
    private const KINDS = [
        'regular' => 'الكيك بالأحجام',
        'slab' => 'الكيك البلاطة حسب المقاس',
        'other' => 'الأشكال والأحجام الخاصة',
    ];

    private const REGULAR_SIZES = [
        'كبير' => 'كبير',
        'وسط' => 'وسط',
        'صغير' => 'صغير',
    ];

    private const SLAB_SIZES = [
        '30 × 30' => '30 × 30',
        '40 × 30' => '40 × 30',
        '40 × 40' => '40 × 40',
    ];

    /** @return array{sections: array, totals: array, row_count: int} */
    public static function build(iterable $rows, string $mode = 'all'): array
    {
        $sections = [];
        $totals = self::emptyAmounts();
        $lineCount = 0;

        foreach ($rows as $entry) {
            $lineCount++;
            $date = $mode === 'plan'
                ? (substr(trim((string) ($entry->first_needed_date ?? '')), 0, 10) ?: 'غير محدد')
                : '';
            $type = CakeProductionLabels::type($entry->cake_type ?? null);
            $flavor = CakeProductionLabels::flavor($entry->detail_flavor ?? null);
            $shape = CakeProductionLabels::shape($entry->shape ?? null);
            $layers = $entry->layer_count === null || $entry->layer_count === ''
                ? 'غير محدد'
                : (string) $entry->layer_count;
            $size = CakeProductionLabels::size($entry->cake_size ?? null);

            $rawShape = strtolower(trim((string) ($entry->shape ?? '')));
            $isSlab = in_array($rawShape, ['slab', 'بلاطة'], true)
                || preg_match('/^\d+\s*×\s*\d+$/u', $size) === 1;

            $isSpecialShape = in_array($rawShape, [
                'heart', 'heart_head', 'square', 'rectangle', 'rectangular',
                'oval', 'wedding_tiers', 'standard_tiers', 'custom', 'other',
                'قلب', 'رأس قلب', 'مربع', 'مستطيل', 'بيضاوي', 'طوابق أفراح',
                'طوابق ستاندر', 'شكل خاص',
            ], true);

            $kind = $isSlab
                ? 'slab'
                : ($isSpecialShape || ! isset(self::REGULAR_SIZES[$size])
                    ? 'other'
                    : 'regular');

            $sectionId = json_encode([$date, $kind], JSON_UNESCAPED_UNICODE);
            if (! isset($sections[$sectionId])) {
                $columns = match ($kind) {
                    'regular' => self::REGULAR_SIZES,
                    'slab' => self::SLAB_SIZES,
                    default => [],
                };
                $sections[$sectionId] = [
                    'kind' => $kind,
                    'title' => self::KINDS[$kind],
                    'date' => $date,
                    'columns' => $columns,
                    'rows' => [],
                    'column_totals' => [],
                    'totals' => self::emptyAmounts(),
                ];
            }

            // Type, flavor, shape and layers are never silently combined.
            // Distinct due dates already live in distinct sections in plan mode.
            $rowId = json_encode([$type, $flavor, $shape, $layers], JSON_UNESCAPED_UNICODE);
            if (! isset($sections[$sectionId]['rows'][$rowId])) {
                $sections[$sectionId]['rows'][$rowId] = [
                    'type' => $type,
                    'flavor' => $flavor,
                    'shape' => $shape,
                    'layers' => $layers,
                    'needs_review' => false,
                    'cells' => [],
                    'totals' => self::emptyAmounts(),
                ];
            }

            $review = $flavor === 'غير محدد'
                || $shape === 'غير محدد'
                || ($layers === 'غير محدد' && (int) ($entry->showroom_quantity ?? 0) > 0);
            $sections[$sectionId]['rows'][$rowId]['needs_review'] =
                $sections[$sectionId]['rows'][$rowId]['needs_review'] || $review;

            $amounts = [
                'display' => (int) ($entry->display_quantity ?? 0),
                'reserved' => (int) ($entry->reserved_quantity ?? 0),
                'special' => (int) ($entry->special_quantity ?? 0),
                'total' => (int) ($entry->total_quantity ?? 0),
            ];

            // A distinct non-standard dimension is a real column, not
            // discarded or merged into an arbitrary default size.
            $sections[$sectionId]['columns'][$size] = $size;
            if (! isset($sections[$sectionId]['rows'][$rowId]['cells'][$size])) {
                $sections[$sectionId]['rows'][$rowId]['cells'][$size] = self::emptyAmounts();
            }
            if (! isset($sections[$sectionId]['column_totals'][$size])) {
                $sections[$sectionId]['column_totals'][$size] = self::emptyAmounts();
            }

            self::add($sections[$sectionId]['rows'][$rowId]['cells'][$size], $amounts);
            self::add($sections[$sectionId]['column_totals'][$size], $amounts);
            self::add($sections[$sectionId]['rows'][$rowId]['totals'], $amounts);
            self::add($sections[$sectionId]['totals'], $amounts);
            self::add($totals, $amounts);
        }

        $sections = array_values($sections);
        usort($sections, static fn ($a, $b) =>
            [$a['date'], array_search($a['kind'], array_keys(self::KINDS), true)]
            <=> [$b['date'], array_search($b['kind'], array_keys(self::KINDS), true)]
        );

        foreach ($sections as &$section) {
            if ($section['kind'] === 'slab') {
                // Preset paper-sheet dimensions first, additional real
                // dimensions afterward so no valid size disappears.
                $extras = array_diff_key($section['columns'], self::SLAB_SIZES);
                ksort($extras, SORT_NATURAL);
                $section['columns'] = self::SLAB_SIZES + $extras;
            } elseif ($section['kind'] === 'other') {
                ksort($section['columns'], SORT_NATURAL);
            }

            $section['rows'] = array_values($section['rows']);
            usort($section['rows'], static fn ($a, $b) =>
                [$a['type'], $a['flavor'], $a['shape'], $a['layers']]
                <=> [$b['type'], $b['flavor'], $b['shape'], $b['layers']]
            );
        }
        unset($section);

        return [
            'sections' => $sections,
            'totals' => $totals,
            'row_count' => $lineCount,
        ];
    }

    private static function emptyAmounts(): array
    {
        return ['display' => 0, 'reserved' => 0, 'special' => 0, 'total' => 0];
    }

    private static function add(array &$target, array $amounts): void
    {
        foreach ($amounts as $key => $amount) {
            $target[$key] += $amount;
        }
    }
}

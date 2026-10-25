<?php

namespace Tests\Unit;

use App\Support\CakeProductionMatrix;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CakeProductionMatrixTest extends TestCase
{
    private function cake(
        string $type, string $flavor, string $shape, string $size,
        ?int $layers, string $date, int $showroom, int $reserved, int $special
    ): object {
        return (object) [
            'cake_type' => $type,
            'detail_flavor' => $flavor,
            'shape' => $shape,
            'cake_size' => $size,
            'layer_count' => $layers,
            'first_needed_date' => $date,
            'showroom_quantity' => $showroom,
            'display_quantity' => $showroom - $reserved,
            'reserved_quantity' => $reserved,
            'special_quantity' => $special,
            'total_quantity' => $showroom + $special,
        ];
    }

    #[Test]
    public function regular_flavors_are_rows_and_big_medium_small_are_columns_with_exact_totals(): void
    {
        $matrix = CakeProductionMatrix::build([
            $this->cake('chocolate','nutella','round','large',3,'2026-10-25',5,2,1),
            $this->cake('chocolate','nutella','round','medium',3,'2026-10-25',4,0,0),
            $this->cake('chocolate','rocher','round','large',3,'2026-10-25',1,0,0),
            $this->cake('chocolate','nutella','round','small',2,'2026-10-25',2,0,0),
        ]);

        $this->assertCount(1, $matrix['sections']);
        $section = $matrix['sections'][0];
        $this->assertSame('regular', $section['kind']);
        $this->assertSame(['كبير','وسط','صغير'], array_keys($section['columns']));
        $this->assertCount(3, $section['rows']); // layers and flavors do not merge
        $this->assertSame(13, $section['totals']['total']);
        $this->assertSame(10, $section['totals']['display']);
        $this->assertSame(2, $section['totals']['reserved']);
        $this->assertSame(1, $section['totals']['special']);
        $this->assertSame(13, $matrix['totals']['total']);

        $sameRecipe = collect($section['rows'])->first(fn ($row) => $row['flavor'] === 'نوتيلا' && $row['layers'] === '3');
        $this->assertSame(6, $sameRecipe['cells']['كبير']['total']);
        $this->assertSame(4, $sameRecipe['cells']['وسط']['total']);
        $this->assertSame(10, $sameRecipe['totals']['total']);
    }

    #[Test]
    public function slab_preset_dimensions_and_extra_real_dimensions_are_retained(): void
    {
        $matrix = CakeProductionMatrix::build([
            $this->cake('chocolate','nutella','slab','40x30',3,'2026-10-25',2,0,0),
            $this->cake('chocolate','nutella','slab','30×30',3,'2026-10-25',3,1,0),
            $this->cake('chocolate','nutella','slab','40X60',3,'2026-10-25',0,0,2),
        ]);
        $section = $matrix['sections'][0];
        $this->assertSame('slab', $section['kind']);
        $this->assertSame(['30 × 30','40 × 30','40 × 40','40 × 60'], array_keys($section['columns']));
        $this->assertCount(1, $section['rows']);
        $this->assertSame(7, $section['totals']['total']);
        $this->assertSame(3, $section['column_totals']['30 × 30']['total']);
        $this->assertSame(2, $section['column_totals']['40 × 60']['special']);
        $this->assertSame(0, $section['column_totals']['40 × 40']['total'] ?? 0);
    }

    #[Test]
    public function plan_never_merges_identical_cakes_with_different_due_dates(): void
    {
        $first = $this->cake('vanilla','lotus','round','small',3,'2026-10-25',1,0,0);
        $second = $this->cake('vanilla','lotus','round','small',3,'2026-10-26 00:00:00',3,0,0);
        $matrix = CakeProductionMatrix::build([$second,$first], 'plan');
        $this->assertCount(2, $matrix['sections']);
        $this->assertSame(['2026-10-25','2026-10-26'],array_column($matrix['sections'],'date'));
        $this->assertSame([1,3],array_map(fn ($section) => $section['totals']['total'],$matrix['sections']));
    }

    #[Test]
    public function special_and_unknown_shapes_are_never_merged_with_round_cakes(): void
    {
        $matrix = CakeProductionMatrix::build([
            $this->cake('vanilla','lotus','round','large',3,'2026-10-25',2,0,0),
            $this->cake('vanilla','lotus','غير محدد','large',3,'2026-10-25',1,0,0),
            $this->cake('vanilla','lotus','heart_head','large',3,'2026-10-25',4,0,0),
            $this->cake('vanilla','', 'round','medium',null,'2026-10-25',2,0,0),
        ]);
        $this->assertCount(2,$matrix['sections']); // regular and special shape
        $this->assertCount(3,$matrix['sections'][0]['rows']);
        $this->assertSame(4,$matrix['sections'][1]['totals']['total']);
        $this->assertTrue(collect($matrix['sections'][0]['rows'])->contains('needs_review',true));
        $this->assertSame(9,$matrix['totals']['total']);
    }
}

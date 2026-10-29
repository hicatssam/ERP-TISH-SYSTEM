<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchCakeZeroQuantitySelectionTest extends TestCase
{
    #[Test]
    public function new_branch_cake_order_preserves_checked_sizes_with_zero_quantity(): void
    {
        $blade = file_get_contents(resource_path('views/sales/showroom-cake-requests/create.blade.php'));

        $this->assertStringNotContainsString(
            'if (showroom + reserved === 0) continue;',
            $blade
        );
        $this->assertStringContainsString(
            'if(!index){event.preventDefault();',
            $blade
        );
        $this->assertStringContainsString(
            'يمكن أن تكون كميته صفرًا',
            $blade
        );

        $this->assertStringContainsString("append('quantity',showroom+reserved);", $blade);
        $this->assertStringNotContainsString('scr-shape', $blade);
        $this->assertStringNotContainsString("append('shape',", $blade);
    }

    #[Test]
    public function summary_counts_all_checked_sizes_even_when_quantities_are_zero(): void
    {
        $blade = file_get_contents(resource_path('views/sales/showroom-cake-requests/create.blade.php'));

        $this->assertStringNotContainsString(
            'if (rowShowroom + rowReserved <= 0) return;',
            $blade
        );
        $this->assertStringContainsString('الأحجام المختارة:', $blade);
        $this->assertStringNotContainsString('scr-layer-count', $blade);
        $this->assertStringNotContainsString('scr-flavor', $blade);
    }
}

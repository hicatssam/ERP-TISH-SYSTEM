<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchCakeZeroQuantitySelectionTest extends TestCase
{
    #[Test]
    public function new_branch_cake_order_skips_checked_sizes_with_no_quantity(): void
    {
        $blade = file_get_contents(resource_path('views/sales/showroom-cake-requests/create.blade.php'));

        $this->assertStringContainsString(
            'if (showroom + reserved === 0) continue;',
            $blade
        );
        $this->assertStringContainsString(
            'if(!index){event.preventDefault();',
            $blade
        );
        $this->assertStringContainsString(
            'الأحجام التي كميتها صفر لا تُضاف للطلب',
            $blade
        );

        $skip = strpos($blade, 'if (showroom + reserved === 0) continue;');
        $createItem = strpos($blade, "append('product_id',row.dataset.productId);");
        $this->assertNotFalse($skip);
        $this->assertNotFalse($createItem);
        $this->assertLessThan($createItem, $skip, 'Skip zero-quantity rows before generating item inputs.');
    }

    #[Test]
    public function summary_counts_only_sizes_with_a_positive_requested_quantity(): void
    {
        $blade = file_get_contents(resource_path('views/sales/showroom-cake-requests/create.blade.php'));

        $this->assertStringContainsString(
            'if (rowShowroom + rowReserved <= 0) return;',
            $blade
        );
        $this->assertStringContainsString('الأحجام المطلوبة فعليًا:', $blade);
        $this->assertStringNotContainsString(
            'على الأقل قطعة واحدة لكل حجم',
            $blade
        );
    }
}

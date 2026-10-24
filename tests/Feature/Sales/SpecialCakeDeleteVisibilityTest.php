<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeDeleteVisibilityTest extends TestCase
{
    #[Test]
    public function delete_control_is_guarded_for_drafts_and_cancelled_orders_without_financial_links(): void
    {
        $view = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));

        $this->assertStringContainsString('CakeOrderStatus::Draft, \\App\\Enums\\CakeOrderStatus::Cancelled', $view);
        $this->assertStringContainsString('route(\'cake-orders.destroy\', $cakeOrder)', $view);
        $this->assertStringContainsString('route(\'cake-orders.archive\', $cakeOrder)', $view);
        $this->assertStringContainsString('$cakeOrder->payments()->exists()', $view);
        $this->assertStringContainsString('$cakeOrder->invoice()->exists()', $view);
        $this->assertStringContainsString('$cakeOrder->branchShipment()->exists()', $view);
        $this->assertStringContainsString('$cakeOrder->receivingIssues()->exists()', $view);
        $this->assertStringContainsString('lockForUpdate()', $controller);
        $this->assertStringContainsString('يمكن حذف الطلبات الملغاة أو المسودات فقط', $controller);
    }
}

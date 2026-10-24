<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CakeOrderActionsDeleteTest extends TestCase
{
    #[Test]
    public function list_actions_only_offer_delete_when_no_financial_or_delivery_records_exist(): void
    {
        $list = file_get_contents(resource_path('views/sales/cake-orders/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));

        $this->assertStringContainsString("['draft', 'cancelled']", $list);
        $this->assertStringContainsString("@can('delete', \$order)", $list);
        $this->assertStringContainsString("'cake-orders.destroy'", $list);
        $this->assertStringContainsString("'cake-orders.archive'", $list);
        $this->assertStringContainsString('$order->payments()->exists()', $list);
        $this->assertStringContainsString('$order->invoice()->exists()', $list);
        $this->assertStringContainsString('$order->branchShipment()->exists()', $list);
        $this->assertStringContainsString('$order->receivingIssues()->exists()', $list);
        $this->assertStringContainsString('$locked->payments()->exists()', $controller);
    }
}

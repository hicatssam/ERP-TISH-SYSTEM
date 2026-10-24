<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeAllowedTypesTest extends TestCase
{
    #[Test]
    public function special_cake_create_and_edit_only_offer_three_types(): void
    {
        $create = file_get_contents(resource_path('views/sales/cake-orders/create.blade.php'));
        $edit = file_get_contents(resource_path('views/sales/cake-orders/edit.blade.php'));

        foreach (['chocolate', 'red_velvet', 'vanilla'] as $allowed) {
            $this->assertStringContainsString($allowed, $create);
            $this->assertStringContainsString($allowed, $edit);
        }
        foreach (['caramel', 'fruit', 'other'] as $excluded) {
            $this->assertStringNotContainsString('value="'.$excluded.'"', $create);
            $this->assertStringNotContainsString("'".$excluded."'=>'", $edit);
        }
    }

    #[Test]
    public function both_special_cake_endpoints_validate_submitted_type_against_whitelist(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));
        $request = file_get_contents(app_path('Http/Requests/Sales/StoreSpecialCakeOrderRequest.php'));
        $this->assertSame(2, substr_count($controller, "Rule::in(['chocolate', 'red_velvet', 'vanilla'])"));
        $this->assertStringContainsString("'in:chocolate,red_velvet,vanilla'", $request);
    }
}

<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeCoveringAndHeartHeadTest extends TestCase
{
    #[Test]
    public function special_cake_forms_use_covering_choices_instead_of_person_count(): void
    {
        $create = file_get_contents(resource_path('views/sales/cake-orders/create.blade.php'));
        $edit = file_get_contents(resource_path('views/sales/cake-orders/edit.blade.php'));
        $show = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));

        foreach ([$create, $edit] as $html) {
            $this->assertStringContainsString('name="covering_type"', $html);
            $this->assertStringContainsString('value="cream"', $html);
            $this->assertStringContainsString('value="sugar"', $html);
            $this->assertStringContainsString('heart_head', $html);
            $this->assertStringNotContainsString('name="persons_count"', $html);
        }
        $this->assertStringContainsString("'covering_type' => 'نوع التغليف'", $show);
        $this->assertStringContainsString("'heart_head' => 'رأس قلب'", $show);
        $this->assertStringNotContainsString("'persons_count' => 'عدد الأشخاص'", $show);
    }

    #[Test]
    public function covering_and_heart_head_are_supported_in_validation_persistence_and_reporting_labels(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));
        $request = file_get_contents(app_path('Http/Requests/Sales/StoreSpecialCakeOrderRequest.php'));
        $service = file_get_contents(app_path('Services/SpecialCakes/SpecialCakeOrderService.php'));
        $labels = file_get_contents(app_path('Support/CakeProductionLabels.php'));

        $this->assertStringContainsString("'covering_type' => ['nullable', Rule::in(['cream', 'sugar'])]", $controller);
        $this->assertStringContainsString("'in:cream,sugar'", $request);
        $this->assertStringContainsString("'covering_type'         => \$data['covering_type'] ?? null", $service);
        $this->assertStringContainsString("'heart_head' => 'رأس قلب'", $labels);
        $this->assertStringContainsString("'round', 'standard_tiers', 'heart_head'", $controller);
    }
}

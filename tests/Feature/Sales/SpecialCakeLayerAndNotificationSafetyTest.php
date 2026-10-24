<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeLayerAndNotificationSafetyTest extends TestCase
{
    #[Test]
    public function wedding_tier_count_is_validated_persisted_and_displayed(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));
        $service = file_get_contents(app_path('Services/SpecialCakes/SpecialCakeOrderService.php'));
        $model = file_get_contents(app_path('Models/SpecialCakeOrder.php'));
        $create = file_get_contents(resource_path('views/sales/cake-orders/create.blade.php'));
        $edit = file_get_contents(resource_path('views/sales/cake-orders/edit.blade.php'));
        $show = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));

        $this->assertStringContainsString("'layers_count' => [", $controller);
        $this->assertStringContainsString("? [3, 4, 5, 6] : [1, 2, 3, 4, 5, 6]", $controller);
        $this->assertStringContainsString("'layers_count'           => \$data['layers_count'] ?? null", $service);
        $this->assertStringContainsString("'layers_count'        => 'integer'", $model);
        $this->assertStringContainsString('name="layers_count"', $create);
        $this->assertStringContainsString('name="layers_count"', $edit);
        $this->assertStringContainsString("'layers_count' => 'عدد الطبقات'", $show);
    }

    #[Test]
    public function failing_notification_does_not_convert_saved_order_to_http_500(): void
    {
        $service = file_get_contents(app_path('Services/SpecialCakes/SpecialCakeStatusTransitionService.php'));

        $this->assertStringContainsString("SpecialCakeOrderTransitioned::dispatch(", $service);
        $this->assertStringContainsString('catch (\\Throwable $exception)', $service);
        $this->assertStringContainsString('notification dispatch failed after order persistence', $service);
    }
}

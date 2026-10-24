<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeAllShapesLayerCountTest extends TestCase
{
    #[Test]
    public function all_shapes_keep_the_submitted_layers_and_wedding_tiers_remain_required(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SpecialCakeOrderController.php'));
        $create = file_get_contents(resource_path('views/sales/cake-orders/create.blade.php'));
        $edit = file_get_contents(resource_path('views/sales/cake-orders/edit.blade.php'));
        $show = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));

        $this->assertStringContainsString('Rule::in($request->input(\'shape\') === \'wedding_tiers\' ? [3, 4, 5, 6] : [1, 2, 3, 4, 5, 6])', $controller);
        $this->assertStringNotContainsString("\$validated['layers_count'] = null", $controller);
        $this->assertStringContainsString("layersGroup.hidden = false", $create);
        $this->assertStringContainsString("layers.required = isWedding", $create);
        $this->assertStringContainsString('name="layers_count"', $edit);
        $this->assertStringContainsString("'layers_count' => 'عدد الطبقات'", $show);
    }
}

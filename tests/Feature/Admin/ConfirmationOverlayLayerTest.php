<?php

namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfirmationOverlayLayerTest extends TestCase
{
    #[Test]
    public function global_confirmation_stacks_above_cake_order_status_modal(): void
    {
        $css = file_get_contents(public_path('assets/css/app.css'));
        $cake = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));

        preg_match('/\\.dahab-confirm-overlay\\s*\\{[^}]*z-index:\\s*(\\d+)/s', $css, $confirmMatch);
        preg_match('/\\.status-modal\\s*\\{[^}]*z-index:\\s*(\\d+)/s', $cake, $statusMatch);

        $this->assertNotEmpty($confirmMatch, 'Global confirmation must declare its stacking order.');
        $this->assertNotEmpty($statusMatch, 'Cake status dialog must declare its stacking order.');
        $this->assertGreaterThan((int) $statusMatch[1], (int) $confirmMatch[1]);
    }
}

<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CakeOrderPaymentProofDisplayTest extends TestCase
{
    #[Test]
    public function special_cake_details_use_protected_payment_proof_route(): void
    {
        $view = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));

        $this->assertStringContainsString("route('payments.proof', \$proofPayment)", $view);
        $this->assertStringContainsString("@can('view', \$proofPayment)", $view);
        $this->assertStringContainsString('alt="صورة إثبات الدفع"', $view);
        $this->assertStringContainsString("['jpg', 'jpeg', 'png', 'webp']", $view);
        $this->assertStringContainsString('عرض إثبات الدفع PDF', $view);
    }
}

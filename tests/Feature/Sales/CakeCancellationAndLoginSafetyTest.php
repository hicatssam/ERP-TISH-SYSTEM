<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CakeCancellationAndLoginSafetyTest extends TestCase
{
    #[Test]
    public function login_form_includes_csrf_and_duplicate_submission_protection(): void
    {
        $view = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString('id="dahabLoginForm"', $view);
        $this->assertStringContainsString("route('login.post')", $view);
        $this->assertStringContainsString('@csrf', $view);
        $this->assertStringContainsString("form.addEventListener('submit'", $view);
        $this->assertStringContainsString('event.preventDefault()', $view);
    }

    #[Test]
    public function cancelled_cake_notice_links_to_exact_financial_sources(): void
    {
        $view = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Finance/PaymentController.php'));

        $this->assertStringContainsString("'order_type' => 'special_cake_order'", $view);
        $this->assertStringContainsString("'order_id' => " . '$cakeOrder->id', $view);
        $this->assertStringContainsString("route('payments.bank-sales'", $view);
        $this->assertStringContainsString("route('invoices.show'", $view);
        $this->assertStringContainsString("where('order_id', " . '$request->integer(\'order_id\')' . ")", $controller);
    }

    #[Test]
    public function cancellation_screen_warns_that_refunds_are_not_automatic(): void
    {
        $view = file_get_contents(resource_path('views/sales/cake-orders/show.blade.php'));
        $service = file_get_contents(app_path('Services/SpecialCakes/SpecialCakeStatusTransitionService.php'));

        $this->assertStringContainsString('$cancelledHasFinancialRecords', $view);
        $this->assertStringContainsString('إلغاء الطلب لا يعني استرداد المبلغ المدفوع', $view);
        $this->assertStringContainsString("cancelled_at", $service);
    }
}

<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuCheckoutAuditTest extends TestCase
{
    #[Test]
    public function checkout_requires_a_configured_payment_account_before_submission(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/checkout/checkout.blade.php'));

        $this->assertStringContainsString('id="paymentAccountSelect" aria-required="true"', $view);
        $this->assertStringContainsString('configuredAccounts.length > 0', $view);
        $this->assertStringContainsString('اختر حساب الدفع أو التحويل قبل تأكيد الطلب.', $view);
        $this->assertStringContainsString('paymentProofPreviewUrl', $view);
        $this->assertStringContainsString('URL.revokeObjectURL(paymentProofPreviewUrl)', $view);
    }

    #[Test]
    public function product_variant_photos_reset_and_complete_the_image_skeleton(): void
    {
        $detail = file_get_contents(resource_path('views/customer-menu/product.blade.php'));
        $loader = file_get_contents(public_path('assets/js/customer-menu/tish-visuals.js'));

        $this->assertStringContainsString('tish:product-image-changed', $detail);
        $this->assertStringContainsString("document.addEventListener('tish:product-image-changed'", $loader);
        $this->assertStringContainsString('refreshImage(img, frame)', $loader);
        $this->assertStringContainsString("img.addEventListener('load'", $loader);
        $this->assertStringContainsString("img.addEventListener('error'", $loader);
        $this->assertStringContainsString("frame.classList.remove('tish-image-loading')", $loader);
    }
    #[Test]
    public function my_orders_uses_bounded_status_refresh_without_overlapping_batches(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/my-orders.blade.php'));
        $this->assertStringContainsString('refreshingStatuses', $view);
        $this->assertStringContainsString('Math.min(4,rows.length)', $view);
        $this->assertStringContainsString('setInterval(refreshStatuses,20000)', $view);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $view);
    }

}

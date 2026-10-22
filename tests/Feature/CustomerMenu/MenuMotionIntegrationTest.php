<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuMotionIntegrationTest extends TestCase
{
    #[Test]
    public function intro_uses_existing_admin_settings_and_uploaded_brand_logo(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $this->assertStringContainsString("customer_menu_intro_mode", $view);
        $this->assertStringContainsString("customer_menu_intro_show_logo", $view);
        $this->assertStringContainsString("\$branding['logo']", $view);
        $this->assertStringContainsString('cmIntroReveal', $view);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $view);
    }

    #[Test]
    public function catalog_motion_does_not_replace_product_or_cart_logic(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $catalog = file_get_contents(resource_path('views/customer-menu/products.blade.php'));
        $this->assertStringContainsString('IntersectionObserver', $home);
        $this->assertStringContainsString('IntersectionObserver', $catalog);
        $this->assertStringContainsString('CM.addToCart', $home);
        $this->assertStringContainsString('CM.addToCart', $catalog);
        $this->assertStringContainsString('revealMenuCards()', $catalog);
    }
}

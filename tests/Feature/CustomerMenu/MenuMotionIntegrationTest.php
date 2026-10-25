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
    #[Test]
    public function mobile_cart_summary_and_banner_motion_follow_accessibility_preferences(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $navigation = file_get_contents(resource_path('views/customer-menu/partials/bottom-nav.blade.php'));
        $cart = file_get_contents(resource_path('views/customer-menu/partials/cart-engine.blade.php'));
        $styles = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));

        $this->assertStringContainsString('cmStickyCartCount', $navigation);
        $this->assertStringContainsString('cmStickyCartTotal', $navigation);
        $this->assertStringContainsString('stickyCart.hidden = c === 0', $cart);
        $this->assertStringContainsString('customer-menu:cart-changed', $cart);
        $this->assertStringContainsString('reducedMotion.matches || document.hidden || hovered || focused', $home);
        $this->assertStringContainsString("addEventListener('visibilitychange'", $home);
        $this->assertStringContainsString('.cm-sticky-cart:not([hidden])', $styles);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $styles);
        $this->assertStringContainsString('.cm-sticky-cart.cm-cart-pulse:not([hidden])', $styles);
        $this->assertStringContainsString('previousSnapshot !== snapshot', $cart);
    }

}

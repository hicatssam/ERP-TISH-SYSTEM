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
        $sharedCard = file_get_contents(public_path('assets/js/customer-menu/tish-product-circle-card-v8.js'));
        $paperMotion = file_get_contents(public_path('assets/js/customer-menu/tish-paper-motion-v10.js'));
        $layout = file_get_contents(public_path('assets/css/customer-menu/tish-product-layout-v10.css'));
        $motionCss = file_get_contents(public_path('assets/css/customer-menu/tish-paper-motion-v10.css'));

        // Actual shared card supplies all product actions in both views.
        $this->assertStringContainsString('TishProductCircleCard.render', $home);
        $this->assertStringContainsString('TishProductCircleCard.render', $catalog);
        $this->assertStringContainsString('TishProductCircleCard.addFromButton', $home);
        $this->assertStringContainsString('TishProductCircleCard.addFromButton', $catalog);
        $this->assertStringContainsString('cm.addToCart(product.id, 1)', $sharedCard);
        $this->assertStringContainsString('data-fav=', $sharedCard);
        $this->assertStringContainsString('TishPaperMotion?.observe', $home);
        $this->assertStringContainsString('TishPaperMotion?.observe', $catalog);
        $this->assertStringContainsString('IntersectionObserver', $paperMotion);
        $this->assertStringContainsString('threshold: .15', $paperMotion);
        $this->assertStringContainsString('repeat(2,minmax(0,1fr))', $layout);
        $this->assertStringContainsString('cm-sticky-cart:not([hidden])::before', $layout);
        $this->assertStringContainsString('--paper-card-duration:480ms', $motionCss);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $motionCss);
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

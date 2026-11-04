<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CircularMenuDesignTest extends TestCase
{
    #[Test]
    public function all_customer_menu_pages_share_a_brand_aware_circular_stylesheet(): void
    {
        $shared = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));
        $orders = file_get_contents(resource_path('views/customer-menu/my-orders.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-circular-ui.css'));

        $this->assertStringContainsString('tish-circular-ui.css', $shared);
        $this->assertStringContainsString('tish-circular-ui.css', $orders);
        $this->assertStringContainsString('class="cm-my-orders"', $orders);
        $this->assertStringContainsString('var(--primary)', $css);
        $this->assertStringContainsString('var(--on-primary)', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
        $this->assertStringContainsString('focus-visible', $css);
        $this->assertStringContainsString('.cart-row', $css);
        $this->assertStringContainsString('.payment-card', $css);
        $this->assertStringContainsString('.cat', $css);
        $this->assertStringContainsString('.orderIcon', $css);
    }

    #[Test]
    public function install_button_is_accessible_and_shows_text_inside_a_round_pill(): void
    {
        $install = file_get_contents(resource_path('views/customer-menu/partials/pwa-install.blade.php'));
        $shared = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-circular-ui.css'));

        $this->assertStringContainsString('id="pwaInstallBtn" aria-label="ثبّت التطبيق"', $install);
        $this->assertStringContainsString('tish-circular-ui.css', $shared);
        $this->assertStringContainsString('v=3', $shared);
        $this->assertStringContainsString('body.crisp-customer-menu #pwaInstallBtn', $css);
        $this->assertStringContainsString('border-radius:50%!important', $css);
        $this->assertStringContainsString('cm-has-sticky-cart #pwaInstallBtn', $css);
        $this->assertStringContainsString('#pwaInstallBtn span{', $css);
        $this->assertStringContainsString('position:static!important', $css);
        $this->assertStringContainsString('border-radius:999px!important', $css);
        $this->assertStringContainsString('#pwaInstallBtn[hidden]{display:none!important}', $css);
    }

    #[Test]
    public function product_addons_are_only_branch_catalog_items_and_respect_choice_requirements(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CustomerOrdering/CustomerMenuController.php'));
        $product = file_get_contents(resource_path('views/customer-menu/product.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-circular-ui.css'));

        $this->assertStringContainsString("'addOnItems' => \$addOnItems", $controller);
        $this->assertStringContainsString("->filter(fn (array \$row): bool", $controller);
        $this->assertStringContainsString("('available'", $controller);
        $this->assertStringContainsString('tish-complete-meal', $product);
        $this->assertStringContainsString('data-addon-id', $product);
        $this->assertStringContainsString('CM.addToCart(item.id, 1)', $product);
        $this->assertStringContainsString('item.requiresChoices || item.isVariantProduct', $product);
        $this->assertStringContainsString('tish-addon-card', $css);
    }

    #[Test]
    public function cart_checkout_and_order_tracking_actions_remain_connected(): void
    {
        $cart = file_get_contents(resource_path('views/customer-menu/cart/cart.blade.php'));
        $checkout = file_get_contents(resource_path('views/customer-menu/checkout/checkout.blade.php'));
        $orders = file_get_contents(resource_path('views/customer-menu/my-orders.blade.php'));

        $this->assertStringContainsString('CM.cartRows()', $cart);
        $this->assertStringContainsString('CM.cartTotal()', $cart);
        $this->assertStringContainsString('checkoutStart', $cart);
        $this->assertStringContainsString('checkoutForm', $checkout);
        $this->assertStringContainsString('paymentMethods', $checkout);
        $this->assertStringContainsString('customer_menu_orders', $orders);
        $this->assertStringContainsString('refreshStatuses()', $orders);
    }
}

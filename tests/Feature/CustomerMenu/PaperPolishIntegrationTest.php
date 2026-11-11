<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaperPolishIntegrationTest extends TestCase
{
    #[Test]
    public function empty_cart_uses_a_large_branded_paper_bag_without_changing_checkout(): void
    {
        $cart = file_get_contents(resource_path('views/customer-menu/cart/cart.blade.php'));
        $styles = file_get_contents(public_path('assets/css/customer-menu/tish-paper-polish-v13.css'));

        $this->assertStringContainsString('tish-cart-bag-art', $cart);
        $this->assertStringContainsString('kraft-cart-bag.webp', $cart);
        $this->assertStringContainsString("id=\"cartTotal\"", $cart);
        $this->assertStringContainsString('CM.cartTotal()', $cart);
        $this->assertStringContainsString("customer-menu.checkout", $cart);
        $this->assertStringContainsString('width:240px!important', $styles);
        $this->assertStringContainsString('tish-paper-bigbag-settle', $styles);
    }

    #[Test]
    public function native_install_remains_intact_and_pin_uses_full_paper_fold(): void
    {
        $pwa = file_get_contents(resource_path('views/customer-menu/partials/pwa-install.blade.php'));
        $js = file_get_contents(public_path('assets/js/customer-menu/tish-paper-controls-v12.js'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-paper-polish-v13.css'));

        $this->assertStringContainsString("btn.addEventListener('click', async function", $pwa);
        $this->assertStringContainsString('await prompt.prompt()', $pwa);
        $this->assertStringContainsString("press(pin, 'paper-pin-fold', 800)", $js);
        $this->assertStringContainsString('@keyframes tish-paper-complete-fold', $css);
        $this->assertStringContainsString('scale:.04 .08', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }

    #[Test]
    public function orders_keep_real_local_order_links_and_show_paper_details(): void
    {
        $orders = file_get_contents(resource_path('views/customer-menu/my-orders.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-paper-polish-v13.css'));

        $this->assertStringContainsString("const ordersKey='customer_menu_orders'", $orders);
        $this->assertStringContainsString('refreshStatuses()', $orders);
        $this->assertStringContainsString('localUrl(item.url)', $orders);
        $this->assertStringContainsString('data-order-index=', $orders);
        $this->assertStringContainsString('orderStatus', $orders);
        $this->assertStringContainsString('tish-paper-page-arrow', $orders);
        $this->assertStringContainsString('body.tish-paper.cm-my-orders', $css);
        $this->assertStringContainsString('availability-green.webp', $css);
        $this->assertStringContainsString('background.webp', $css);
    }

    #[Test]
    public function all_admin_banners_share_an_outer_paper_frame_without_replacing_images(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/tish-paper-polish-v13.css'));

        $this->assertStringContainsString('class="tish-paper-banner-frame"', $home);
        $this->assertStringContainsString('id="bannerTrack"', $home);
        $this->assertStringContainsString("{{ \$banner['image'] }}", $home);
        $this->assertStringContainsString("{{ \$banner['link_url'] }}", $home);
        $this->assertStringContainsString('tish-paper-top-ads', $home);
        $this->assertStringContainsString('category-ivory.webp', $css);
        $this->assertStringContainsString('banner-dots', $css);
        $this->assertStringContainsString('clip-path:polygon(', $css);
        $this->assertStringContainsString('nav-charcoal.webp', $css);
    }
}

<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FloatingShowcaseIntegrationTest extends TestCase
{
    #[Test]
    public function homepage_uses_branding_logo_in_a_non_interactive_floating_background(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $brand = file_get_contents(resource_path('views/customer-menu/partials/brand-watermark.blade.php'));
        $css = file_get_contents(public_path('assets/css/customer-menu/floating-showcase.css'));

        $this->assertStringContainsString('floatingBrandingEnabled = true', $home);
        $this->assertStringContainsString("\$branding['logo']", $brand);
        $this->assertStringContainsString('cm-brand-echo-left', $brand);
        $this->assertStringContainsString('cm-brand-echo-right', $brand);
        $this->assertStringContainsString('pointer-events:none', $css);
        $this->assertStringContainsString('cmBrandHover', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }

    #[Test]
    public function carousel_keeps_real_product_and_cart_links_and_works_with_controls_or_swipe(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $script = file_get_contents(public_path('assets/js/customer-menu/featured-carousel.js'));
        $style = file_get_contents(public_path('assets/css/customer-menu/floating-showcase.css'));

        $this->assertStringContainsString('id="featuredShowcase"', $home);
        $this->assertStringContainsString('data-showcase-prev', $home);
        $this->assertStringContainsString('data-showcase-next', $home);
        $this->assertStringContainsString('id="featuredDots"', $home);
        $this->assertStringContainsString('data-showcase-slide', $home);
        $this->assertStringContainsString("renderCard(product) + '</div>'", $home);
        $this->assertStringContainsString('TishProductCircleCard.addFromButton', $home);
        $this->assertStringContainsString('CustomerMenuFeaturedCarousel?.refresh()', $home);

        $this->assertStringContainsString('touchstart', $script);
        $this->assertStringContainsString('touchend', $script);
        $this->assertStringContainsString('ArrowRight', $script);
        $this->assertStringContainsString('document.hidden', $script);
        $this->assertStringContainsString('reduced.matches', $script);
        $this->assertStringContainsString('cm-showcase-slide[data-offset="0"]', $style);
    }
}

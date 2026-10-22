<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandWatermarkIntegrationTest extends TestCase
{
    #[Test]
    public function watermark_uses_configured_logo_and_does_not_intercept_interactions(): void
    {
        $watermark = file_get_contents(resource_path('views/customer-menu/partials/brand-watermark.blade.php'));
        $css = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $menu = file_get_contents(resource_path('views/customer-menu/products.blade.php'));

        $this->assertStringContainsString("\$branding['logo']", $watermark);
        $this->assertStringContainsString("customer-menu.partials.brand-watermark", $home);
        $this->assertStringContainsString("customer-menu.partials.brand-watermark", $menu);
        $this->assertStringContainsString('pointer-events:none', $css);
        $this->assertStringContainsString('cmWatermarkDrift', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }
}

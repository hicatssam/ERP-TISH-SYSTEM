<?php

namespace Tests\Feature\CustomerMenu;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallCompatibilityTest extends TestCase
{
    #[Test]
    public function customer_manifest_and_early_prompt_capture_are_present(): void
    {
        $head = file_get_contents(resource_path('views/customer-menu/partials/pwa-head.blade.php'));
        $this->assertStringContainsString("route('customer-menu.manifest')", $head);
        $this->assertStringContainsString("route('pwa.icon', ['size' => 180])", $head);
        $this->assertStringContainsString('beforeinstallprompt', $head);
        $this->assertStringContainsString('dahabCustomerPwa.prompt = event', $head);
    }

    #[Test]
    public function install_button_has_cross_browser_fallback_and_correct_hidden_style(): void
    {
        $install = file_get_contents(resource_path('views/customer-menu/partials/pwa-install.blade.php'));
        $styles = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));

        $this->assertStringContainsString('state.prompt', $install);
        $this->assertStringContainsString('openHelp()', $install);
        $this->assertStringContainsString('isSamsung', $install);
        $this->assertStringContainsString('isFirefox', $install);
        $this->assertStringContainsString('isIos', $install);
        $this->assertStringContainsString('isSafari', $install);
        $this->assertStringContainsString('navigator.serviceWorker.register', $install);
        $this->assertStringContainsString('.pwa-install-btn[hidden],.pwa-ios-sheet[hidden]{display:none!important}', $styles);
    }
}

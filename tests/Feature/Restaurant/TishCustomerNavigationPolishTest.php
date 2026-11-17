<?php

namespace Tests\Feature\Restaurant;

use Tests\TestCase;

class TishCustomerNavigationPolishTest extends TestCase
{
    public function test_public_menu_stylesheets_are_registered_and_physically_present(): void
    {
        $shared = file_get_contents(resource_path('views/customer-menu/partials/styles.blade.php'));
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));

        $this->assertStringContainsString('tish-nav-motion-v1.css', $shared);
        $this->assertStringContainsString('tish-menu-responsive-polish-v1.css', $home);
        $this->assertFileExists(public_path('assets/css/customer-menu/tish-nav-motion-v1.css'));
        $this->assertFileExists(public_path('assets/css/customer-menu/tish-menu-responsive-polish-v1.css'));
    }

    public function test_navigation_keeps_real_links_active_marker_and_reduced_motion_support(): void
    {
        $nav = file_get_contents(resource_path('views/customer-menu/partials/bottom-nav.blade.php'));
        $motion = file_get_contents(public_path('assets/css/customer-menu/tish-nav-motion-v1.css'));

        $this->assertStringContainsString("route('customer-menu.show'", $nav);
        $this->assertStringContainsString("route('customer-menu.cart'", $nav);
        $this->assertStringContainsString("route('customer-menu.my-orders'", $nav);
        $this->assertStringContainsString('aria-current', $nav);
        $this->assertStringContainsString('pointerdown', $nav);
        $this->assertStringContainsString('tish-nav-ripple', $motion);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $motion);
        $this->assertStringNotContainsString('preventDefault()', $nav);
    }

    public function test_home_keeps_admin_hero_and_search_category_cart_hooks(): void
    {
        $home = file_get_contents(resource_path('views/customer-menu/show.blade.php'));
        $nav = file_get_contents(resource_path('views/customer-menu/partials/bottom-nav.blade.php'));

        $this->assertStringContainsString("asset('assets/tish-paper/brand/tish-drive-hero.png')", $home);
        $this->assertStringContainsString('id="searchInput"', $home);
        $this->assertStringContainsString('id="categories"', $home);
        $this->assertStringContainsString('id="openFullMenu"', $home);
        $this->assertStringContainsString('id="cmStickyCart"', $nav);
        $this->assertStringContainsString('id="cartBadgeNav"', $nav);
    }
}

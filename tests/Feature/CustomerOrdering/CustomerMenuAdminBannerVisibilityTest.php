<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Banner;
use App\Models\Location;
use App\Models\MenuBanner;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerMenuAdminBannerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_menu_includes_both_admin_and_restaurant_banners_for_correct_branches(): void
    {
        Storage::fake('public');

        foreach (['banners/general.jpg', 'banners/local.jpg', 'banners/elsewhere.jpg', 'banners/inactive.jpg',
            'banners/future.jpg', 'banners/expired.jpg', 'menu-banners/restaurant.jpg'] as $path) {
            Storage::disk('public')->put($path, 'synthetic jpeg bytes');
        }

        $branch = $this->branch('MENU-BANNER-A');
        $another = $this->branch('MENU-BANNER-B');

        Banner::query()->create([
            'title' => 'عرض عام لجميع الفروع',
            'image' => 'banners/general.jpg',
            'location_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Banner::query()->create([
            'title' => 'عرض خاص بالفرع',
            'image' => 'banners/local.jpg',
            'location_id' => $branch->id,
            'kicker' => 'جديد',
            'cta_text' => 'شاهد العرض',
            'cta_link' => '#products',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Banner::query()->create([
            'title' => 'فرع آخر فقط',
            'image' => 'banners/elsewhere.jpg',
            'location_id' => $another->id,
            'is_active' => true,
        ]);

        Banner::query()->create([
            'title' => 'إعلان غير فعال',
            'image' => 'banners/inactive.jpg',
            'location_id' => $branch->id,
            'is_active' => false,
        ]);

        Banner::query()->create([
            'title' => 'إعلان مستقبلي',
            'image' => 'banners/future.jpg',
            'location_id' => $branch->id,
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        Banner::query()->create([
            'title' => 'إعلان منتهي',
            'image' => 'banners/expired.jpg',
            'location_id' => $branch->id,
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);

        MenuBanner::query()->create([
            'title' => 'بانر من قسم المطعم',
            'image' => 'menu-banners/restaurant.jpg',
            'location_id' => $branch->id,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $response = $this->get(route('customer-menu.show', $branch->code))
            ->assertOk()
            ->assertSee('عرض عام لجميع الفروع')
            ->assertSee('عرض خاص بالفرع')
            ->assertSee('بانر من قسم المطعم')
            ->assertSee('شاهد العرض')
            ->assertDontSee('فرع آخر فقط')
            ->assertDontSee('إعلان غير فعال')
            ->assertDontSee('إعلان مستقبلي')
            ->assertDontSee('إعلان منتهي');

        $banners = $response->viewData('banners');
        $this->assertSame([
            'عرض عام لجميع الفروع',
            'عرض خاص بالفرع',
            'بانر من قسم المطعم',
        ], $banners->pluck('title')->all());
        $this->assertSame('جديد', $banners[1]['badge_text']);
        $this->assertSame('شاهد العرض', $banners[1]['cta_text']);
        $this->assertSame('#products', $banners[1]['link_url']);
        $this->assertSame(
            route('customer-menu.assets.show', ['path' => 'banners/local.jpg'], false),
            $banners[1]['image']
        );

        $this->get(route('customer-menu.assets.show', ['path' => 'banners/local.jpg']))
            ->assertOk();

        $otherPage = $this->get(route('customer-menu.show', $another->code))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['عرض عام لجميع الفروع', 'فرع آخر فقط'],
            $otherPage->viewData('banners')->pluck('title')->all()
        );
    }

    #[Test]
    public function menu_hide_hero_setting_still_hides_all_promotional_banners(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/hidden.jpg', 'fake');
        $branch = $this->branch('MENU-BANNER-HIDDEN');
        Banner::query()->create([
            'title' => 'عرض مخفي بالإعدادات',
            'image' => 'banners/hidden.jpg',
            'is_active' => true,
        ]);
        SystemSetting::set('customer_menu_show_hero', false);

        $this->get(route('customer-menu.show', $branch->code))
            ->assertOk()
            ->assertDontSee('id="bannerTrack"', false);
    }

    private function branch(string $code): Location
    {
        return Location::query()->create([
            'name' => 'فرع '.$code,
            'code' => $code,
            'type' => 'branch',
            'is_active' => true,
        ]);
    }
}

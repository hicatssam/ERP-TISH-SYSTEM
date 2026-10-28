<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Banner;
use App\Models\Location;
use App\Models\MenuBanner;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
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
    public function admin_banner_upload_is_saved_in_banners_and_appears_only_in_the_selected_branch_menu(): void
    {
        Storage::fake('public');
        $branch = $this->branch('ADMIN-BANNER-A');
        $another = $this->branch('ADMIN-BANNER-B');
        $admin = User::factory()->create();
        $admin->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));

        $this->actingAs($admin)->get(route('banners.index'))
            ->assertOk()
            ->assertSee('إعلانات المنيو')
            ->assertSee('+ إضافة إعلان');

        $this->actingAs($admin)->post(route('banners.store'), [
            'location_id' => $branch->id,
            'title' => 'عرض افتتاح الموسم',
            'kicker' => 'حصري',
            'subtitle' => 'خصم على الحلويات',
            'cta_text' => 'شاهد الأصناف',
            'cta_link' => '#products',
            'sort_order' => 2,
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('offer.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $banner = Banner::query()->sole();
        $this->actingAs($admin)->get(route('banners.index'))
            ->assertOk()->assertSee('معاينة المنيو')
            ->assertSee(route('customer-menu.show', $branch->code));
        $this->assertSame('عرض افتتاح الموسم', $banner->title);
        $this->assertStringStartsWith('banners/', $banner->image);
        Storage::disk('public')->assertExists($banner->image);

        $this->get(route('customer-menu.show', $branch->code))
            ->assertOk()
            ->assertSee('عرض افتتاح الموسم')
            ->assertSee('خصم على الحلويات')
            ->assertSee('شاهد الأصناف')
            ->assertSee(route('customer-menu.assets.show', ['path' => $banner->image], false));

        $this->get(route('customer-menu.assets.show', ['path' => $banner->image]))
            ->assertOk();

        $this->get(route('customer-menu.show', $another->code))
            ->assertOk()
            ->assertDontSee('عرض افتتاح الموسم');

        $this->actingAs($admin)->put(route('banners.update', $banner), [
            'location_id' => $branch->id,
            'title' => 'عرض محدّث',
            'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->get(route('customer-menu.show', $branch->code))
            ->assertOk()->assertDontSee('عرض محدّث');
    }

    #[Test]
    public function banner_creation_requires_an_image_and_is_protected_by_settings_permission(): void
    {
        $branch = $this->branch('BANNER-PERMISSIONS-A');
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        $this->actingAs($user)->get(route('banners.index'))->assertForbidden();
        $this->actingAs($user)->post(route('banners.store'), [
            'title' => 'إعلان بدون صلاحية',
        ])->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        $this->actingAs($user)->post(route('banners.store'), [
            'title' => 'إعلان دون صورة',
            'is_active' => 1,
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('banners', 0);
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

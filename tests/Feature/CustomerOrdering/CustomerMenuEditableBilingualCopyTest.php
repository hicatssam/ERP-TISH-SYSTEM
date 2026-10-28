<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerMenuEditableBilingualCopyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_edit_both_languages_in_one_settings_panel_and_see_them_on_public_menu(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع النصر', 'code' => 'COPY-EN-01',
            'type' => 'branch', 'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $admin->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));

        $settings = $this->actingAs($admin)->get(route('settings.index'))->assertOk()
            ->assertSee('نصوص واجهة الطلب — عربي / English')
            ->assertSee('معاينة صفحة الطلب');

        foreach ([
            'customer_menu_title', 'customer_menu_title_en',
            'customer_menu_subtitle', 'customer_menu_subtitle_en',
            'customer_menu_search_placeholder_ar', 'customer_menu_search_placeholder_en',
        ] as $field) {
            $this->assertSame(1, substr_count($settings->getContent(), 'name="'.$field.'"'), $field);
        }

        $this->actingAs($admin)->post(route('settings.update'), [
            'active_settings_group' => 'customer_menu',
            'customer_menu_title' => 'اطلبها الآن',
            'customer_menu_title_en' => 'Order right here',
            'customer_menu_subtitle' => 'اختار طلبك من دهب',
            'customer_menu_subtitle_en' => 'Pick your favorites from Dahab',
            'customer_menu_search_placeholder_ar' => 'ابحث عن حلاوتك...',
            'customer_menu_search_placeholder_en' => 'Find sweets you love...',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Order right here', SystemSetting::get('customer_menu_title_en'));
        $this->assertSame('ابحث عن حلاوتك...', SystemSetting::get('customer_menu_search_placeholder_ar'));

        $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'ar']))
            ->assertOk()
            ->assertSee('اطلبها الآن')
            ->assertSee('اختار طلبك من دهب')
            ->assertSee('placeholder="ابحث عن حلاوتك..."', false);

        $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Order right here')
            ->assertSee('Pick your favorites from Dahab')
            ->assertSee('placeholder="Find sweets you love..."', false);

        $this->get(route('customer-menu.products', [$branch->code, 'lang' => 'ar']))
            ->assertOk()->assertSee('placeholder="ابحث عن حلاوتك..."', false);

        $this->get(route('customer-menu.products', [$branch->code, 'lang' => 'en']))
            ->assertOk()->assertSee('placeholder="Find sweets you love..."', false);
    }

    #[Test]
    public function existing_arabic_copy_is_not_replaced_by_the_migration_and_legacy_english_fallback_remains_readable(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع الاختبار', 'code' => 'COPY-LEGACY',
            'type' => 'branch', 'is_active' => true,
        ]);

        SystemSetting::set('customer_menu_title', 'اطلب مباشرة');
        SystemSetting::set('customer_menu_subtitle', 'اختر طلبك وسنجهزه لك');
        SystemSetting::set('customer_menu_title_en', '');
        SystemSetting::set('customer_menu_subtitle_en', '');

        (require database_path('migrations/2026_10_28_170000_add_bilingual_customer_menu_home_and_search_text.php'))->up();

        $this->assertSame('اطلب مباشرة', SystemSetting::get('customer_menu_title'));
        $this->assertSame('اختر طلبك وسنجهزه لك', SystemSetting::get('customer_menu_subtitle'));

        $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Order directly')
            ->assertSee("Choose your order and we'll prepare it for you")
            ->assertSee('placeholder="Search your favorite items..."', false);
    }

    #[Test]
    public function settings_reject_overlong_translations(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع الإعدادات', 'code' => 'COPY-VALIDATION',
            'type' => 'branch', 'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $admin->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));

        $this->actingAs($admin)->post(route('settings.update'), [
            'active_settings_group' => 'customer_menu',
            'customer_menu_search_placeholder_en' => str_repeat('x', 151),
        ])->assertSessionHasErrors('customer_menu_search_placeholder_en');
    }
}

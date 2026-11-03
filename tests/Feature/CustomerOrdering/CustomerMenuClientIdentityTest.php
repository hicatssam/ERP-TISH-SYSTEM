<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerMenuClientIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Location
    {
        return Location::create(['code'=>'IDENTITY', 'name'=>'فرع الهوية', 'type'=>'branch', 'is_active'=>true]);
    }

    private function admin(Location $branch): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary'=>true]);
        $user->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        return $user;
    }

    public function test_header_upload_identity_and_removal_work_independently_of_promotion_banners(): void
    {
        Storage::fake('public');
        $branch = $this->branch();
        $admin = $this->admin($branch);
        $this->actingAs($admin)->post(route('settings.update'), [
            'customer_menu_header_image'=>UploadedFile::fake()->image('header.png'),
            'brand_logo'=>UploadedFile::fake()->image('logo.png'),
            'brand_tagline_ar'=>'شعار العميل الخاص',
            'customer_menu_header_height'=>360,
            'customer_menu_header_overlay'=>20,
            'customer_menu_header_position'=>'left',
            'customer_menu_category_visual'=>'icons',
            'customer_menu_use_client_colors'=>1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $path = SystemSetting::get('customer_menu_header_image');
        Storage::disk('public')->assertExists(substr($path, 8));
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertSee('reference-masthead-image')->assertSee('شعار العميل الخاص')
            ->assertViewHas('theme', fn ($theme) => $theme['header_height'] === 360
                && $theme['header_position'] === 'left' && !empty($theme['header_image']))
            ->assertViewHas('banners', fn ($banners) => $banners->isEmpty())
            ->assertViewHas('branding', fn ($branding) => !empty($branding['logo']));
        $this->actingAs($admin)->post(route('settings.update'), [
            'remove_customer_menu_header_image'=>1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($path, 8));
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertDontSee('class="reference-masthead-image"', false)
            ->assertSee('شعار العميل الخاص');
    }

    public function test_every_public_menu_page_tracks_client_colors_and_can_opt_into_independent_colors(): void
    {
        $branch = $this->branch();
        SystemSetting::set('theme_primary', '#123456');
        SystemSetting::set('theme_accent', '#345678');
        SystemSetting::set('theme_background', '#F0F4F8');
        SystemSetting::set('customer_menu_primary_color', '#AA2233');
        SystemSetting::set('customer_menu_use_client_colors', '1');
        foreach (['customer-menu.show','customer-menu.products','customer-menu.favorites','customer-menu.cart'] as $route) {
            $this->get(route($route, $branch->code))->assertOk()
                ->assertViewHas('theme', fn ($theme) => $theme['primary'] === '#123456'
                    && $theme['accent'] === '#345678' && $theme['background'] === '#F0F4F8');
        }
        SystemSetting::set('theme_primary', '#225544');
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertViewHas('theme', fn ($theme) => $theme['primary'] === '#225544');
        SystemSetting::set('theme_primary', '#FFF199');
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertViewHas('theme', fn ($theme) => $theme['on_primary'] === '#000000');
        SystemSetting::set('customer_menu_use_client_colors', '0');
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertViewHas('theme', fn ($theme) => $theme['primary'] === '#AA2233');
    }

    public function test_invalid_header_settings_and_files_do_not_replace_the_current_image(): void
    {
        $branch = $this->branch();
        SystemSetting::set('customer_menu_header_image', 'images/existing-header.webp');
        $this->actingAs($this->admin($branch))->post(route('settings.update'), [
            'customer_menu_header_height'=>9999,
            'customer_menu_header_overlay'=>100,
            'customer_menu_header_position'=>'url(invalid)',
            'customer_menu_header_image'=>UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors([
            'customer_menu_header_height', 'customer_menu_header_overlay',
            'customer_menu_header_position', 'customer_menu_header_image',
        ]);
        $this->assertSame('images/existing-header.webp', SystemSetting::get('customer_menu_header_image'));
    }
}

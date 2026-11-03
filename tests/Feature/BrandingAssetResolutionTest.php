<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PublicImageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BrandingAssetResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $branch = Location::create(['code'=>'BRAND', 'name'=>'Brand', 'type'=>'branch', 'is_active'=>true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary'=>true]);
        $user->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        return $user;
    }

    private function image(string $path): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('brand.png')->getContent());
    }

    public function test_completed_onboarding_uses_the_public_asset_route_for_stored_logo(): void
    {
        Storage::fake('public');
        $this->image('branding/client.png');
        SystemSetting::set('brand_logo', 'storage/branding/client.png');
        SystemSetting::set('client_onboarding_completed', '1');
        SystemSetting::set('system_name', 'Client identity');
        $this->actingAs($this->admin())->get(route('onboarding.index'))->assertOk()
            ->assertSee('src="'.PublicImageUrl::url('storage/branding/client.png').'"', false)
            ->assertDontSee('src="'.asset('storage/branding/client.png').'"', false);
        $this->get(PublicImageUrl::url('storage/branding/client.png'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_browser_icon_follows_explicit_favicon_or_current_logo_without_gd_conversion(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->image('branding/logo-one.png');
        SystemSetting::set('brand_logo', 'storage/branding/logo-one.png');
        $this->image('branding/older-small.png');
        SystemSetting::set('brand_logo_small', 'storage/branding/older-small.png');
        SystemSetting::set('brand_favicon', '');
        $this->actingAs($admin)->get(route('profile.show'))->assertOk()
            ->assertSee('<link rel="icon" href="'.PublicImageUrl::url('storage/branding/logo-one.png').'">', false);
        $this->image('branding/logo-two.png');
        SystemSetting::set('brand_logo', 'storage/branding/logo-two.png');
        $this->get(route('profile.show'))->assertOk()
            ->assertSee('<link rel="icon" href="'.PublicImageUrl::url('storage/branding/logo-two.png').'">', false);
        $this->image('branding/favicon.png');
        SystemSetting::set('brand_favicon', 'storage/branding/favicon.png');
        $this->get(route('profile.show'))->assertOk()
            ->assertSee('<link rel="icon" href="'.PublicImageUrl::url('storage/branding/favicon.png').'">', false);
        $this->get(route('customer-menu.show', 'BRAND'))->assertOk()
            ->assertViewHas('branding', fn ($branding) => $branding['favicon'] === PublicImageUrl::url('storage/branding/favicon.png'));
    }

    public function test_pwa_icons_resample_public_disk_branding_and_prioritize_supported_favicon(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required for PNG resampling.'); }
        Storage::fake('public');
        foreach (['logo'=>[220,20,20], 'favicon'=>[20,40,220]] as $name=>$rgb) {
            $image = imagecreatetruecolor(20,20);
            imagefill($image,0,0,imagecolorallocate($image,...$rgb));
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put('branding/'.$name.'.png',$bytes);
        }
        SystemSetting::set('brand_logo', 'storage/branding/logo.png');
        SystemSetting::set('brand_logo_small', '');
        SystemSetting::set('brand_favicon', '');
        $first = $this->get(route('pwa.icon',32))->assertOk();
        $firstTag = $first->headers->get('ETag');
        $png = imagecreatefrompng($first->baseResponse->getFile()->getPathname());
        $pixel = imagecolorat($png,16,16);
        imagedestroy($png);
        $this->assertSame(220, ($pixel >> 16) & 255);

        SystemSetting::set('brand_favicon', 'storage/branding/favicon.png');
        $second = $this->get(route('pwa.icon',32))->assertOk();
        $this->assertNotSame($firstTag,$second->headers->get('ETag'));
        $png = imagecreatefrompng($second->baseResponse->getFile()->getPathname());
        $pixel = imagecolorat($png,16,16);
        imagedestroy($png);
        $this->assertSame(220, $pixel & 255);
    }

    public function test_local_brand_resolver_rejects_remote_and_unsafe_paths(): void
    {
        Storage::fake('public');
        $this->image('branding/safe.png');
        $this->assertSame(Storage::disk('public')->path('branding/safe.png'), PublicImageUrl::localPath('storage\branding\safe.png'));
        foreach (['../.env', 'branding/../../.env', "branding/a\0.png", 'https://example.com/logo.png', '//example.com/logo.png'] as $path) {
            $this->assertNull(PublicImageUrl::localPath($path));
        }
    }
}

<?php

namespace Tests\Feature\Restaurant;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PosCustomerScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_is_branch_scoped_and_settings_need_permission(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $a = Location::create(['name'=>'Screen A','code'=>'SCREENA','type'=>'branch','is_active'=>true]);
        $b = Location::create(['name'=>'Screen B','code'=>'SCREENB','type'=>'branch','is_active'=>true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($a->id, ['is_primary'=>true]);
        foreach (['restaurant.view', 'restaurant_pos.use'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($user)->get(route('restaurant.pos.customer-screen', ['location_id'=>$a->id]))
            ->assertOk()->assertSee('TishCustomerScreenConfig');
        $this->getJson(route('restaurant.pos.customer-screen.playlist', ['location_id'=>$a->id]))
            ->assertOk()->assertJsonPath('ad_percent', 60);
        $this->getJson(route('restaurant.pos.customer-screen.playlist', ['location_id'=>$b->id]))
            ->assertForbidden();
        $settings = route('restaurant.pos.customer-screen.update', ['location_id'=>$a->id]);
        $payload = ['action'=>'save','image_seconds'=>10,'thanks_seconds'=>15,'ad_percent'=>50];
        $this->postJson($settings, $payload)->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        $this->post($settings, $payload)->assertRedirect();
        $this->getJson(route('restaurant.pos.customer-screen.playlist', ['location_id'=>$a->id]))
            ->assertOk()->assertJsonPath('ad_percent', 50)->assertJsonPath('thanks_seconds',15);

        Storage::fake('public');
        $mediaId = (string) Str::uuid();
        $path = 'pos-display/'.$a->id.'/demo.png';
        Storage::disk('public')->put($path, 'test media');
        SystemSetting::set('pos_customer_screen_v1_'.$a->id, json_encode([
            'media'=>[['id'=>$mediaId,'path'=>$path,'type'=>'image']]
        ]));
        $url = route('restaurant.pos.customer-screen.media',['mediaId'=>$mediaId,'location_id'=>$a->id]);
        $this->get($url)->assertOk();
        $this->post($settings,['action'=>'remove','media_id'=>$mediaId])->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->get($url)->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }
}

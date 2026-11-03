<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActivityModules;
use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BusinessProfileService;
use App\Services\ClientOnboardingService;
use App\Services\ModuleService;
use App\Support\ActivityModules;
use App\Support\ReportCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityModuleSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        app(DatabaseSeeder::class)->seedArchitectureRegistryOnly();
        $this->actor = User::factory()->create();
        $this->actor->assignRole(Role::findOrCreate('Admin', 'web'));
    }

    private function applyActivity(string $profile, array $codes): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($profile, $codes) {
            app(BusinessProfileService::class)->setCurrent(
                BusinessProfile::where('code', $profile)->firstOrFail(), $this->actor
            );
            SystemSetting::set('activity_modules_scoped', '1');
            return app(ModuleService::class)->applySelection($profile, $codes, $this->actor);
        });
    }

    public function test_restaurant_choices_include_production_and_exclude_bakery(): void
    {
        $codes = app(ModuleService::class)->availableForProfile('restaurant')->pluck('code')->all();
        foreach (['inventory', 'recipes', 'production', 'restaurant_pos'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertNotContains('bakery', $codes);
        $this->assertNotContains('cake_orders', $codes);
    }

    public function test_production_enables_dependencies_and_unselected_units_are_disabled(): void
    {
        $this->applyActivity('restaurant', ['production']);
        $modules = app(ModuleService::class);
        foreach (['restaurant', 'production', 'recipes', 'inventory'] as $code) {
            $this->assertTrue($modules->isEnabled($code), $code);
        }
        foreach (['bakery', 'cake_orders', 'restaurant_tables', 'kds', 'purchasing'] as $code) {
            $this->assertFalse($modules->isEnabled($code), $code);
            $this->assertDatabaseHas('modules', ['code' => $code, 'is_active' => false]);
        }
        $this->assertDatabaseHas('business_profiles', ['code' => 'bakery_sweets']);
    }

    public function test_inventory_and_production_can_be_omitted_for_restaurant(): void
    {
        $this->applyActivity('restaurant', ['restaurant_pos']);
        $this->assertFalse(app(ModuleService::class)->isEnabled('inventory'));
        $this->assertFalse(app(ModuleService::class)->isEnabled('production'));
        $this->assertTrue(app(ModuleService::class)->isEnabled('restaurant_pos'));
    }

    public function test_switching_back_to_bakery_disables_restaurant_dependents(): void
    {
        $this->applyActivity('restaurant', ['restaurant_pos', 'restaurant_tables', 'kds', 'production']);
        $this->applyActivity('bakery_sweets', ['cake_orders', 'production']);
        $modules = app(ModuleService::class);
        $this->assertTrue($modules->isEnabled('cake_orders'));
        $this->assertTrue($modules->isEnabled('production'));
        foreach (['restaurant', 'restaurant_pos', 'restaurant_tables', 'kitchen', 'kds'] as $code) {
            $this->assertFalse($modules->isEnabled($code), $code);
        }
    }

    public function test_foreign_module_cannot_be_selected_or_enabled_manually(): void
    {
        $this->applyActivity('restaurant', []);
        $this->expectException(ValidationException::class);
        app(ModuleService::class)->setEnabled('cake_orders', true, $this->actor);
    }

    public function test_forged_wizard_selection_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ModuleService::class)->selection('restaurant', ['cake_orders']);
    }

    public function test_admin_permissions_and_direct_reports_respect_disabled_modules(): void
    {
        $this->applyActivity('restaurant', []);
        $this->actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('cake_orders.view', 'web'));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('cake_orders.view'));
        $this->assertFalse($this->actor->can('cake_orders.view'));
        $this->assertFalse($this->actor->can('inventory.view'));
        $this->assertTrue($this->actor->can('restaurant.view'));
        $request = Request::create('/reports/cake-production');
        $route = new Route('GET', 'reports/{type}', fn () => response('leaked'));
        $route->name('reports.show');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EnsureActivityModules::class)->handle($request, fn () => response('leaked'));
    }

    public function test_report_catalog_hides_foreign_reports_for_admin(): void
    {
        $this->applyActivity('restaurant', ['reports']);
        $catalog = app(ReportCatalog::class);
        $this->assertFalse($catalog->allowed($this->actor, $catalog->native()['showroom-sweets']));
        $this->actingAs($this->actor);
        $entries = collect($catalog->groups())->flatten(1);
        $this->assertFalse($entries->contains('type', 'cake-orders'));
        $this->assertFalse($entries->contains('type', 'cake-production'));
    }

    public function test_sidebar_hides_bakery_reports_and_links_for_restaurant(): void
    {
        $this->applyActivity('restaurant', ['reports']);
        $this->actingAs($this->actor);
        $html = view('layouts.app')->render();
        $this->assertStringNotContainsString('href="'.route('cake-orders.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('showroom-daily-reports.index').'"', $html);
    }
    public function test_notifications_for_disabled_modules_are_hidden_without_deleting_them(): void
    {
        $this->applyActivity('restaurant', []);
        $this->actor->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'data' => ['type' => 'cake_orders.created', 'url' => url('/cake-orders/1'), 'title' => 'Cake'],
        ]);
        $this->actor->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'data' => ['type' => 'general', 'title' => 'General'],
        ]);
        $this->assertSame(1, \App\Support\NotificationVisibility::query($this->actor->notifications())->count());
        $this->assertSame(2, $this->actor->notifications()->count());
    }

    public function test_role_editor_only_offers_permissions_of_enabled_modules(): void
    {
        $this->applyActivity('restaurant', []);
        \Spatie\Permission\Models\Permission::findOrCreate('cake_orders.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('restaurant.view', 'web');
        $view = app(\App\Http\Controllers\Admin\RoleController::class)->create();
        $names = $view->getData()['permissions']->flatten(2)->pluck('name');
        $this->assertContains('restaurant.view', $names);
        $this->assertNotContains('cake_orders.view', $names);
    }

    public function test_dashboard_contains_only_selected_activity_sections(): void
    {
        $this->applyActivity('restaurant', ['restaurant_pos']);
        $this->actingAs($this->actor);
        $view = app(\App\Http\Controllers\Admin\DashboardController::class)->index();
        $html = $view->render();
        $this->assertStringNotContainsString('href="'.route('cake-orders.create').'"', $html);
        $this->assertStringNotContainsString('href="'.route('production.orders.index').'"', $html);
    }

}

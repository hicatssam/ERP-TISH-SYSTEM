<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\SystemSetting;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\LocationPaymentMethod;
use App\Services\ModuleService;
use App\Support\BusinessProfileRegistry;
use App\Support\ModuleRegistry;
use App\Support\PermissionUi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BurgerRestaurantDemoSeeder::class);
    }

    public function seedArchitectureRegistryOnly(): void
    {
        $this->seedBusinessProfilesAndModules();
    }

    public function seedRestaurantFoundationOnly(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('بيانات المطعم التجريبية غير مسموحة في production.');
        }
        $routePermissions = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn ($middleware) => trim(explode(',', substr($middleware, 4))[0]))
            ->filter(fn ($name) => str_contains($name, '.'));
        foreach ($routePermissions->merge(array_keys(PermissionUi::permissionLabels()))->filter()->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate('Admin', 'web')->syncPermissions(Permission::where('guard_name', 'web')->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedCurrencies();
        $this->seedPaymentMethods();
        $this->seedBusinessProfilesAndModules();
    }

    private function seedCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) return;

        foreach ([
            ['code' => 'ILS', 'name' => 'Israeli New Shekel', 'name_ar' => 'شيكل إسرائيلي جديد', 'symbol' => '₪', 'is_base' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'name_ar' => 'دولار أمريكي', 'symbol' => '$', 'is_base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'name_ar' => 'يورو', 'symbol' => '€', 'is_base' => false],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'name_ar' => 'جنيه مصري', 'symbol' => 'E£', 'is_base' => false],
        ] as $currency) {
            $this->upsertExisting('currencies', ['code' => $currency['code']], $currency + [
                'decimal_places' => 2,
                'is_active' => true,
            ]);
        }

        // There must be exactly one deterministic base currency.
        DB::table('currencies')->where('code', '!=', 'ILS')->update(['is_base' => false]);
        DB::table('currencies')->where('code', 'ILS')->update(['is_base' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    // ORDERS
    // ─────────────────────────────────────────────────────────────

    private function seedPaymentMethods(): void
    {
        $methods = [
            ['name' => 'Cash',              'name_ar' => 'نقداً',              'code' => 'CASH',              'type' => 'cash',              'requires_verification' => false, 'requires_reference' => false, 'sort_order' => 1],
            ['name' => 'Bank Transfer',     'name_ar' => 'تحويل بنكي',        'code' => 'BANK',              'type' => 'bank_transfer',     'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 2],
            ['name' => 'PayBox',            'name_ar' => 'باي بوكس',          'code' => 'PAYBOX',            'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 3],
            ['name' => 'Card POS',          'name_ar' => 'بطاقة ائتمانية',    'code' => 'CARD',              'type' => 'card_pos',          'requires_verification' => false, 'requires_reference' => false, 'sort_order' => 4],
            ['name' => 'Pal Pay',           'name_ar' => 'بال باي',           'code' => 'pal_pay',           'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 5],
            ['name' => 'Bank of Palestine', 'name_ar' => 'بنك فلسطين',        'code' => 'bank_of_palestine', 'type' => 'bank_transfer',     'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 6],
            ['name' => 'Jawwal Pay',        'name_ar' => 'جوال باي',          'code' => 'jawwal_pay',        'type' => 'electronic_wallet', 'requires_verification' => true,  'requires_reference' => true,  'sort_order' => 7],
        ];

        $locations = Location::all();
        foreach ($methods as $m) {
            $method = PaymentMethod::firstOrCreate(['code' => $m['code']], array_merge($m, ['is_active' => true]));
            foreach ($locations as $location) {
                LocationPaymentMethod::firstOrCreate(
                    ['location_id' => $location->id, 'payment_method_id' => $method->id],
                    ['is_active' => true]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SYSTEM SETTINGS
    // ─────────────────────────────────────────────────────────────

    private function seedBusinessProfilesAndModules(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('business_profiles')) return;

        $modules = [];
        foreach (ModuleRegistry::modules() as $definition) {
            $module = Module::query()->firstOrNew(['code' => $definition['code']]);
            $isNew = ! $module->exists;
            $module->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'icon' => $definition['icon'],
                'route_prefix' => $definition['route_prefix'],
                'is_core' => $definition['is_core'],
                'is_system' => $definition['is_system'],
                'sort_order' => $definition['sort_order'],
                'configuration' => [
                    'implemented' => (bool) $definition['implemented'],
                    'registered_by' => 'DatabaseSeeder',
                ],
            ]);
            if ($isNew) {
                $module->is_active = $definition['code'] === 'chat'
                    ? (bool) SystemSetting::get('chat_enabled', true)
                    : (bool) ($definition['initial_active'] ?? $definition['implemented']);
            }
            $module->save();
            $modules[$module->code] = $module;
        }

        if (Schema::hasTable('module_dependencies')) {
            foreach (ModuleRegistry::dependencies() as $code => $requiredCodes) {
                if (! isset($modules[$code])) continue;
                $modules[$code]->dependencies()->sync(
                    collect($requiredCodes)->map(fn ($required) => $modules[$required]->id ?? null)->filter()->all()
                );
            }
        }

        foreach (BusinessProfileRegistry::profiles() as $definition) {
            $profile = BusinessProfile::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $definition['sort_order'],
                    'configuration' => ['registered_by' => 'DatabaseSeeder'],
                ]
            );
            if (Schema::hasTable('business_profile_modules')) {
                $pivot = [];
                foreach (array_values(array_unique($definition['modules'])) as $index => $code) {
                    if (! isset($modules[$code])) continue;
                    $pivot[$modules[$code]->id] = [
                        'is_required' => (bool) $modules[$code]->is_system,
                        'is_default' => true,
                        'sort_order' => ($index + 1) * 10,
                    ];
                }
                $profile->modules()->sync($pivot);
            }
        }

        if (Schema::hasTable('module_bundles') && Schema::hasTable('module_bundle_modules')) {
            foreach (BusinessProfileRegistry::bundles() as $definition) {
                $profileId = $definition['profile']
                    ? BusinessProfile::query()->where('code', $definition['profile'])->value('id')
                    : null;
                $bundle = ModuleBundle::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    [
                        'business_profile_id' => $profileId,
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_active' => true,
                        'sort_order' => $definition['sort_order'],
                        'configuration' => ['registered_by' => 'DatabaseSeeder'],
                    ]
                );
                $bundlePivot = [];
                foreach ($definition['modules'] as $index => $code) {
                    if (isset($modules[$code])) {
                        $bundlePivot[$modules[$code]->id] = ['sort_order' => ($index + 1) * 10];
                    }
                }
                $bundle->modules()->sync($bundlePivot);
            }
        }

        SystemSetting::query()->firstOrCreate(
            ['key' => 'business_profile_code'],
            [
                'value' => 'bakery_sweets', 'type' => 'string', 'group' => 'business',
                'label' => 'نوع النشاط', 'description' => 'ملف النشاط الحالي للنظام.',
            ]
        );
        SystemSetting::query()->firstOrCreate(
            ['key' => 'client_onboarding_completed'],
            [
                'value' => '0', 'type' => 'boolean', 'group' => 'onboarding',
                'label' => 'اكتمل إعداد العميل', 'description' => 'حالة معالج إعداد العميل.',
            ]
        );
        SystemSetting::flushCache();
        Cache::forget('business-profile:current:v1');
        app(ModuleService::class)->invalidate();
    }

    // ─────────────────────────────────────────────────────────────
    // ONE FINAL, DETERMINISTIC PERMISSION MATRIX
    // ─────────────────────────────────────────────────────────────

    private function upsertExisting(string $table, array $unique, array $values): void
    {
        if (! Schema::hasTable($table)) return;

        $columns = array_flip(Schema::getColumnListing($table));
        $unique = array_intersect_key($unique, $columns);
        $values = array_intersect_key($values, $columns);
        if (isset($columns['created_at']) && ! array_key_exists('created_at', $values)) $values['created_at'] = now();
        if (isset($columns['updated_at'])) $values['updated_at'] = now();
        if ($unique !== []) DB::table($table)->updateOrInsert($unique, $values);
    }

}

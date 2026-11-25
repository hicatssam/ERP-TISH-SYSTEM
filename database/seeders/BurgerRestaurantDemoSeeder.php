<?php

namespace Database\Seeders;

use App\Enums\MovementReason;
use App\Models\BusinessProfile;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\AccountingJournal;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantArea;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BusinessProfileService;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\KitchenTicketService;
use App\Services\ModuleService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use RuntimeException;

/**
 * Restaurant-only development fixture. On the local demo database:
 * php artisan migrate:fresh --seed
 *
 * Never silently clears a populated database. Existing sweets/legacy fixtures
 * are rejected; migrate:fresh is an explicit, separate operator action.
 */
class BurgerRestaurantDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('تهيئة مطعم البرجر تجريبية وممنوعة في production.');
        }
        if (Product::withTrashed()->where('sku', 'not like', 'BRG-%')->exists()
            || Location::withTrashed()->whereNotIn('code', ['B01'])->exists()
            || Location::query()->where('name', 'like', '%دهب%')->exists()
            || DB::table('special_cake_orders')->exists()) {
            throw new RuntimeException('توجد بيانات قديمة. استخدم migrate:fresh --seed على قاعدة البيانات التجريبية فقط.');
        }

        DB::transaction(function (): void {
            Location::firstOrCreate(['code' => 'B01'], [
                'name' => 'Tish — الفرع الرئيسي', 'type' => 'branch', 'is_active' => true,
            ]);

            app(DatabaseSeeder::class)->seedRestaurantFoundationOnly();
            $admin = $this->seedStaff();
            $this->settings();
            app(BusinessProfileService::class)->setCurrent(
                BusinessProfile::where('code', 'restaurant')->firstOrFail(), $admin
            );
            SystemSetting::set('activity_modules_scoped', '1');
            $modules = app(ModuleService::class);
            $modules->applySelection('restaurant', $modules->availableForProfile('restaurant')->pluck('code')->all(), $admin);
            SystemSetting::set('client_onboarding_completed', '1');
            SystemSetting::set('client_onboarding_completed_at', now()->toIso8601String());

            $this->call(BurgerRestaurantSeeder::class);
            $raw = $this->seedIngredients();
            $this->seedRecipes($raw, $admin);
            $this->requireRecipesForDemoMenu();
            // Only dine-in and takeaway are supported in this Tish demo.
            RestaurantMenuItem::query()->whereHas('product', fn ($q) => $q->where('sku', 'like', 'BRG-%'))
                ->update(['show_in_delivery' => false]);
            $this->openingStock($raw, $admin);
            $this->seedRestaurantOperations($admin);
            $this->seedOrders($admin);
            $this->seedBankVerificationScenario($admin);
            $this->assertDemoInventoryAndRecipes();
            $this->assertFinanceFixtureIntegrity();
        });

        $this->command?->info('تم تجهيز Tish بالكامل: منيو برجر مصور، وصفات، مواد خام، مخزون، مطبخ، طاولات وطلبات مترابطة.');
        $this->command?->warn('بيانات تجريبية فقط. المستخدم admin وكلمة المرور TishDemo@2026!');
    }

    private function seedStaff(): User
    {
        $branch = Location::where('code', 'B01')->firstOrFail();
        $roles = [
            'Branch Manager' => ['restaurant.', 'restaurant_', 'orders.', 'customers.', 'reports.', 'inventory.', 'stock_', 'employees.view', 'payments.', 'invoices.'],
            'Cashier' => ['restaurant.pos.', 'restaurant_pos.', 'restaurant.view', 'restaurant_menu.view', 'orders.view', 'orders.create', 'customers.', 'payments.record', 'invoices.', 'cash_sessions.'],
            'Chef' => ['kitchen.', 'kds.', 'recipes.', 'production.', 'inventory.view'],
            'Inventory Manager' => ['inventory.', 'stock_', 'branch_shipments.', 'products.', 'suppliers.', 'purchase_', 'goods_', 'procurement.'],
            'Accountant' => ['financial.', 'accounting.', 'payments.', 'invoices.', 'reports.', 'cash_sessions.', 'expenses.'],
        ];
        foreach ($roles as $name => $prefixes) {
            $permissions = Permission::where('guard_name', 'web')->get()->filter(fn ($p) =>
                collect($prefixes)->contains(fn ($prefix) => str_starts_with($p->name, $prefix))
            );
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
        foreach ([
            ['admin', 'مدير نظام Tish', 'Admin', $branch],
            ['branch_b01', 'مدير الفرع الرئيسي', 'Branch Manager', $branch],
            ['cashier_b01', 'كاشير الفرع الرئيسي', 'Cashier', $branch],
            ['chef_b01', 'شيف البرجر', 'Chef', $branch],
            ['inventory', 'مسؤول مخزن Tish', 'Inventory Manager', $branch],
            ['accountant', 'محاسب Tish', 'Accountant', $branch],
        ] as [$username, $name, $role, $location]) {
            $employee = Employee::firstOrCreate(['employee_number' => 'BRG-EMP-'.$username], [
                'full_name' => $name, 'employment_status' => 'active',
                'job_title' => $role, 'hire_date' => now()->subMonths(3)->toDateString(),
            ]);
            $employee->locations()->syncWithoutDetaching([$location->id => ['is_primary' => true]]);
            $user = User::firstOrCreate(['username' => $username], [
                'employee_id' => $employee->id, 'email' => $username.'@tish.example.test',
                'password' => Hash::make('TishDemo@2026!'), 'is_active' => true,
                'must_change_password' => false,
            ]);
            $user->syncRoles([$role]);
        }
        return User::where('username', 'admin')->firstOrFail();
    }

    private function settings(): void
    {
        $settings = [
            'system_name' => 'مطعم Tish', 'system_name_en' => 'Tish British Burger',
            'business_legal_name' => 'Tish British Burger', 'brand_footer_text' => 'Tish British Burger',
            'brand_tagline_ar' => 'برجر على طريقة لندن', 'brand_tagline_en' => 'London Calling. Burger Waiting.',
            'timezone' => 'Asia/Gaza', 'order_number_prefix' => 'TSH', 'invoice_prefix' => 'TISH',
            'stock_request_prefix' => 'TSR', 'print_primary_color' => '#8B1238',
            'print_footer_text' => 'Tish British Burger', 'invoice_footer_ar' => 'شكراً لاختياركم Tish',
            'theme_primary' => '#8B1238', 'theme_secondary' => '#C72847', 'theme_accent' => '#B71945',
            'theme_background' => '#FFF7F9', 'theme_surface' => '#FFFFFF', 'theme_text' => '#301923',
            'theme_text_muted' => '#796870', 'theme_border' => '#EED9E0', 'theme_sidebar_bg' => '#8B1238',
            'theme_sidebar_text' => '#FFFFFF', 'theme_sidebar_active' => '#FFD9E3',
            'theme_header_bg' => '#FFFFFF', 'theme_font_family' => 'Cairo', 'system_theme_preset' => 'custom',
            'customer_menu_primary_color' => '#8B1238', 'customer_menu_accent_color' => '#B71945',
            'customer_menu_background_color' => '#FBF7FA', 'customer_menu_title' => 'أهلاً بك في Tish',
            'customer_menu_subtitle' => 'برجر وفرايز ومشروبات… على ذوقك',
            'customer_menu_enabled' => '1', 'customer_menu_show_hero' => '1',
            'customer_menu_show_categories' => '1', 'customer_menu_show_featured' => '1',
            'customer_menu_show_descriptions' => '1', 'customer_menu_show_search' => '1',
            'customer_menu_allow_dine_in' => '1', 'customer_menu_allow_takeaway' => '1',
            'customer_menu_allow_delivery' => '0', 'kitchen_require_served_before_complete' => '1',
        ];
        foreach ($settings as $key => $value) {
            SystemSetting::set($key, $value);
        }
        // No legacy logo assets or payment-account details are imported.
        foreach (['brand_logo', 'brand_logo_small', 'brand_report_logo', 'brand_favicon', 'print_logo', 'brand_stamp', 'brand_signature'] as $key) {
            SystemSetting::set($key, '');
        }
    }

    public static function ingredients(): array
    {
        return [
            'BUN' => ['خبز برجر', 'piece', 1.2], 'MINI-BUN' => ['خبز ميني برجر', 'piece', .6],
            'BEEF' => ['لحم بقري مفروم', 'kg', 40], 'CHICKEN' => ['فيليه دجاج', 'kg', 24],
            'WINGS' => ['أجنحة دجاج خام', 'kg', 18], 'VEGGIE' => ['أقراص خضار نباتية', 'piece', 3],
            'FALAFEL' => ['أقراص فلافل', 'piece', .5], 'MUSHROOM' => ['فطر طازج', 'kg', 20],
            'CHEDDAR' => ['جبنة شيدر', 'kg', 28], 'SWISS' => ['جبنة سويس', 'kg', 38],
            'BLUE' => ['بلو تشيز', 'kg', 40], 'MOZZARELLA' => ['جبنة موزاريلا', 'kg', 28],
            'TURKEY' => ['تركي مدخن', 'kg', 35], 'LETTUCE' => ['خس', 'kg', 5],
            'TOMATO' => ['طماطم', 'kg', 4], 'ONION' => ['بصل', 'kg', 3],
            'PICKLE' => ['مخلل خيار', 'kg', 8], 'JALAPENO' => ['هالبينو', 'kg', 18],
            'SAUCE' => ['صوص برجر', 'kg', 12], 'BBQ' => ['صوص باربكيو', 'kg', 14],
            'RANCH' => ['صوص رانش', 'kg', 14], 'BUFFALO' => ['صوص بافلو', 'kg', 14],
            'HONEY' => ['صوص هاني مسترد', 'kg', 16], 'TRUFFLE' => ['صوص ترافل', 'kg', 40],
            'POTATO' => ['بطاطا', 'kg', 4], 'SWEET-POTATO' => ['بطاطا حلوة', 'kg', 6],
            'OIL' => ['زيت قلي', 'liter', 9], 'SEASONING' => ['توابل وملح', 'kg', 12],
            'FLOUR' => ['طحين تغليف', 'kg', 3], 'CRUMBS' => ['بقسماط', 'kg', 7],
            'NACHOS' => ['رقائق ناتشوز', 'kg', 16], 'CABBAGE' => ['ملفوف', 'kg', 3],
            'CARROT' => ['جزر', 'kg', 4], 'MAYO' => ['مايونيز', 'kg', 10], 'GARLIC' => ['صوص ثوم', 'kg', 12],
            'COLA' => ['كولا خام للمشروب', 'liter', 3], 'COLA-ZERO' => ['كولا زيرو خام للمشروب', 'liter', 3],
            'LEMON-SODA' => ['مشروب ليمون غازي', 'liter', 3], 'ORANGE-SODA' => ['مشروب برتقال غازي', 'liter', 3],
            'WATER' => ['مياه للشرب', 'liter', .5], 'SPARKLING' => ['مياه غازية خام', 'liter', 2],
            'ORANGE' => ['برتقال', 'kg', 5], 'LEMON' => ['ليمون', 'kg', 6],
            'MINT' => ['نعنع', 'kg', 10], 'MANGO' => ['بيوريه مانجو', 'kg', 14],
            'STRAWBERRY' => ['فراولة', 'kg', 12], 'TEA' => ['مركز شاي خوخ', 'liter', 12],
            'MILK' => ['حليب', 'liter', 5], 'ICECREAM' => ['آيس كريم فانيلا', 'kg', 18],
            'VANILLA' => ['فانيلا', 'liter', 30], 'CHOCOLATE' => ['صوص شوكولاتة للميلك شيك', 'kg', 20],
            'OREO' => ['فتات أوريو', 'kg', 18], 'LOTUS' => ['كريمة لوتس', 'kg', 25],
        ];
    }

    public function seedIngredients()
    {
        $category = Category::firstOrCreate(['slug' => 'burger-ingredients'], [
            'name' => 'Burger Ingredients', 'name_ar' => 'مواد خام للمطعم', 'is_active' => true, 'sort_order' => 900,
        ]);
        foreach (self::ingredients() as $code => [$name, $unit, $cost]) {
            Product::firstOrCreate(['sku' => 'BRG-ING-'.$code], [
                'barcode' => 'BRG-ING-'.$code, 'category_id' => $category->id,
                'name' => $name, 'name_ar' => $name, 'unit' => $unit,
                'product_type' => 'standard', 'base_selling_price' => 0,
                'is_active' => true, 'is_raw_material' => true,
                'tracks_batch' => false, 'tracks_expiry' => false,
            ]);
        }
        return Product::where('is_raw_material', true)->where('sku', 'like', 'BRG-ING-%')->get()
            ->keyBy(fn ($p) => substr($p->sku, strlen('BRG-ING-')));
    }

    private function recipeFor(string $group, string $code): array
    {
        if (in_array($group, ['beef', 'chicken', 'vegetarian'], true)) {
            $mini = $code === 'SLIDERS';
            $items = [$mini ? 'MINI-BUN' : 'BUN' => $mini ? 3 : 1,
                'LETTUCE' => .02, 'TOMATO' => .03, 'PICKLE' => .015, 'SAUCE' => .025];
            if ($group === 'beef') {
                $items['BEEF'] = match ($code) { 'TRIPLE' => .45, 'DOUBLE', 'DOUBLE-SMASH' => .30, 'SLIDERS' => .18, default => .15 };
                $items['CHEDDAR'] = $code === 'TRIPLE' ? .06 : .03;
            } elseif ($group === 'chicken') {
                $items['CHICKEN'] = $code === 'DOUBLE' ? .36 : .18;
                if (in_array($code, ['CRISPY', 'SPICY', 'ZINGER', 'BUFFALO'], true)) {
                    $items += ['CRUMBS' => .035, 'OIL' => .015];
                }
            } else {
                $items += match ($code) { 'FALAFEL' => ['FALAFEL' => 3], 'MUSHROOM' => ['MUSHROOM' => .16], default => ['VEGGIE' => 1] };
            }
            foreach (['MUSHROOM' => 'MUSHROOM', 'BBQ' => 'BBQ', 'BUFFALO' => 'BUFFALO', 'RANCH' => 'RANCH', 'HONEY' => 'HONEY',
                'JALAPENO' => 'JALAPENO', 'TURKEY-BACON' => 'TURKEY', 'BLUE-CHEESE' => 'BLUE', 'TRUFFLE' => 'TRUFFLE',
                'MOZZARELLA' => 'MOZZARELLA', 'ONION' => 'ONION'] as $variant => $ingredient) {
                if ($code === $variant) { $items[$ingredient] = .035; }
            }
            return $items;
        }
        if ($group === 'fries') {
            $items = [$code === 'SWEET' ? 'SWEET-POTATO' : 'POTATO' => match ($code) {
                'SMALL' => .15, 'LARGE' => .35, default => .25,
            }, 'OIL' => .02, 'SEASONING' => .003];
            if (in_array($code, ['CHEESE', 'LOADED', 'CHICKEN'], true)) { $items['CHEDDAR'] = .05; }
            if ($code === 'LOADED') { $items['BEEF'] = .08; }
            if ($code === 'CHICKEN') { $items['CHICKEN'] = .08; }
            if ($code === 'TRUFFLE') { $items['TRUFFLE'] = .025; }
            return $items;
        }
        if ($group === 'appetizers') {
            return match ($code) {
                'ONION' => ['ONION' => .15, 'FLOUR' => .04, 'OIL' => .02],
                'MOZZARELLA' => ['MOZZARELLA' => .12, 'CRUMBS' => .04, 'OIL' => .02],
                'JALAPENO' => ['JALAPENO' => .10, 'CHEDDAR' => .05, 'CRUMBS' => .03, 'OIL' => .02],
                'NACHOS' => ['NACHOS' => .12, 'CHEDDAR' => .05],
                'COLESLAW' => ['CABBAGE' => .10, 'CARROT' => .03, 'MAYO' => .025],
                'SALAD' => ['LETTUCE' => .10, 'TOMATO' => .10, 'CARROT' => .03, 'SAUCE' => .015],
                'WINGS-BBQ' => ['WINGS' => .25, 'BBQ' => .04, 'OIL' => .02],
                'WINGS-HOT' => ['WINGS' => .25, 'BUFFALO' => .04, 'OIL' => .02],
                default => ['CHICKEN' => .18, 'CRUMBS' => .04, 'OIL' => .02],
            };
        }
        if ($group === 'sauces') {
            return [match ($code) { 'BURGER' => 'SAUCE', 'GARLIC' => 'GARLIC', default => $code } => .05];
        }
        if ($group === 'drinks') {
            return match ($code) {
                'ORANGE' => ['ORANGE' => .4], 'LEMON-MINT' => ['LEMON' => .12, 'MINT' => .01, 'WATER' => .25],
                'MANGO' => ['MANGO' => .15, 'WATER' => .2], 'STRAWBERRY' => ['STRAWBERRY' => .15, 'WATER' => .2],
                'ICED-TEA' => ['TEA' => .05, 'WATER' => .3],
                'MOJITO' => ['LEMON' => .08, 'MINT' => .01, 'SPARKLING' => .25],
                default => [$code => .33],
            };
        }
        $items = ['MILK' => .2, 'ICECREAM' => .12];
        $items[match ($code) { 'CHOCOLATE' => 'CHOCOLATE', 'STRAWBERRY' => 'STRAWBERRY', 'OREO' => 'OREO', 'LOTUS' => 'LOTUS', default => 'VANILLA' }] = .025;
        return $items;
    }

    public function seedRecipes($raw, User $admin, bool $skipTrashedProducts = false): void
    {
        foreach (BurgerRestaurantSeeder::catalogue() as $group => $definition) {
            foreach ($definition['items'] as [$code]) {
                $sku = 'BRG-'.strtoupper($group).'-'.$code;
                $product = Product::withTrashed()->where('sku', $sku)->firstOrFail();
                if ($product->trashed() && $skipTrashedProducts) {
                    continue;
                }
                if ($product->trashed()) {
                    throw new RuntimeException('Demo catalogue product is deleted: '.$sku);
                }
                $recipe = Recipe::firstOrCreate(['code' => 'RCP-'.$product->sku], [
                    'product_id' => $product->id, 'name' => 'وصفة '.$product->name_ar,
                    'version' => 1, 'yield_quantity' => 1, 'labor_cost_per_batch' => .8,
                    'overhead_percent' => 5, 'status' => 'approved', 'is_active' => true,
                    'created_by' => $admin->id, 'approved_by' => $admin->id, 'approved_at' => now(),
                    'notes' => 'وصفة تجريبية للتدريب؛ اضبط المقادير والتكلفة قبل التشغيل الفعلي.',
                ]);
                foreach ($this->recipeFor($group, $code) as $ingredient => $quantity) {
                    RecipeItem::firstOrCreate(['recipe_id' => $recipe->id, 'ingredient_product_id' => $raw[$ingredient]->id], [
                        'quantity' => $quantity, 'unit_snapshot' => $raw[$ingredient]->unit,
                        'expected_waste_percent' => 0, 'stage' => 'تحضير', 'sort_order' => 10,
                    ]);
                }
            }
        }
    }

    /**
     * Demo only: make an incomplete recipe a hard error rather than silently
     * deducting the finished menu product (inventory_mode=auto fallback).
     * Leave manually selected non-auto modes alone on repeated demo seeds.
     */
    private function requireRecipesForDemoMenu(): void
    {
        $products = Product::query()
            ->where('is_raw_material', false)
            ->where('sku', 'like', 'BRG-%')
            ->pluck('id');

        RestaurantMenuItem::query()
            ->whereIn('product_id', $products)
            ->where('inventory_mode', 'auto')
            ->update(['inventory_mode' => 'recipe']);
    }

    /**
     * Stop an incomplete training fixture immediately. No opening balance is
     * considered valid unless the menu -> approved recipe -> raw ingredient ->
     * branch inventory and stock movements are all connected.
     */
    private function assertDemoInventoryAndRecipes(): void
    {
        $menuProducts = Product::query()
            ->where('is_raw_material', false)
            ->where('sku', 'like', 'BRG-%')
            ->get(['id', 'sku']);

        foreach ($menuProducts as $product) {
            $recipe = Recipe::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->where('status', 'approved')
                ->where('is_active', true)
                ->with('items.ingredient')
                ->first();

            if (! $recipe || (float) $recipe->yield_quantity <= 0 || $recipe->items->isEmpty()) {
                throw new RuntimeException("Missing active ingredient recipe: {$product->sku}");
            }

            foreach ($recipe->items as $item) {
                if (! $item->ingredient?->is_raw_material || (float) $item->quantity <= 0) {
                    throw new RuntimeException("Invalid recipe ingredient: {$product->sku}");
                }
            }

            foreach (Location::query()->branches()->active()->get() as $branch) {
                $menu = RestaurantMenuItem::query()
                    ->where('location_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->first();

                if (! $menu || $menu->inventory_mode !== 'recipe') {
                    throw new RuntimeException("Menu recipe mode missing: {$branch->code}/{$product->sku}");
                }

                foreach ($recipe->items as $item) {
                    if (! \App\Models\Inventory::query()
                        ->where('location_id', $branch->id)
                        ->where('product_id', $item->ingredient_product_id)
                        ->exists()) {
                        throw new RuntimeException("Ingredient stock record missing: {$branch->code}/{$product->sku}");
                    }
                }
            }
        }

        if (DB::table('inventories')->where('quantity', '<', 0)->exists()) {
            throw new RuntimeException('Negative raw inventory found after Tish demo orders.');
        }

        $confirmedIds = Order::query()
            ->where('notes', 'like', 'TISH-DEMO-%')
            ->whereIn('status', ['confirmed', 'completed'])
            ->pluck('id');

        foreach ($confirmedIds as $orderId) {
            if (! DB::table('order_inventory_consumptions')
                ->where('order_id', $orderId)
                ->where('source', 'recipe')
                ->exists()) {
                throw new RuntimeException("Demo order lacks recipe consumption: {$orderId}");
            }
        }
    }

    private function openingStock($raw, User $admin): void
    {
        foreach (Location::active()->get() as $location) {
            foreach ($raw as $code => $product) {
                LocationProduct::firstOrCreate(['location_id' => $location->id, 'product_id' => $product->id], [
                    'is_available' => true, 'minimum_stock_level' => 10,
                ]);
                app(InventoryService::class)->increase(
                    locationId: $location->id, productId: $product->id, quantity: 200,
                    reason: MovementReason::OpeningStock, userId: $admin->id,
                    unitCost: (string) self::ingredients()[$code][2],
                    idempotencyKey: 'tish-demo:opening:'.$location->id.':'.$product->id,
                    note: 'رصيد افتتاحي تجريبي لمطعم البرجر'
                );
            }
        }
    }

    private function seedRestaurantOperations(User $admin): void
    {
        foreach (Location::branches()->active()->get() as $branch) {
            $area = RestaurantArea::firstOrCreate(['location_id' => $branch->id, 'code' => 'HALL'], [
                'name' => 'صالة Tish', 'is_active' => true, 'sort_order' => 10,
            ]);
            foreach (range(1, 8) as $number) {
                RestaurantTable::firstOrCreate(['location_id' => $branch->id, 'code' => 'T'.$number], [
                    'area_id' => $area->id, 'name' => 'طاولة '.$number, 'capacity' => 4,
                    'is_active' => true, 'sort_order' => $number,
                ]);
            }
            // Single kitchen production station and one preparation/assembly station.
            foreach (['KITCHEN' => 'محطة المطبخ', 'PREP' => 'محطة التجهيز'] as $code => $name) {
                $station = KitchenStation::firstOrCreate(['location_id' => $branch->id, 'code' => $code], [
                    'name' => $name, 'is_active' => true, 'is_default' => $code === 'KITCHEN',
                    'target_minutes' => $code === 'PREP' ? 5 : 15, 'created_by' => $admin->id,
                ]);
                $groups = $code === 'KITCHEN'
                    ? ['beef', 'chicken', 'vegetarian', 'fries', 'appetizers']
                    : ['sauces', 'drinks', 'shakes'];
                foreach ($groups as $group) {
                    $category = Category::where('slug', 'burger-'.$group)->firstOrFail();
                    DB::table('kitchen_category_routes')->updateOrInsert([
                        'location_id' => $branch->id, 'category_id' => $category->id,
                    ], ['kitchen_station_id' => $station->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $cashier = User::where('username', 'cashier_'.strtolower($branch->code))->firstOrFail();
            CashSession::firstOrCreate(['employee_id' => $cashier->employee_id, 'location_id' => $branch->id, 'status' => 'open'], [
                'opening_balance' => 0, 'cash_received' => 0, 'cash_refunds' => 0, 'opened_at' => now()->startOfDay(),
            ]);
            Customer::firstOrCreate(['phone' => 'DEMO-'.$branch->code], [
                'location_id' => $branch->id, 'name' => 'زبون Tish التجريبي — '.$branch->code,
                'customer_type' => 'individual', 'scope' => 'branch', 'allow_credit' => false,
            ]);
        }
        $start = now()->startOfMonth();
        FinancialPeriod::firstOrCreate(['year' => $start->year, 'month' => $start->month], [
            'name' => 'فترة Tish الحالية', 'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'open',
            'opening_balance' => 0, 'opened_at' => now(), 'opened_by' => $admin->id,
        ]);
        SalesChannel::firstOrCreate(['slug' => 'tish-direct'], [
            'name' => 'طلبات Tish المباشرة', 'type' => 'direct', 'is_active' => true,
            'discount_type' => 'fixed', 'discount_value' => 0, 'commission_type' => 'fixed', 'commission_value' => 0,
        ]);
    }

    private function seedOrders(User $admin): void
    {
        $channel = SalesChannel::where('slug', 'tish-direct')->firstOrFail();
        $payment = PaymentMethod::where('code', 'CASH')->firstOrFail();
        $products = Product::where('is_raw_material', false)->where('sku', 'like', 'BRG-%')->orderBy('id')->get();
        foreach (Location::branches()->active()->get() as $branch) {
            $cashier = User::where('username', 'cashier_'.strtolower($branch->code))->firstOrFail();
            $customer = Customer::where('location_id', $branch->id)->firstOrFail();
            foreach (range(1, 12) as $number) {
                $marker = 'TISH-DEMO-'.$branch->code.'-'.$number;
                if (Order::where('notes', $marker)->exists()) { continue; }
                $rows = [
                    ['product_id' => $products[($number - 1) % $products->count()]->id, 'quantity' => ($number % 3) + 1],
                    ['product_id' => Product::where('sku', 'BRG-FRIES-MEDIUM')->value('id'), 'quantity' => 1],
                    ['product_id' => Product::where('sku', 'BRG-DRINKS-COLA')->value('id'), 'quantity' => 1],
                    ['product_id' => Product::where('sku', 'BRG-SAUCES-RANCH')->value('id'), 'quantity' => 1],
                ];
                $payload = [
                    'location_id' => $branch->id, 'customer_id' => $customer->id,
                    'sales_channel_id' => $channel->id,
                    'service_type' => $number % 2 === 0 ? 'dine_in' : 'takeaway',
                    'restaurant_table_id' => $number % 2 === 0
                        ? RestaurantTable::where('location_id', $branch->id)->where('code', 'T'.(($number % 8) + 1))->value('id')
                        : null,
                    'payment_arrangement' => 'pay_now', 'payment_method_id' => $payment->id,
                    'discount_type' => 'fixed', 'discount_value' => 0,
                    'items' => $rows, 'notes' => $marker, 'order_source' => 'restaurant_pos',
                ];
                $order = app(RestaurantOrderService::class)->createPosOrder($payload, $cashier);
                if ($number <= 8) {
                    foreach ($order->kitchenTickets()->get() as $ticket) {
                        $service = app(KitchenTicketService::class);
                        $ticket = $service->start($ticket, $admin);
                        $ticket = $service->markReady($ticket, $admin);
                        $service->serve($ticket, $admin);
                    }
                    app(OrderService::class)->completeOrder($order->fresh(), $admin);
                } elseif ($number <= 10) {
                    foreach ($order->kitchenTickets()->get() as $ticket) {
                        app(KitchenTicketService::class)->start($ticket, $admin);
                    }
                }
            }
        }
    }

    /**
     * Verify a transfer on a draft POS order, then confirm and invoice it.
     * Exercises: pending payment -> bank verification -> customer advance 2400
     * -> confirmed order -> invoice -> advance settlement -> COGS / inventory.
     */
    private function seedBankVerificationScenario(User $admin): void
    {
        $branch = Location::query()->where('code', 'B01')->firstOrFail();
        $marker = 'TISH-DEMO-BANK-ADVANCE-B01';
        if (Order::query()->where('notes', $marker)->exists()) {
            return;
        }

        $cashier = User::query()->where('username', 'cashier_b01')->firstOrFail();
        $customer = Customer::query()->where('location_id', $branch->id)->firstOrFail();
        $channel = SalesChannel::query()->where('slug', 'tish-direct')->firstOrFail();
        $bank = PaymentMethod::query()->where('code', 'bank_of_palestine')->firstOrFail();
        $burger = Product::query()->where('sku', 'BRG-BEEF-CLASSIC')->first();
        if (! $burger) {
            $burger = Product::query()->where('sku', 'like', 'BRG-BEEF-%')->firstOrFail();
        }

        $order = app(RestaurantOrderService::class)->createPosOrder([
            'location_id' => $branch->id,
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pending_verification',
            'payment_method_id' => $bank->id,
            'reference_number' => 'TISH-QA-BANK-ADVANCE',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
            'notes' => $marker,
            'order_source' => 'restaurant_pos',
        ], $cashier);

        if ($order->statusValue() !== 'draft') {
            throw new RuntimeException('Bank transfer test order must await verification as draft.');
        }

        $payment = Payment::query()
            ->where('order_type', 'order')
            ->where('order_id', $order->id)
            ->sole();

        if ($payment->statusValue() !== 'pending_verification') {
            throw new RuntimeException('Bank transfer test payment must be pending.');
        }

        app(PaymentService::class)->verify($payment, true, null, $admin);
        if ($order->fresh()->statusValue() !== 'draft') {
            throw new RuntimeException('Payment approval must not auto-confirm the order.');
        }
        app(OrderService::class)->confirmOrder($order->fresh(), $admin);
    }

    private function assertFinanceFixtureIntegrity(): void
    {
        $orders = Order::query()->where('notes', 'like', 'TISH-DEMO-%')->get();
        if ($orders->count() < 13) {
            throw new RuntimeException('Expected 12 cash POS orders and one bank transfer order.');
        }

        foreach ($orders as $order) {
            if (! $order->invoice()->exists()) {
                throw new RuntimeException("Demo order has no invoice: {$order->id}");
            }

            $payments = Payment::query()->where('order_type', 'order')
                ->where('order_id', $order->id)->get();
            if ($payments->isEmpty() || $payments->contains(fn ($p) => $p->statusValue() !== 'confirmed')) {
                throw new RuntimeException("Demo payment missing or not confirmed: {$order->id}");
            }

            foreach ($payments as $payment) {
                $entry = DB::table('sales_ledger_entries')
                    ->where('idempotency_key', "payment:{$payment->id}:collection")
                    ->first();
                if (! $entry || ! AccountingJournal::query()
                    ->where('source_type', 'sales_ledger_entries')
                    ->where('source_id', $entry->id)->exists()) {
                    throw new RuntimeException("Demo payment has no GL journal: {$payment->id}");
                }
            }
        }

        $unbalanced = DB::table('accounting_journals')
            ->whereRaw('ABS(total_debit - total_credit) > 0.005')->count();
        if ($unbalanced > 0) {
            throw new RuntimeException("Unbalanced journals: {$unbalanced}");
        }

        $bank = $orders->firstWhere('notes', 'TISH-DEMO-BANK-ADVANCE-B01');
        if (! $bank || ! AccountingJournal::query()
            ->where('source_type', 'invoice_advance_settlements')
            ->where('source_id', $bank->invoice?->id)->exists()) {
            throw new RuntimeException('Bank advance was not settled against its invoice.');
        }
    }

}

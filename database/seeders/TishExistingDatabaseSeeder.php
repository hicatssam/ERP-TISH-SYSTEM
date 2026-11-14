<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Location;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantArea;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Add/repair the Tish catalogue and recipes in an existing MySQL installation.
 * This does NOT seed opening balances, invent sales, touch payments, delete
 * historical stations, or replace manually edited recipe quantities.
 *
 * Run ONLY after backing up the database:
 * php artisan db:seed --class=TishExistingDatabaseSeeder
 */
class TishExistingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Location::query()->where('code', 'B01')->where('is_active', true)->firstOrFail();
        $activeOther = Location::query()->where('is_active', true)->where('id', '!=', $branch->id)->exists();
        if ($activeOther) {
            throw new RuntimeException('Only B01 may be active while applying the Tish one-branch configuration.');
        }
        $admin = User::query()->where('username', 'admin')->first();
        if (! $admin) {
            throw new RuntimeException('An existing admin user is required to attribute approved demo recipes.');
        }

        DB::transaction(function () use ($branch, $admin): void {
            $this->call(BurgerRestaurantSeeder::class);
            $catalogue = new BurgerRestaurantDemoSeeder();
            $raw = $catalogue->seedIngredients();
            $catalogue->seedRecipes($raw, $admin);

            $area = RestaurantArea::query()->firstOrCreate(
                ['location_id' => $branch->id, 'code' => 'HALL'],
                ['name' => 'صالة Tish', 'is_active' => true, 'sort_order' => 10]
            );

            $stations = [];
            foreach (['KITCHEN' => 'محطة المطبخ', 'PREP' => 'محطة التجهيز'] as $code => $name) {
                $stations[$code] = KitchenStation::query()->firstOrCreate(
                    ['location_id' => $branch->id, 'code' => $code],
                    ['name' => $name, 'is_active' => true, 'is_default' => $code === 'KITCHEN',
                        'target_minutes' => $code === 'PREP' ? 5 : 15, 'created_by' => $admin->id]
                );
            }

            $productIds = [];
            foreach (BurgerRestaurantSeeder::catalogue() as $group => $definition) {
                $station = in_array($group, ['sauces', 'drinks', 'shakes'], true)
                    ? $stations['PREP'] : $stations['KITCHEN'];
                $category = Category::query()->where('slug', 'burger-'.$group)->firstOrFail();
                // Redirect future tickets only. Existing tickets retain their station IDs.
                DB::table('kitchen_category_routes')->updateOrInsert(
                    ['location_id' => $branch->id, 'category_id' => $category->id],
                    ['kitchen_station_id' => $station->id, 'created_at' => now(), 'updated_at' => now()]
                );

                foreach ($definition['items'] as [$code]) {
                    $product = Product::query()->where('sku', 'BRG-'.strtoupper($group).'-'.$code)->firstOrFail();
                    $productIds[] = $product->id;

                    $recipe = Recipe::query()->where('product_id', $product->id)
                        ->whereNull('product_variant_id')->where('status', 'approved')
                        ->where('is_active', true)->with('items.ingredient')->first();
                    if (! $recipe || $recipe->items->isEmpty() || (float) $recipe->yield_quantity <= 0) {
                        throw new RuntimeException('Missing approved ingredient recipe: '.$product->sku);
                    }
                    foreach ($recipe->items as $item) {
                        if (! $item->ingredient?->is_raw_material || (float) $item->quantity <= 0) {
                            throw new RuntimeException('Invalid ingredient for '.$product->sku);
                        }
                        // Refuse to fabricate real stock. It must be received or counted through InventoryService.
                        if (! DB::table('inventories')->where('location_id', $branch->id)
                            ->where('product_id', $item->ingredient_product_id)->exists()) {
                            throw new RuntimeException('Missing B01 inventory for '.$item->ingredient->sku.
                                '. Receive actual stock through inventory workflow first.');
                        }
                    }

                    $menu = RestaurantMenuItem::query()->where('location_id', $branch->id)
                        ->where('product_id', $product->id)->firstOrFail();
                    if ($menu->inventory_mode === 'auto') {
                        $menu->inventory_mode = 'recipe';
                    }
                    if ($menu->inventory_mode !== 'recipe') {
                        throw new RuntimeException('Explicit non-recipe inventory mode for '.$product->sku.
                            '. Review manually before enabling recipe deduction.');
                    }
                    $menu->show_in_delivery = false;
                    $menu->save();
                }
            }
            if (count($productIds) !== 76 || $raw->count() !== 53) {
                throw new RuntimeException('Unexpected Tish product or ingredient catalogue size.');
            }
            $this->command?->info('Tish B01: 76 menu recipes checked, 53 raw ingredients, KITCHEN/PREP routes ready.');
            $this->command?->warn('Historical B02/FAC data and old kitchen tickets untouched. No stock was added.');
        });
    }
}

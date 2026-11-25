<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicit, idempotent ingredient catalogue setup; safe for an existing installation.
 *
 * NO opening stock, invented purchase costs, approved recipes, financial journals,
 * order/payment changes, deleted data or menu-product mutations.
 * Run only after reviewing a database backup and existing branch/SKU mapping:
 * php artisan db:seed --class=TishRawMaterialsCatalogSeeder
 */
class TishRawMaterialsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $ingredients = BurgerRestaurantDemoSeeder::ingredients();
            $category = Category::query()->firstOrCreate(
                ['slug' => 'burger-ingredients'],
                [
                    'name' => 'Burger Ingredients',
                    'name_ar' => 'المواد الخام للمطعم',
                    'is_active' => true,
                    'sort_order' => 900,
                ],
            );

            $branches = Location::query()->branches()->active()->get();
            if ($branches->isEmpty()) {
                throw new RuntimeException('No active restaurant branches. Catalogue was not imported.');
            }

            foreach ($ingredients as $code => [$name, $unit]) {
                $sku = 'BRG-ING-'.$code;
                $existing = Product::withTrashed()->where('sku', $sku)->first();
                if ($existing && (
                    $existing->trashed()
                    || ! $existing->is_raw_material
                    || ! $existing->is_active
                    || $existing->unit !== $unit
                )) {
                    throw new RuntimeException("Conflicting or inactive raw material SKU: {$sku}. Review manually.");
                }

                $product = $existing ?? Product::query()->create([
                    'sku' => $sku,
                    'barcode' => $sku,
                    'category_id' => $category->id,
                    'name' => $name,
                    'name_ar' => $name,
                    'unit' => $unit,
                    'product_type' => 'standard',
                    'base_selling_price' => 0,
                    'is_active' => true,
                    'is_raw_material' => true,
                    'tracks_batch' => false,
                    'tracks_expiry' => false,
                ]);

                foreach ($branches as $branch) {
                    LocationProduct::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $product->id],
                        ['is_available' => true, 'minimum_stock_level' => 0],
                    );

                    // A zero balance is a catalogue placeholder, NOT stock receipt.
                    // Real quantity/cost must come from verified receiving/stock count.
                    Inventory::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $product->id],
                        ['quantity' => 0, 'reserved_quantity' => 0,
                            'damaged_quantity' => 0, 'in_transit_quantity' => 0,
                            'unit_cost' => 0],
                    );
                }
            }
        });

        $this->command?->info('TISH raw material catalogue initialized with zero stock. No recipes, receipts or financial entries created.');
    }
}

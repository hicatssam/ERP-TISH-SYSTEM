<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TISH breakfast-only ingredient catalogue and proposed DRAFT recipe items.
 * All amounts are placeholders for the owner/chef to edit before approval.
 * No inventory receipts, recipes approval, menu mode, GL, costs or sales edits.
 */
class TishBreakfastDraftIngredientsSeeder extends Seeder
{
    private const MATERIALS = [
        'TOAST' => ['خبز توست', 'piece'],
        'WRAP' => ['خبز رول / تورتيلا', 'piece'],
        'BAGUETTE' => ['خبز باغيت', 'piece'],
        'FETA' => ['جبنة فيتا', 'kg'],
        'ZAATAR' => ['زعتر', 'kg'],
        'OLIVE-OIL' => ['زيت زيتون', 'liter'],
        'AVOCADO' => ['أفوكادو', 'kg'],
        'EGG' => ['بيض', 'piece'],
        'PASTRAMI' => ['بسطرمة للفطور', 'kg'],
        'BREAKFAST-SAUCE' => ['صوص الفطور (تحديد النوع لاحقاً)', 'kg'],
        'CREAMY-SAUCE' => ['صوص كريمي للفطور', 'kg'],
        'BREAKFAST-CHEESE' => ['جبنة K أو جريديس (حدد الصنف لاحقاً)', 'kg'],
        'BALADI-CHEESE' => ['جبنة بلدية فلسطينية', 'kg'],
        'TUNA' => ['تونا', 'kg'],
        'ARUGULA' => ['جرجير', 'kg'],
        'CUCUMBER' => ['خيار', 'kg'],
    ];

    private const RECIPES = [
        'ATHENA-TOAST' => [
            'TOAST' => 2, 'FETA' => .04, 'ZAATAR' => .004, 'OLIVE-OIL' => .008,
            'LETTUCE' => .02, 'TOMATO' => .03, 'ONION' => .015, 'CHICKEN' => .08,
        ],
        'CHICKEN-ROLL' => [
            'WRAP' => 1, 'BREAKFAST-SAUCE' => .02, 'LETTUCE' => .02,
            'TOMATO' => .03, 'PASTRAMI' => .03, 'CHEDDAR' => .03, 'CHICKEN' => .10,
        ],
        'CLUB-SANDWICH' => [
            'TOAST' => 3, 'BREAKFAST-SAUCE' => .02, 'LETTUCE' => .02,
            'TOMATO' => .03, 'AVOCADO' => .035, 'EGG' => 1,
            'MAYO' => .015, 'PASTRAMI' => .035, 'CHICKEN' => .08,
        ],
        'CREAMY-EGG-TOAST' => [
            'TOAST' => 2, 'CREAMY-SAUCE' => .025, 'EGG' => 2,
            'PASTRAMI' => .035, 'BREAKFAST-CHEESE' => .03,
        ],
        'PALESTINIAN-CHEESE' => [
            'LETTUCE' => .02, 'TOMATO' => .04, 'CUCUMBER' => .04,
            'BALADI-CHEESE' => .08, 'OLIVE-OIL' => .008,
        ],
        'TUNA-BAGUETTE' => [
            'BAGUETTE' => 1, 'ARUGULA' => .02, 'TOMATO' => .04,
            'ONION' => .015, 'TUNA' => .09, 'AVOCADO' => .035,
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $breakfast = Product::query()
                ->where('sku', 'like', 'BRG-BREAKFAST-%')
                ->where('is_raw_material', false)
                ->where('is_active', true)
                ->get()->keyBy('sku');

            $activeMenuCount = Product::query()
                ->where('is_raw_material', false)->where('is_active', true)->count();

            if ($breakfast->count() !== 6 || $activeMenuCount < 6) {
                throw new RuntimeException('Breakfast catalogue mismatch. No edits made.');
            }

            $recipes = [];
            foreach (self::RECIPES as $code => $materials) {
                $p = $breakfast->get('BRG-BREAKFAST-'.$code);
                if (! $p) {
                    throw new RuntimeException('Breakfast product missing: '.$code);
                }
                $existing = Recipe::query()->where('product_id', $p->id)->get();
                if ($existing->count() !== 1 || ! $existing->first()->isEditable()
                    || $existing->first()->is_active) {
                    throw new RuntimeException('Expected one nonactive editable breakfast recipe: '.$code);
                }
                $recipe = $existing->first();
                // Idempotent but never overwrite the owner's existing work.
                if ($recipe->items()->exists()) {
                    throw new RuntimeException('Breakfast recipe already contains items; review manually: '.$code);
                }
                $recipes[$code] = $recipe;
            }

            $category = Category::query()->firstOrCreate(
                ['slug' => 'burger-ingredients'],
                ['name' => 'Burger Ingredients', 'name_ar' => 'مواد خام للمطعم', 'is_active' => true, 'sort_order' => 900]
            );
            $branches = Location::query()->branches()->active()->get();
            if ($branches->isEmpty()) {
                throw new RuntimeException('No active branch.');
            }

            $raw = [];
            foreach (self::MATERIALS as $code => [$name, $unit]) {
                $sku = 'BRG-ING-'.$code;
                $existing = Product::withTrashed()->where('sku', $sku)->first();
                if ($existing && ($existing->trashed() || ! $existing->is_raw_material
                    || ! $existing->is_active || $existing->unit !== $unit)) {
                    throw new RuntimeException('Ingredient SKU conflict: '.$sku);
                }
                $p = $existing ?? Product::query()->create([
                    'sku' => $sku, 'barcode' => $sku,
                    'category_id' => $category->id,
                    'name' => $name, 'name_ar' => $name, 'unit' => $unit,
                    'product_type' => 'standard', 'base_selling_price' => 0,
                    'is_active' => true, 'is_raw_material' => true,
                    'tracks_batch' => false, 'tracks_expiry' => false,
                ]);
                $raw[$code] = $p;
                foreach ($branches as $branch) {
                    LocationProduct::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $p->id],
                        ['is_available' => true, 'minimum_stock_level' => 0]
                    );
                    Inventory::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $p->id],
                        ['quantity' => 0, 'reserved_quantity' => 0, 'damaged_quantity' => 0,
                            'in_transit_quantity' => 0, 'unit_cost' => 0]
                    );
                }
            }

            foreach (self::RECIPES as $code => $materials) {
                $recipe = $recipes[$code];
                $sort = 10;
                foreach ($materials as $materialCode => $quantity) {
                    $ingredient = $raw[$materialCode]
                        ?? Product::query()->where('sku', 'BRG-ING-'.$materialCode)
                            ->where('is_raw_material', true)->where('is_active', true)->first();
                    if (! $ingredient || $quantity <= 0) {
                        throw new RuntimeException('Missing ingredient: '.$materialCode.' for '.$code);
                    }
                    RecipeItem::query()->create([
                        'recipe_id' => $recipe->id,
                        'ingredient_product_id' => $ingredient->id,
                        'quantity' => $quantity,
                        'unit_snapshot' => $ingredient->unit,
                        'expected_waste_percent' => 0,
                        'stage' => 'تحضير',
                        'sort_order' => $sort += 10,
                    ]);
                }
                $recipe->update([
                    'notes' => trim(($recipe->notes ?? '')."\n"
                        .'مقادير مبدئية غير معتمدة، يلزم مراجعة المكونات والأوزان والهدر قبل التشغيل.'
                        .($code === 'TUNA-BAGUETTE' ? ' بديل الأفوكادو: الخيار حسب اختيار الشيف.' : '')
                        .($code === 'CHICKEN-ROLL' ? ' الجبنة المختارة مؤقتاً: شيدر، راجع النوع.' : '')
                        .($code === 'CREAMY-EGG-TOAST' ? ' حدّد هل الجبنة K أم جريديس قبل الاعتماد.' : '')),
                ]);
            }
            $this->command?->info('Added 16 breakfast raw materials at zero stock and populated 6 nonactive draft recipes; no stock movements or approvals.');
        });
    }
}

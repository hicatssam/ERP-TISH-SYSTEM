<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proposal-only draft recipes for an existing TISH menu.
 *
 * Never approve recipes, modify inventory modes, stock, account journals,
 * purchase prices, products or existing recipe contents.
 * Suggested amounts from the original QA demo are NOT verified kitchen recipes.
 * Breakfast products are created as deliberately incomplete drafts.
 */
class TishMenuDraftRecipesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $actor = User::query()->where('is_active', true)->orderBy('id')->first();
            if (! $actor) {
                throw new RuntimeException('No active user found; draft recipes not created.');
            }

            $catalogue = [];
            foreach (BurgerRestaurantSeeder::catalogue() as $group => $definition) {
                foreach ($definition['items'] as [$code]) {
                    $catalogue['BRG-'.strtoupper($group).'-'.$code] = [$group, $code];
                }
            }

            $ingredients = Product::query()
                ->where('is_raw_material', true)
                ->where('is_active', true)
                ->get()
                ->keyBy('sku');

            $menu = Product::query()
                ->where('is_raw_material', false)
                ->where('is_active', true)
                ->where('sku', 'like', 'BRG-%')
                ->orderBy('sku')
                ->get();

            if ($menu->isEmpty()) {
                throw new RuntimeException('No active TISH menu products; abort.');
            }

            $created = 0;
            $incomplete = 0;
            foreach ($menu as $product) {
                $code = 'RCP-'.$product->sku;
                $exists = Recipe::withTrashed()->where('product_id', $product->id)->first();
                if ($exists || Recipe::withTrashed()->where('code', $code)->exists()) {
                    // Never overwrite an existing manual recipe, including archived ones.
                    continue;
                }

                $entry = $catalogue[$product->sku] ?? null;
                $proposals = $entry ? $this->recipeFor($entry[0], $entry[1]) : [];
                foreach ($proposals as $ingredientCode => $qty) {
                    $ingredient = $ingredients->get('BRG-ING-'.$ingredientCode);
                    if (! $ingredient || $qty <= 0) {
                        throw new RuntimeException("Missing or invalid recipe ingredient for {$product->sku}: {$ingredientCode}");
                    }
                }

                $recipe = Recipe::query()->create([
                    'code' => $code,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'name' => 'مسودة وصفة '.$product->name_ar,
                    'version' => 1,
                    'yield_quantity' => 1,
                    'labor_cost_per_batch' => 0,
                    'overhead_percent' => 0,
                    'status' => 'draft',
                    'is_active' => false,
                    'created_by' => $actor->id,
                    'approved_by' => null,
                    'approved_at' => null,
                    'notes' => $entry
                        ? 'مقادير مقترحة من بيئة اختبار TISH، وليست وصفة معتمدة. راجع المكونات والكميات والهدر مع الشيف قبل الاعتماد.'
                        : 'وصفة غير مكتملة: لا توجد مقادير معتمدة لهذا الصنف (مثل أصناف الفطور). أضف جميع المواد الخام والكميات الحقيقية قبل الاعتماد.',
                ]);

                foreach ($proposals as $ingredientCode => $qty) {
                    $ingredient = $ingredients->get('BRG-ING-'.$ingredientCode);
                    RecipeItem::query()->create([
                        'recipe_id' => $recipe->id,
                        'ingredient_product_id' => $ingredient->id,
                        'quantity' => $qty,
                        'unit_snapshot' => $ingredient->unit,
                        'expected_waste_percent' => 0,
                        'stage' => 'تحضير',
                        'sort_order' => 10,
                    ]);
                }

                $created++;
                if ($proposals === []) {
                    $incomplete++;
                }
            }

            $this->command?->info("Created {$created} non-active draft recipes; {$incomplete} require ingredients. No inventory modes or journal entries changed.");
        });
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

}

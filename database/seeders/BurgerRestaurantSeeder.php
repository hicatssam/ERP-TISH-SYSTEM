<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\MenuBanner;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Standalone burger menu catalogue. Run explicitly:
 * php artisan db:seed --class=BurgerRestaurantSeeder
 *
 * Prices are illustrative values in the installation's base currency.
 * Existing records and manually edited prices/availability are preserved.
 * Does not run DatabaseSeeder, change branding/activity, delete other menus,
 * create users/orders/payments, or invent physical inventory balances.
 */
class BurgerRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['categories', 'products', 'locations', 'location_products', 'restaurant_menu_items', 'menu_banners'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('شغّل migrations أولاً. الجدول المفقود: '.$table);
            }
        }

        $count = DB::transaction(function (): int {
            $branches = Location::query()->branches()->active()->get();
            if ($branches->isEmpty()) {
                // Never replace or rename existing branches.
                $branch = Location::query()->firstOrCreate(['code' => 'BRG-B01'], [
                    'name' => 'فرع مطعم البرجر', 'type' => 'branch', 'is_active' => true,
                ]);
                if (! $branch->is_active) {
                    throw new RuntimeException('فعّل فرعاً واحداً على الأقل قبل تشغيل Seeder البرجر.');
                }
                $branches = collect([$branch]);
            }

            $count = 0;
            foreach (self::catalogue() as $group => $definition) {
                $category = Category::query()->firstOrCreate(['slug' => 'burger-'.$group], [
                    'name' => $definition['name'], 'name_ar' => $definition['name_ar'],
                    'description' => 'قسم منيو مطعم البرجر.',
                    'is_active' => true, 'sort_order' => ($count + 1) * 10,
                ]);

                foreach ($definition['items'] as $index => [$code, $name, $nameAr, $price]) {
                    $sku = 'BRG-'.strtoupper($group).'-'.$code;
                    $product = Product::withTrashed()->firstOrCreate(['sku' => $sku], [
                        'barcode' => $sku,
                        'category_id' => $category->id, 'name' => $name, 'name_ar' => $nameAr,
                        'description' => $nameAr.' — '.$definition['name_ar'],
                        'unit' => in_array($group, ['drinks', 'shakes'], true) ? 'cup' : 'piece',
                        'product_type' => 'standard', 'base_selling_price' => $price,
                        'prep_time_minutes' => in_array($group, ['drinks', 'shakes'], true) ? 5 : 15,
                        'is_active' => true, 'is_raw_material' => false,
                        'tracks_batch' => false, 'tracks_expiry' => false,
                    ]);
                    // A deliberate deletion must not be undone by rerunning a catalogue seed.
                    if ($product->trashed()) {
                        continue;
                    }

                    foreach ($branches as $branch) {
                        LocationProduct::query()->firstOrCreate([
                            'location_id' => $branch->id, 'product_id' => $product->id,
                        ], [
                            'is_available' => true, 'local_selling_price' => null,
                            'minimum_stock_level' => 0,
                        ]);
                        RestaurantMenuItem::query()->firstOrCreate([
                            'location_id' => $branch->id, 'product_id' => $product->id,
                        ], [
                            'display_name' => $name, 'display_name_ar' => $nameAr,
                            'sort_order' => ($index + 1) * 10,
                            'is_active' => true, 'show_in_pos' => true,
                            'show_in_qr' => true, 'show_in_delivery' => true,
                            'is_takeaway_item' => true, 'inventory_mode' => 'auto',
                        ]);
                    }
                    $count++;
                }
            }
            $this->seedBanners($branches);
            return $count;
        });

        $this->command?->info('تم تجهيز '.$count.' صنف برجر ومقبلات وفرايز ومشروبات في منيو الفروع الفعالة.');
        $this->command?->warn('الأسعار تجريبية. جهّز المخزون أو الوصفات الفعلية قبل تأكيد المبيعات.');
    }

    private function seedBanners(iterable $branches): void
    {
        $banners = [
            ['burger', 'برجر على طريقة لندن', 'London Calling. Burger Waiting.',
                'سماش وتشيز وكرسبي… اختار برجر على ذوقك', 'Find your favourite burger', 'برجر', 'Burgers'],
            ['fries', 'الفرايز إلها حكاية', 'Fries Worth Calling For',
                'تشيز ولودد وكيرلي… كمّل وجبتك', 'Cheese, loaded and curly fries', 'فرايز ومقبلات', 'Fries & Sides'],
            ['drinks', 'برّدها على طريقة لندن', 'A Cool London Break',
                'ميلك شيك وعصائر ومشروبات باردة', 'Milkshakes, juices and cold drinks', 'مشروبات', 'Drinks'],
        ];
        foreach ($branches as $branch) {
            foreach ($banners as $index => [$key, $title, $titleEn, $subtitle, $subtitleEn, $badge, $badgeEn]) {
                $image = 'images/burger-menu/banners/'.$key.'-london.webp';
                if (! is_file(public_path($image))) {
                    throw new RuntimeException('ملف بانر البرجر مفقود: '.$image);
                }
                // Match by stable bundled asset and branch, not editable copy.
                // Existing banner customizations remain untouched on reruns.
                MenuBanner::query()->firstOrCreate([
                    'location_id' => $branch->id, 'image' => $image,
                ], [
                    'title' => $title, 'title_en' => $titleEn,
                    'subtitle' => $subtitle, 'subtitle_en' => $subtitleEn,
                    'badge_text' => $badge, 'badge_text_en' => $badgeEn,
                    'link_url' => '#menuCatalog', 'sort_order' => ($index + 1) * 10,
                    'is_active' => true, 'starts_at' => null, 'ends_at' => null,
                ]);
            }
        }
    }

    public static function catalogue(): array
    {
        return [
            'beef' => [
                'name' => 'Beef Burgers', 'name_ar' => 'برجر لحم',
                'items' => [
                    ['CLASSIC', 'Classic Beef Burger', 'برجر لحم كلاسيك', 22],
                    ['CHEESE', 'Cheeseburger', 'تشيز برجر', 24],
                    ['DOUBLE', 'Double Cheeseburger', 'دبل تشيز برجر', 32],
                    ['TRIPLE', 'Triple Cheeseburger', 'تربل تشيز برجر', 40],
                    ['SMASH', 'Smash Burger', 'سماش برجر', 25],
                    ['DOUBLE-SMASH', 'Double Smash Burger', 'دبل سماش برجر', 34],
                    ['MUSHROOM', 'Mushroom Swiss Burger', 'برجر مشروم وسويس', 28],
                    ['BBQ', 'BBQ Beef Burger', 'برجر لحم باربكيو', 27],
                    ['JALAPENO', 'Jalapeno Burger', 'برجر هالبينو', 27],
                    ['TURKEY-BACON', 'Smoked Turkey Burger', 'برجر لحم بالتركي المدخن', 29],
                    ['MOZZARELLA', 'Mozzarella Beef Burger', 'برجر لحم موزاريلا', 28],
                    ['ONION', 'Onion Ring Burger', 'برجر لحم بحلقات البصل', 27],
                    ['TEXAS', 'Texas Burger', 'برجر تكساس', 30],
                    ['BLUE-CHEESE', 'Blue Cheese Burger', 'برجر بلو تشيز', 30],
                    ['TRUFFLE', 'Truffle Burger', 'برجر ترافل', 34],
                    ['SLIDERS', 'Beef Sliders (3 Pieces)', 'ميني برجر لحم 3 قطع', 32],
                ],
            ],
            'chicken' => [
                'name' => 'Chicken Burgers', 'name_ar' => 'برجر دجاج',
                'items' => [
                    ['CLASSIC', 'Grilled Chicken Burger', 'برجر دجاج مشوي', 21],
                    ['CRISPY', 'Crispy Chicken Burger', 'برجر دجاج كرسبي', 23],
                    ['SPICY', 'Spicy Chicken Burger', 'برجر دجاج سبايسي', 24],
                    ['ZINGER', 'Zinger Burger', 'زنجر برجر', 24],
                    ['DOUBLE', 'Double Chicken Burger', 'دبل برجر دجاج', 31],
                    ['BBQ', 'BBQ Chicken Burger', 'برجر دجاج باربكيو', 25],
                    ['BUFFALO', 'Buffalo Chicken Burger', 'برجر دجاج بافلو', 26],
                    ['RANCH', 'Ranch Chicken Burger', 'برجر دجاج رانش', 25],
                    ['MUSHROOM', 'Mushroom Chicken Burger', 'برجر دجاج بالمشروم', 26],
                    ['HONEY', 'Honey Mustard Chicken Burger', 'برجر دجاج هاني مسترد', 25],
                ],
            ],
            'vegetarian' => [
                'name' => 'Vegetarian Burgers', 'name_ar' => 'برجر نباتي',
                'items' => [
                    ['VEGGIE', 'Veggie Burger', 'برجر خضار', 20],
                    ['FALAFEL', 'Falafel Burger', 'برجر فلافل', 17],
                    ['MUSHROOM', 'Portobello Burger', 'برجر مشروم نباتي', 24],
                ],
            ],
            'fries' => [
                'name' => 'Fries', 'name_ar' => 'الفرايز',
                'items' => [
                    ['SMALL', 'Small Fries', 'فرايز صغير', 7],
                    ['MEDIUM', 'Medium Fries', 'فرايز وسط', 10],
                    ['LARGE', 'Large Fries', 'فرايز كبير', 13],
                    ['CHEESE', 'Cheese Fries', 'تشيز فرايز', 15],
                    ['LOADED', 'Loaded Beef Fries', 'لودد فرايز باللحم', 22],
                    ['CHICKEN', 'Loaded Chicken Fries', 'لودد فرايز بالدجاج', 21],
                    ['CAJUN', 'Cajun Fries', 'كاجون فرايز', 12],
                    ['CURLY', 'Curly Fries', 'كيرلي فرايز', 14],
                    ['WEDGES', 'Potato Wedges', 'بطاطا ويدجز', 13],
                    ['SWEET', 'Sweet Potato Fries', 'فرايز بطاطا حلوة', 15],
                    ['TRUFFLE', 'Truffle Parmesan Fries', 'ترافل بارميزان فرايز', 19],
                ],
            ],
            'appetizers' => [
                'name' => 'Appetizers', 'name_ar' => 'المقبلات',
                'items' => [
                    ['ONION', 'Onion Rings', 'حلقات بصل', 12],
                    ['MOZZARELLA', 'Mozzarella Sticks', 'أصابع موزاريلا', 16],
                    ['NUGGETS', 'Chicken Nuggets', 'ناجتس دجاج', 15],
                    ['TENDERS', 'Chicken Tenders', 'تشيكن تندرز', 20],
                    ['WINGS-BBQ', 'BBQ Chicken Wings', 'أجنحة دجاج باربكيو', 20],
                    ['WINGS-HOT', 'Hot Chicken Wings', 'أجنحة دجاج حارة', 20],
                    ['POPCORN', 'Popcorn Chicken', 'بوب كورن دجاج', 17],
                    ['JALAPENO', 'Jalapeno Poppers', 'هالبينو محشي جبنة', 16],
                    ['NACHOS', 'Cheese Nachos', 'ناتشوز بالجبنة', 15],
                    ['COLESLAW', 'Coleslaw', 'كول سلو', 7],
                    ['SALAD', 'Garden Salad', 'سلطة خضراء', 10],
                ],
            ],
            'drinks' => [
                'name' => 'Cold Drinks', 'name_ar' => 'المشروبات الباردة',
                'items' => [
                    ['COLA', 'Cola', 'كولا', 5],
                    ['COLA-ZERO', 'Cola Zero', 'كولا زيرو', 5],
                    ['LEMON-SODA', 'Lemon Soda', 'مشروب غازي ليمون', 5],
                    ['ORANGE-SODA', 'Orange Soda', 'مشروب غازي برتقال', 5],
                    ['WATER', 'Mineral Water', 'مياه معدنية', 3],
                    ['SPARKLING', 'Sparkling Water', 'مياه غازية', 6],
                    ['ORANGE', 'Fresh Orange Juice', 'عصير برتقال', 10],
                    ['LEMON-MINT', 'Lemon Mint Juice', 'ليمون ونعنع', 11],
                    ['MANGO', 'Mango Juice', 'عصير مانجو', 11],
                    ['STRAWBERRY', 'Strawberry Juice', 'عصير فراولة', 11],
                    ['ICED-TEA', 'Peach Iced Tea', 'آيس تي خوخ', 9],
                    ['MOJITO', 'Alcohol-Free Lemon Mojito', 'موهيتو ليمون بدون كحول', 13],
                ],
            ],
            'shakes' => [
                'name' => 'Milkshakes', 'name_ar' => 'الميلك شيك',
                'items' => [
                    ['VANILLA', 'Vanilla Milkshake', 'ميلك شيك فانيلا', 15],
                    ['CHOCOLATE', 'Chocolate Milkshake', 'ميلك شيك شوكولاتة', 15],
                    ['STRAWBERRY', 'Strawberry Milkshake', 'ميلك شيك فراولة', 15],
                    ['OREO', 'Oreo Milkshake', 'ميلك شيك أوريو', 18],
                    ['LOTUS', 'Lotus Milkshake', 'ميلك شيك لوتس', 18],
                ],
            ],
        ];
    }
}

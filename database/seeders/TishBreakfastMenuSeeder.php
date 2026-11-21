<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Add the owner's six breakfast dishes as editable, non-sellable catalogue
 * starters. Run explicitly on an existing TISH database:
 *
 * php artisan db:seed --class=TishBreakfastMenuSeeder
 *
 * Does not create prices, recipe quantities, inventory, purchase orders, or
 * fake availability. Existing manual edits, deletions and prices are kept.
 */
class TishBreakfastMenuSeeder extends Seeder
{
    private const CATEGORY_SLUG = 'tish-breakfast';

    /** @return array<int, array{string,string,string,string}> */
    public static function dishes(): array
    {
        return [
            ['CLUB-SANDWICH', 'Club Sandwich', 'كلوب ساندويش',
                'خبز توست، صوص، خس، بندورة، أفوكادو، بيض، مايونيز، بسطرمة، صدر دجاج'],
            ['ATHENA-TOAST', 'Athena Toast', 'أثينا توست',
                'جبنة فيتا، زعتر وزيت، خس، بندورة، بصل، صدر دجاج'],
            ['CREAMY-EGG-TOAST', 'Creamy Egg Toast', 'توست البيض الكريمي',
                'صوص كريمي، أومليت بيض، بسطرمة، جبنة K أو جريديس'],
            ['TUNA-BAGUETTE', 'Tuna Baguette', 'باغيت التونا',
                'جرجير، بندورة، بصل، تونا، أفوكادو أو خيار'],
            ['CHICKEN-ROLL', 'Chicken Roll', 'رول الدجاج',
                'صوص، خس، بندورة، بسطرمة، جبنة، صدر دجاج'],
            ['PALESTINIAN-CHEESE', 'Palestinian Cheese', 'الجبنة الفلسطينية',
                'خس، بندورة، خيار، جبنة بلدية، زيت زيتون'],
        ];
    }

    public function run(): void
    {
        foreach (['categories', 'products', 'locations', 'location_products', 'restaurant_menu_items'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('الجدول مفقود: '.$table.'. نفّذ migrations أولاً.');
            }
        }

        DB::transaction(function (): void {
            $branches = Location::query()->where('type', 'branch')
                ->where('is_active', true)->get();

            if ($branches->isEmpty()) {
                throw new RuntimeException('لا توجد فروع TISH فعالة. لم تتم إضافة أصناف.');
            }

            $category = Category::query()->firstOrCreate(
                ['slug' => self::CATEGORY_SLUG],
                ['name' => 'Breakfast Menu', 'name_ar' => 'الفطور',
                    'description' => 'أصناف الإفطار الخاصة بمطعم TISH.', 'is_active' => true,
                    'sort_order' => 85]
            );

            foreach (self::dishes() as $index => [$code, $name, $arabicName, $ingredients]) {
                $sku = 'BRG-BREAKFAST-'.$code;
                $product = Product::withTrashed()->firstOrCreate(
                    ['sku' => $sku],
                    [
                        'barcode' => $sku,
                        'category_id' => $category->id,
                        'name' => $name, 'name_ar' => $arabicName,
                        'description' => 'المكونات: '.$ingredients,
                        'unit' => 'piece', 'product_type' => 'standard',
                        'base_selling_price' => 0,
                        'is_active' => true, 'is_raw_material' => false,
                        'tracks_batch' => false, 'tracks_expiry' => false,
                    ]
                );

                // Never reactivate an item manually deleted by the owner.
                if ($product->trashed()) {
                    continue;
                }

                foreach ($branches as $branch) {
                    LocationProduct::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $product->id],
                        ['is_available' => false, 'local_selling_price' => null,
                            'minimum_stock_level' => 0]
                    );

                    RestaurantMenuItem::query()->firstOrCreate(
                        ['location_id' => $branch->id, 'product_id' => $product->id],
                        [
                            'display_name' => $name,
                            'display_name_ar' => $arabicName,
                            'description' => 'المكونات: '.$ingredients,
                            'sort_order' => ($index + 1) * 10,
                            'is_active' => false,
                            'show_in_pos' => false,
                            'show_in_qr' => false,
                            'show_in_delivery' => false,
                            'is_takeaway_item' => true,
                            'inventory_mode' => 'recipe',
                        ]
                    );
                }
            }
        });

        $this->command?->info('تم تجهيز ستة أصناف إفطار في الكتالوج، وتركها غير متاحة للبيع لحين ضبط السعر والوصفة.');
        $this->command?->warn('لم تُنشأ أسعار أو كميات وصفات أو مخزون وهمي. عدّل الخيارات والأسعار والوصفات من واجهات TISH قبل تفعيل البيع.');
    }
}

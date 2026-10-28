<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerMenuBilingualTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function arabic_is_default_and_english_locale_uses_catalog_translations_without_changing_identity_or_price(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع الرمال', 'code' => 'L-EN-01',
            'type' => 'branch', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Eastern Sweets', 'name_ar' => 'حلويات شرقية',
            'slug' => 'eastern-sweets', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Chocolate Cake', 'name_ar' => 'كيك شوكولاتة',
            'sku' => 'ENU-CAKE', 'barcode' => 'ENU-CAKE',
            'product_type' => 'standard', 'is_active' => true,
        ]);
        LocationProduct::query()->create([
            'location_id' => $branch->id,
            'product_id' => $product->id,
            'local_selling_price' => 27.5,
            'is_available' => true,
        ]);
        RestaurantMenuItem::query()->create([
            'location_id' => $branch->id,
            'product_id' => $product->id,
            'display_name_ar' => 'كيك الشوكولاتة المميز',
            'display_name' => 'Signature Chocolate Cake',
            'show_in_qr' => true, 'is_active' => true,
        ]);

        $arabic = $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false);
        $en = $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))->assertOk()
            ->assertSee('lang="en" dir="ltr"', false);
        $arProduct = $arabic->viewData('menuItems')->first();
        $enProduct = $en->viewData('menuItems')->first();

        $this->assertSame('كيك الشوكولاتة المميز', $arProduct['name']);
        $this->assertSame('حلويات شرقية', $arProduct['category']);
        $this->assertSame('Signature Chocolate Cake', $enProduct['name']);
        $this->assertSame('Eastern Sweets', $enProduct['category']);
        $this->assertSame($arProduct['product_id'], $enProduct['product_id']);
        $this->assertSame($arProduct['menu_item_id'], $enProduct['menu_item_id']);
        $this->assertSame($arProduct['price'], $enProduct['price']);
        $this->assertSame($arProduct['available'], $enProduct['available']);

        $this->get(route('customer-menu.products', [$branch->code, 'lang' => 'en']))
            ->assertOk()->assertSee('Signature Chocolate Cake');
        $this->get(route('customer-menu.product.show', [$branch->code, $product->id, 'lang' => 'en']))
            ->assertOk()->assertSee('Signature Chocolate Cake');
    }

    #[Test]
    public function legacy_arabic_only_category_and_birds_nest_product_get_confirmed_english_names(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع النصر', 'code' => 'B01',
            'type' => 'branch', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'حلويات شرقية',
            'name_ar' => 'حلويات شرقية',
            'slug' => 'legacy-oriental-sweets', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'عش بلبل أصابع', 'name_ar' => 'عش بلبل أصابع',
            'sku' => 'MENU-BIRDS-NEST', 'barcode' => 'MENU-BIRDS-NEST',
            'product_type' => 'standard', 'is_active' => true,
        ]);
        LocationProduct::query()->create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'local_selling_price' => 230, 'is_available' => true,
        ]);
        RestaurantMenuItem::query()->create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'display_name' => 'عش بلبل أصابع',
            'display_name_ar' => 'عش بلبل أصابع',
            'is_active' => true, 'show_in_qr' => true,
        ]);

        (require database_path('migrations/2026_10_28_160000_backfill_legacy_customer_menu_english_names.php'))->up();

        $this->assertSame('Eastern Sweets', $category->fresh()->name);
        $this->assertSame('حلويات شرقية', $category->fresh()->name_ar);
        $this->assertSame("Bird's Nest Pastry Fingers", $product->fresh()->name);
        $this->assertSame('عش بلبل أصابع', $product->fresh()->name_ar);

        $english = $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))->assertOk();
        $item = $english->viewData('menuItems')->first();
        $this->assertSame("Bird's Nest Pastry Fingers", $item['name']);
        $this->assertSame('Eastern Sweets', $item['category']);
        $this->assertSame('Eastern Sweets', $english->viewData('categories')->first()['name']);
        $this->assertSame(230.0, $item['price']);

        $arabic = $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'ar']))->assertOk();
        $this->assertSame('عش بلبل أصابع', $arabic->viewData('menuItems')->first()['name']);
        $this->assertSame('حلويات شرقية', $arabic->viewData('menuItems')->first()['category']);
    }

    #[Test]
    public function english_menu_prefers_real_latin_names_even_if_translations_are_inverted(): void
    {
        $this->get(route('customer-menu.index', ['lang' => 'en']))->assertOk();
        $this->assertSame('Eastern Sweets', \App\Support\CustomerMenuLanguage::name('حلويات شرقية', 'Eastern Sweets'));
        $this->assertTrue(\App\Support\CustomerMenuLanguage::hasEnglishName('عش بلبل', 'Bird Nest'));
        $this->assertFalse(\App\Support\CustomerMenuLanguage::hasEnglishName('عش بلبل', 'حلويات شرقية'));
        $this->assertSame('عش بلبل', \App\Support\CustomerMenuLanguage::englishName('عش بلبل'));
    }

    #[Test]
    public function menu_banners_use_explicit_english_fields_and_preserve_legacy_arabic_fallback(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع الإعلانات', 'code' => 'L-EN-BANNER',
            'type' => 'branch', 'is_active' => true,
        ]);
        $banner = \App\Models\MenuBanner::query()->create([
            'location_id' => $branch->id,
            'image' => 'https://example.com/banner.jpg',
            'title' => 'عرض اليوم',
            'subtitle' => 'حلاوة طازجة',
            'badge_text' => 'لفترة محدودة',
            'title_en' => 'Today\'s Special',
            'subtitle_en' => 'Fresh sweets',
            'badge_text_en' => 'Limited time',
            'is_active' => true,
        ]);

        $arabic = $this->get(route('customer-menu.show', $branch->code))->assertOk();
        $english = $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))->assertOk();
        $this->assertSame('عرض اليوم', $arabic->viewData('banners')->first()['title']);
        $this->assertSame('Today\'s Special', $english->viewData('banners')->first()['title']);
        $this->assertSame('Fresh sweets', $english->viewData('banners')->first()['subtitle']);
        $this->assertSame('Limited time', $english->viewData('banners')->first()['badge_text']);

        $banner->update(['subtitle_en' => null]);
        $this->assertSame('حلاوة طازجة',
            $this->get(route('customer-menu.show', [$branch->code, 'lang' => 'en']))
                ->assertOk()->viewData('banners')->first()['subtitle']);

        $adminForm = file_get_contents(resource_path('views/restaurant/menu-banners/index.blade.php'));
        foreach (['title_en', 'subtitle_en', 'badge_text_en'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $adminForm);
        }
    }

    #[Test]
    public function english_preference_is_sticky_for_menu_pages_and_unknown_locales_fall_back_to_arabic(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع النصر', 'code' => 'L-EN-02', 'type' => 'branch', 'is_active' => true,
        ]);

        foreach ([
            route('customer-menu.index'),
            route('customer-menu.show', $branch->code),
            route('customer-menu.products', $branch->code),
            route('customer-menu.cart', $branch->code),
            route('customer-menu.checkout', $branch->code),
            route('customer-menu.favorites', $branch->code),
            route('customer-menu.my-orders', $branch->code),
        ] as $page) {
            $this->withUnencryptedCookie('dahab_menu_lang', 'en')->get($page)
                ->assertOk()->assertSee('lang="en" dir="ltr"', false)
                ->assertSee('assets/js/customer-menu/i18n.js');
        }

        $this->withUnencryptedCookie('dahab_menu_lang', 'en')
            ->get(route('customer-menu.show', [$branch->code, 'lang' => 'ar']))
            ->assertOk()->assertSee('lang="ar" dir="rtl"', false);
        $this->withUnencryptedCookie('dahab_menu_lang', 'ar')
            ->get(route('customer-menu.show', [$branch->code, 'lang' => 'fr']))
            ->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    }
}

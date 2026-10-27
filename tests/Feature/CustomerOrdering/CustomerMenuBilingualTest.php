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

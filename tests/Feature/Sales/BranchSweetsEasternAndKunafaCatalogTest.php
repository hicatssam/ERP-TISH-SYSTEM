<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomSweetsRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchSweetsEasternAndKunafaCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function new_order_and_product_search_include_only_eastern_sweets_and_all_kunafa_varieties(): void
    {
        [, , $user] = $this->actor();
        $eastern = $this->category('oriental-sweets', 'حلويات شرقية', 'Oriental Sweets');
        $kunafa = $this->category('kunafa', 'الكنافة', 'Kunafa');
        $kunafaVariety = $this->category('knafeh-nabulsi', 'كنافة نابلسية', 'Knafeh Nabulsi');
        $kunafaPlural = $this->category('kunayef', 'الكنائف', 'Kunayef');
        $cakes = $this->category('cakes', 'الكيك', 'Cakes');
        $inactive = $this->category('inactive-kunafa', 'كنافة متوقفة', 'Kunafa inactive', false);

        $products = [
            $this->product($eastern, 'بقلاوة شرقية'),
            $this->product($kunafa, 'كنافة خشنة'),
            $this->product($kunafaVariety, 'كنافة نابلسية ناعمة'),
            $this->product($kunafaPlural, 'كنائف بالقشطة'),
        ];
        $excluded = [
            $this->product($cakes, 'كنافة تورتة'),
            $this->product($inactive, 'كنافة غير نشطة'),
            $this->product($kunafa, 'كنافة مادة خام', true),
        ];

        $page = $this->actingAs($user)->get(route('showroom-sweets-requests.create'))->assertOk();
        foreach ($products as $product) {
            $page->assertSee($product->name);
        }
        foreach ($excluded as $product) {
            $page->assertDontSee($product->name);
        }
        $page->assertSee('الحلويات الشرقية والكنافة بجميع أنواعها');

        $response = $this->actingAs($user)->getJson(route('showroom-sweets-requests.products'))
            ->assertOk();
        $ids = array_column($response->json(), 'id');
        foreach ($products as $product) {
            $this->assertContains($product->id, $ids);
        }
        foreach ($excluded as $product) {
            $this->assertNotContains($product->id, $ids);
            $this->actingAs($user)->getJson(route('showroom-sweets-requests.products', ['id' => $product->id]))
                ->assertOk()->assertExactJson([]);
        }
    }

    #[Test]
    public function creation_and_edit_accept_kunafa_and_eastern_sweets_but_reject_other_categories(): void
    {
        [, $factory, $user] = $this->actor();
        $eastern = $this->product($this->category('eastern-sweets', 'حلويات شرقية', 'Eastern Sweets'), 'بقلاوة');
        $kunafa = $this->product($this->category('kunafa-varieties', 'كنافة بأنواعها', 'Kunafa varieties'), 'كنافة جبنة');
        $cakes = $this->product($this->category('cakes', 'كيك', 'Cakes'), 'كنافة كيك');

        $url = route('showroom-sweets-requests.store');
        $payload = [
            'factory_location_id' => $factory->id,
            'items' => [
                ['product_id' => $eastern->id, 'quantity' => 2, 'requested_unit' => 'صدر'],
                ['product_id' => $kunafa->id, 'quantity' => 3, 'requested_unit' => 'صينية'],
            ],
        ];
        $this->actingAs($user)->post($url, $payload)->assertSessionHasNoErrors();
        $order = ShowroomSweetsRequest::query()->sole();
        $this->assertEqualsCanonicalizing([$eastern->id, $kunafa->id], $order->items()->pluck('product_id')->all());

        $this->actingAs($user)->put(route('showroom-sweets-requests.update', $order), [
            'factory_location_id' => $factory->id,
            'items' => [['product_id' => $kunafa->id, 'quantity' => 4, 'requested_unit' => 'صينية']],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$kunafa->id], $order->items()->pluck('product_id')->all());

        $this->actingAs($user)->post($url, [
            'factory_location_id' => $factory->id,
            'items' => [['product_id' => $cakes->id, 'quantity' => 1, 'requested_unit' => 'حبة']],
        ])->assertSessionHasErrors('items');
        $this->assertSame(1, ShowroomSweetsRequest::query()->count());

        $this->actingAs($user)->put(route('showroom-sweets-requests.update', $order), [
            'factory_location_id' => $factory->id,
            'items' => [['product_id' => $cakes->id, 'quantity' => 1, 'requested_unit' => 'حبة']],
        ])->assertSessionHasErrors('items');
        $this->assertEqualsCanonicalizing([$kunafa->id], $order->items()->pluck('product_id')->all());
    }

    private function actor(): array
    {
        $branch = Location::query()->create([
            'name' => 'فرع طلب الكنافة', 'code' => 'KNB-'.Str::upper(Str::random(7)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'مصنع الكنافة', 'code' => 'KNF-'.Str::upper(Str::random(7)),
            'type' => 'factory', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['showroom_sweets_requests.create', 'showroom_sweets_requests.view'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $factory, $user->fresh()];
    }

    private function category(string $slug, string $ar, string $english, bool $active = true): Category
    {
        return Category::query()->create([
            'slug' => $slug, 'name' => $english, 'name_ar' => $ar, 'is_active' => $active,
        ]);
    }

    private function product(Category $category, string $name, bool $raw = false): Product
    {
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name, 'name_ar' => $name,
            'sku' => 'KNF-'.Str::upper(Str::random(12)),
            'barcode' => 'KNF-'.Str::upper(Str::random(12)),
            'unit_id' => $unit->id, 'unit' => $unit->code,
            'product_type' => 'standard', 'is_active' => true, 'is_raw_material' => $raw,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductChronologicalOrderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function products_are_always_oldest_first_without_reapplying_a_sort_filter(): void
    {
        [$user, $branch, $category, $unit] = $this->setupCatalog();

        $newest = $this->product('ORDERING-NEWEST', $branch, $category, $unit, '2026-10-17 12:00:00');
        $oldest = $this->product('ORDERING-OLDEST', $branch, $category, $unit, '2026-10-10 12:00:00');
        $middle = $this->product('ORDERING-MIDDLE', $branch, $category, $unit, '2026-10-13 12:00:00');

        $this->actingAs($user)->get(route('products.index'))
            ->assertOk()
            ->assertSeeInOrder([$oldest->name, $middle->name, $newest->name])
            ->assertDontSee('name="sort"', false);

        // A bookmarked former sort URL must no longer change the automatic order.
        $this->actingAs($user)->get(route('products.index', ['sort' => 'newest']))
            ->assertOk()
            ->assertSeeInOrder([$oldest->name, $middle->name, $newest->name]);
    }

    #[Test]
    public function search_and_pagination_preserve_ascending_creation_order(): void
    {
        [$user, $branch, $category, $unit] = $this->setupCatalog();
        $items = [];
        foreach (range(1, 26) as $i) {
            $items[] = $this->product(
                sprintf('ORDERING-BATCH-%02d', $i),
                $branch,
                $category,
                $unit,
                '2026-10-01 09:00:00'
            );
        }

        // Same creation timestamp: ascending ID breaks ties deterministically.
        $first = $this->actingAs($user)->get(route('products.index', ['q' => 'ORDERING-BATCH']));
        $first->assertOk()
            ->assertSeeInOrder([$items[0]->name, $items[24]->name])
            ->assertDontSee($items[25]->name);

        $second = $this->actingAs($user)->get(route('products.index', [
            'q' => 'ORDERING-BATCH', 'page' => 2, 'sort' => 'za',
        ]));
        $second->assertOk()->assertSee($items[25]->name);
        $second->assertDontSee('name="sort"', false);
    }

    #[Test]
    public function filtered_product_list_context_survives_view_and_edit_navigation(): void
    {
        [$user, $branch, $category, $unit] = $this->setupCatalog();
        $user->givePermissionTo(Permission::findOrCreate('products.update', 'web'));
        $product = $this->product('FILTER-PERSIST', $branch, $category, $unit, '2026-10-12 12:00:00');
        $filters = ['q' => 'FILTER-PERSIST', 'category_id' => $category->id, 'status' => 'active', 'page' => 1];
        $returnQuery = http_build_query($filters);

        $this->actingAs($user)->get(route('products.index', $filters))
            ->assertOk()
            ->assertSee(route('products.show', [$product, 'return_query' => $returnQuery]))
            ->assertSee(route('products.edit', [$product, 'return_query' => $returnQuery]));

        $this->actingAs($user)->get(route('products.show', [$product, 'return_query' => $returnQuery]))
            ->assertOk()
            ->assertSee(route('products.index') . '?' . $returnQuery);

        $this->actingAs($user)->get(route('products.edit', [$product, 'return_query' => $returnQuery]))
            ->assertOk()
            ->assertSee('name="return_query"', false)
            ->assertSee(route('products.show', [$product, 'return_query' => $returnQuery]));
    }

    /** @return array{User, Location, Category, Unit} */
    private function setupCatalog(): array
    {
        $branch = Location::query()->create([
            'name' => 'Chronological branch',
            'code' => 'SORT-'.Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user->givePermissionTo(Permission::findOrCreate('products.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $category = Category::query()->create([
            'name' => 'Order test products',
            'slug' => 'chronological-products',
            'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();

        return [$user->fresh(), $branch, $category, $unit];
    }

    private function product(string $name, Location $branch, Category $category, Unit $unit, string $createdAt): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'unit' => $unit->code,
            'product_type' => 'standard',
            'sku' => 'SORT-'.Str::upper(Str::random(14)),
            'barcode' => 'SORT-'.Str::upper(Str::random(14)),
            'is_active' => true,
        ]);
        $product->forceFill(['created_at' => $createdAt])->save();
        LocationProduct::query()->create([
            'location_id' => $branch->id,
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        return $product;
    }
}

<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowroomSweetsProductChronologicalOrderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_sweets_product_picker_lists_oldest_created_first_even_if_alphabetical_order_differs(): void
    {
        $this->authorizeBranchOrderCreation();
        $category = $this->category();

        $newest = $this->product('SORT-SWEETS-A-NEWEST', $category, '2026-10-20 12:00:00');
        $middle = $this->product('SORT-SWEETS-M-MIDDLE', $category, '2026-10-17 12:00:00');
        $oldest = $this->product('SORT-SWEETS-Z-OLDEST', $category, '2026-10-10 12:00:00');

        $response = $this->getJson(route('showroom-sweets-requests.products', ['q' => 'SORT-SWEETS']));
        $response->assertOk()->assertJsonCount(3);
        $this->assertSame(
            [$oldest->id, $middle->id, $newest->id],
            array_column($response->json(), 'id')
        );
    }

    #[Test]
    public function search_keeps_creation_order_and_uses_ascending_id_for_equal_timestamps(): void
    {
        $this->authorizeBranchOrderCreation();
        $category = $this->category();

        $first = $this->product('ORDER-TIE-Z', $category, '2026-10-10 12:00:00');
        $second = $this->product('ORDER-TIE-A', $category, '2026-10-10 12:00:00');

        $response = $this->getJson(route('showroom-sweets-requests.products', ['q' => 'ORDER-TIE']));
        $response->assertOk()->assertJsonCount(2);
        $this->assertSame([$first->id, $second->id], array_column($response->json(), 'id'));
    }

    #[Test]
    public function raw_materials_are_not_shown_in_the_sweets_product_picker(): void
    {
        $this->authorizeBranchOrderCreation();
        $category = $this->category();

        $raw = $this->product('SWEETS-RAW-ONLY', $category, '2026-10-01 12:00:00', true);
        $sweet = $this->product('SWEETS-READY', $category, '2026-10-20 12:00:00');

        $response = $this->getJson(route('showroom-sweets-requests.products'));
        $response->assertOk();
        $ids = array_column($response->json(), 'id');
        $this->assertNotContains($raw->id, $ids);
        $this->assertContains($sweet->id, $ids);
    }

    private function authorizeBranchOrderCreation(): void
    {
        $branch = Location::query()->create([
            'name' => 'Sweets picker branch',
            'code' => 'SSR-'.Str::upper(Str::random(8)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user->givePermissionTo(Permission::findOrCreate('showroom_sweets_requests.create', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh());
    }

    private function category(): Category
    {
        return Category::query()->create([
            'slug' => 'eastern-sweets-products',
            'name' => 'Eastern Sweets',
            'name_ar' => 'حلويات شرقية',
            'is_active' => true,
        ]);
    }

    private function product(string $name, Category $category, string $createdAt, bool $raw = false): Product
    {
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'sku' => 'SSR-SORT-'.Str::upper(Str::random(12)),
            'barcode' => 'SSR-SORT-'.Str::upper(Str::random(12)),
            'unit_id' => $unit->id,
            'unit' => $unit->code,
            'product_type' => 'standard',
            'is_active' => true,
            'is_raw_material' => $raw,
        ]);
        $product->forceFill(['created_at' => $createdAt])->save();

        return $product;
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TishBranchStockRequestTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $code): Location
    {
        return Location::create([
            'name' => $code, 'code' => $code, 'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->givePermissionTo(Permission::findOrCreate('stock_requests.create', 'web'));
        $user->assignRole($admin);

        return $user;
    }

    public function test_new_stock_request_form_opens_without_any_factory(): void
    {
        $this->branch('BRANCH_A');
        $this->branch('BRANCH_B');

        $this->actingAs($this->admin())
            ->get(route('stock-requests.create'))
            ->assertOk()
            ->assertSee('فرع التوريد')
            ->assertSee('الفرع المستلم');
    }

    public function test_same_branch_cannot_supply_itself(): void
    {
        $branch = $this->branch('BRANCH_A');
        $this->branch('BRANCH_B');

        $this->actingAs($this->admin())
            ->post(route('stock-requests.store'), [
                'branch_id' => $branch->id,
                'source_location_id' => $branch->id,
                'items' => [['product_id' => 999, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('source_location_id');

        $this->assertDatabaseCount('stock_requests', 0);
    }

    public function test_inactive_supply_branch_cannot_be_selected(): void
    {
        $destination = $this->branch('BRANCH_A');
        $source = $this->branch('BRANCH_B');
        $source->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->post(route('stock-requests.store'), [
                'branch_id' => $destination->id,
                'source_location_id' => $source->id,
                'items' => [['product_id' => 999, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('source_location_id');

        $this->assertDatabaseCount('stock_requests', 0);
    }

    private function ingredient(): Product
    {
        $category = Category::create([
            'name' => 'Restaurant ingredients', 'slug' => 'tish-ingredients',
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Tomatoes', 'sku' => 'TISH-TOMATO', 'barcode' => 'TISH-TOMATO',
            'unit' => 'kg', 'product_type' => 'standard',
            'base_selling_price' => 0,
            'is_active' => true, 'is_raw_material' => true,
        ]);
    }

    public function test_branch_user_cannot_forge_another_receiving_branch(): void
    {
        $destination = $this->branch('BRANCH_A');
        $source = $this->branch('BRANCH_B');
        $forged = $this->branch('BRANCH_C');
        $ingredient = $this->ingredient();
        $user = User::factory()->create();
        $user->employee->locations()->attach($destination->id, ['is_primary' => true]);
        $user->givePermissionTo(Permission::findOrCreate('stock_requests.create', 'web'));

        $this->actingAs($user)->post(route('stock-requests.store'), [
            'branch_id' => $forged->id,
            'source_location_id' => $source->id,
            'items' => [['product_id' => $ingredient->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('stock_requests', [
            'branch_location_id' => $destination->id,
            'factory_location_id' => $source->id,
            'status' => 'pending_factory_review',
        ]);
        $this->assertDatabaseMissing('stock_requests', [
            'branch_location_id' => $forged->id,
        ]);
    }
}

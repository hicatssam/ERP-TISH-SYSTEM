<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductCreateModalCloseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_creation_modal_has_accessible_hash_fallback_for_both_close_actions(): void
    {
        $branch = Location::query()->create([
            'name' => 'Modal Test Branch',
            'code' => 'MODAL-TEST',
            'type' => 'branch',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        foreach (['products.view', 'products.create'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('id="productsPageHeader"', false)
            ->assertSee('href="#productCreateModal"', false)
            ->assertSee('id="closeProductCreateModalButton"', false)
            ->assertSee('id="cancelProductCreateModalButton"', false)
            ->assertSee('href="#productsPageHeader"', false)
            ->assertSee('return closeProductCreateModal()', false);
    }
}

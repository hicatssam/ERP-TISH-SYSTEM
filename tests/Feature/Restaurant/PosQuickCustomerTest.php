<?php

namespace Tests\Feature\Restaurant;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PosQuickCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_add_customer_to_the_current_branch_and_reject_duplicate_phone(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::create([
            'name' => 'POS quick customer',
            'code' => 'POSQUICK',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'customers.create'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $payload = ['name' => 'New POS Customer', 'phone' => '0599000111', 'location_id' => $branch->id];
        $this->actingAs($user)->postJson(route('restaurant.pos.customers.quick-store'), $payload)
            ->assertCreated()
            ->assertJsonPath('customer.name', $payload['name'])
            ->assertJsonPath('customer.phone', $payload['phone']);

        $customer = Customer::where('phone', $payload['phone'])->firstOrFail();
        $this->assertSame($branch->id, $customer->location_id);
        $this->assertSame(Customer::SCOPE_BRANCH, $customer->scope);
        $this->assertFalse($customer->allow_credit);

        $this->actingAs($user)->postJson(route('restaurant.pos.customers.quick-store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
        $this->assertSame(1, Customer::where('phone', $payload['phone'])->count());
    }

    public function test_customer_creation_requires_separate_customers_create_permission(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::create([
            'name' => 'POS restricted',
            'code' => 'POSRESTRICTED',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['restaurant.view', 'restaurant_pos.use'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($user)->postJson(route('restaurant.pos.customers.quick-store'), [
            'name' => 'Should Not Be Created',
            'phone' => '0599000222',
            'location_id' => $branch->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('customers', ['phone' => '0599000222']);
    }
}

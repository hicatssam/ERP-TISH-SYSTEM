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
    public function test_pos_can_save_complete_company_profile_with_branch_scope(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::create([
            'name' => 'Company POS branch', 'code' => 'COMPANYPOS',
            'type' => 'branch', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'customers.create'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $payload = [
            'location_id' => $branch->id,
            'customer_type' => 'company',
            'name' => 'Tish Partners Ltd',
            'phone' => '0599000333',
            'secondary_phone' => '0599000444',
            'contact_person' => 'Purchasing Manager',
            'tax_number' => 'TAX-111',
            'address' => 'Main Street',
            'notes' => 'Deliver on weekdays',
        ];

        $this->actingAs($user)->postJson(route('restaurant.pos.customers.quick-store'), $payload)
            ->assertCreated()
            ->assertJsonPath('customer.customer_type', 'company')
            ->assertJsonPath('customer.scope', Customer::SCOPE_BRANCH);

        $this->assertDatabaseHas('customers', [
            'location_id' => $branch->id,
            'customer_type' => 'company',
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'secondary_phone' => $payload['secondary_phone'],
            'contact_person' => $payload['contact_person'],
            'tax_number' => $payload['tax_number'],
            'address' => $payload['address'],
            'notes' => $payload['notes'],
            'scope' => Customer::SCOPE_BRANCH,
            'allow_credit' => 0,
        ]);
    }

    public function test_pos_does_not_allow_cashier_to_grant_company_credit_or_global_scope(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::create([
            'name' => 'Restricted credit branch', 'code' => 'CREDITPOS',
            'type' => 'branch', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'customers.create'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $base = [
            'location_id' => $branch->id, 'customer_type' => 'company',
            'name' => 'Restricted POS Company', 'phone' => '0599000555',
        ];
        foreach ([['allow_credit' => true], ['scope' => 'global'], ['credit_limit' => 500]] as $forbidden) {
            $this->actingAs($user)->postJson(
                route('restaurant.pos.customers.quick-store'), array_merge($base, $forbidden)
            )->assertUnprocessable()->assertJsonValidationErrors('scope');
        }
        $this->assertDatabaseMissing('customers', ['phone' => $base['phone']]);
    }

    public function test_pos_central_manager_can_create_selected_branch_credit_company(): void
    {
        $this->withoutMiddleware(EnsureModuleEnabled::class);
        $branch = Location::create([
            'name' => 'Credit home', 'code' => 'CREDITHOME',
            'type' => 'branch', 'is_active' => true,
        ]);
        $other = Location::create([
            'name' => 'Credit destination', 'code' => 'CREDITOTHER',
            'type' => 'branch', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['restaurant.view', 'restaurant_pos.use', 'customers.create', 'customers.view_all'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($user)->postJson(route('restaurant.pos.customers.quick-store'), [
            'location_id' => $branch->id, 'customer_type' => 'company',
            'name' => 'Credit POS Company', 'phone' => '0599000666',
            'scope' => 'selected', 'location_ids' => [$other->id],
            'allow_credit' => true, 'credit_limit' => 500,
            'billing_cycle' => 'monthly', 'payment_terms_days' => 30,
        ])->assertCreated()->assertJsonPath('customer.scope', 'selected');

        $customer = Customer::query()->where('phone', '0599000666')->firstOrFail();
        $this->assertTrue($customer->allow_credit);
        $this->assertSame('monthly', $customer->billing_cycle);
        $this->assertEquals(500, $customer->credit_limit);
        $this->assertSame(30, $customer->payment_terms_days);
        $this->assertTrue($customer->isAvailableAt($branch->id));
        $this->assertTrue($customer->isAvailableAt($other->id));
    }

}

<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Models\SpecialCakeOrder;
use App\Models\Location;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CakeOrderArchivingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function administrator_can_archive_and_restore_cancelled_cake_without_deleting_it(): void
    {
        $admin = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('cake_orders.delete', 'web'));
        $admin->givePermissionTo(Permission::findOrCreate('cake_orders.view', 'web'));
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Admin', 'web'));

        $branch = Location::query()->create(['name' => 'Archive branch', 'code' => 'AR-TEST', 'type' => 'branch', 'is_active' => true]);
        $customer = Customer::query()->create(['location_id' => $branch->id, 'customer_type' => Customer::TYPE_INDIVIDUAL, 'scope' => Customer::SCOPE_BRANCH, 'name' => 'Archive customer', 'phone' => '0590000000']);

        $order = SpecialCakeOrder::query()->create([
            'customer_id' => $customer->id,
            'origin_branch_id' => $branch->id,
            'required_date' => now()->addDay()->toDateString(),
            'created_by' => $admin->id,
            'order_number' => 'CAKE-ARCHIVE-TEST',
            'status' => CakeOrderStatus::Cancelled,
        ]);

        $this->actingAs($admin)
            ->post(route('cake-orders.archive', $order))
            ->assertRedirect();

        $this->assertNotNull($order->fresh()->archived_at);
        $this->assertNull($order->fresh()->deleted_at);

        $this->actingAs($admin)
            ->post(route('cake-orders.restore-archive', $order))
            ->assertRedirect(route('cake-orders.show', $order));

        $this->assertNull($order->fresh()->archived_at);
        $this->assertSame(CakeOrderStatus::Cancelled, $order->fresh()->status);
    }
}

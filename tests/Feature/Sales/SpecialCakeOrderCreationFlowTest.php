<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\LocationPaymentMethod;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Enums\CakeOrderStatus;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeOrderCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_can_create_cash_cake_order_and_factory_receives_execution_notification(): void
    {
        Notification::fake();

        $branch = Location::query()->create([
            'name' => 'فرع إنشاء الكيك',
            'code' => 'CKBR-' . Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع إنشاء الكيك',
            'code' => 'CKFA-' . Str::upper(Str::random(5)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $creator = $this->userAt($branch, [
            'cake_orders.create',
            'cake_orders.view',
        ]);

        $factoryPreparer = $this->userAt($factory, [
            'cake_orders.view',
            'cake_orders.prepare',
        ]);

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'عميل إنشاء طلب كيك',
            'phone' => '0592345678',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        $cash = PaymentMethod::query()->create([
            'name' => 'Cash',
            'name_ar' => 'نقدي',
            'code' => 'cash-test-' . Str::lower(Str::random(6)),
            'type' => 'cash',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LocationPaymentMethod::query()->create([
            'location_id' => $branch->id,
            'payment_method_id' => $cash->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($creator)
            ->post(route('cake-orders.store'), [
                'customer_id' => $customer->id,
                'required_date' => now()->addDays(3)->toDateString(),
                'required_time' => '15:30',
                'cake_type' => 'Birthday',
                'cake_size' => 'Large',
                'total_price' => 120,
                'payment_arrangement' => 'pay_now',
                'payment_method_id' => $cash->id,
                'discount_type' => 'none',
                'discount_value' => 0,
                'image_cover_type' => 'none',
            ]);

        $order = SpecialCakeOrder::query()->sole();

        $response
            ->assertRedirect(route('cake-orders.show', $order))
            ->assertSessionHas(
                'success',
                'تم إنشاء طلب الكيك واعتماده تلقائيًا وبدأ التنفيذ.'
            );

        $order->refresh();

        $this->assertSame(
            'in_progress',
            $order->status->value
        );

        $this->assertSame(
            'pay_on_pickup',
            $order->payment_arrangement->value
        );

        $this->assertSame(
            $branch->id,
            $order->origin_branch_id
        );

        $this->assertSame(
            $factory->id,
            $order->factory_location_id
        );

        $this->assertDatabaseHas('cake_order_status_histories', [
            'special_cake_order_id' => $order->id,
            'from_status' => 'draft',
            'to_status' => 'in_progress',
            'changed_by' => $creator->id,
        ]);

        $this->assertSame(
            0,
            Payment::query()
                ->where('order_type', 'special_cake_order')
                ->where('order_id', $order->id)
                ->count()
        );

        Notification::assertSentTo(
            $factoryPreparer,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryPreparer)['to_status']
                === 'in_progress'
        );

        Notification::assertNotSentTo(
            $creator,
            SpecialCakeOrderTransitionedNotification::class
        );
    }

    #[Test]
    public function active_branch_payment_accounts_show_and_work_without_a_legacy_method_link(): void
    {
        Notification::fake();

        $branch = Location::query()->create([
            'name' => 'Branch with configured accounts',
            'code' => 'CKBA-' . Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $otherBranch = Location::query()->create([
            'name' => 'Other branch accounts',
            'code' => 'CKBO-' . Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        Location::query()->create([
            'name' => 'Cake factory',
            'code' => 'CKBF-' . Str::upper(Str::random(5)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $creator = $this->userAt($branch, [
            'cake_orders.create',
            'cake_orders.view',
        ]);
        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Cake account customer',
            'phone' => '0592345678',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        $makeMethod = static fn (string $name): PaymentMethod => PaymentMethod::query()->create([
            'name' => $name,
            'name_ar' => $name,
            'code' => 'cake-' . Str::lower(Str::random(10)),
            'type' => 'bank_transfer',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $available = $makeMethod('Available branch bank');
        $foreign = $makeMethod('Another branch bank');
        $inactive = $makeMethod('Inactive branch bank');

        LocationPaymentAccount::query()->create([
            'location_id' => $branch->id,
            'payment_method_id' => $available->id,
            'name' => 'Configured local account',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        LocationPaymentAccount::query()->create([
            'location_id' => $otherBranch->id,
            'payment_method_id' => $foreign->id,
            'name' => 'Foreign account',
            'is_active' => true,
        ]);
        LocationPaymentAccount::query()->create([
            'location_id' => $branch->id,
            'payment_method_id' => $inactive->id,
            'name' => 'Disabled account',
            'is_active' => false,
        ]);

        $page = $this->actingAs($creator)->get(route('cake-orders.create'));
        $page->assertOk()
            ->assertSee('Available branch bank')
            ->assertSee('Configured local account')
            ->assertDontSee('Another branch bank')
            ->assertDontSee('Inactive branch bank');

        $payload = [
            'customer_id' => $customer->id,
            'required_date' => now()->addDays(3)->toDateString(),
            'required_time' => '15:30',
            'cake_type' => 'Birthday',
            'cake_size' => 'Large',
            'total_price' => 120,
            'payment_arrangement' => 'pay_on_pickup',
            'payment_method_id' => $available->id,
            'discount_type' => 'none',
            'discount_value' => 0,
            'image_cover_type' => 'none',
        ];

        $this->actingAs($creator)
            ->post(route('cake-orders.store'), $payload)
            ->assertRedirect(route('cake-orders.show', SpecialCakeOrder::query()->sole()));

        $this->actingAs($creator)
            ->post(route('cake-orders.store'), array_replace($payload, [
                'payment_method_id' => $inactive->id,
            ]))
            ->assertSessionHasErrors('payment_method_id');

        $this->actingAs($creator)
            ->post(route('cake-orders.store'), array_replace($payload, [
                'payment_method_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('payment_method_id');

        $this->assertSame(1, SpecialCakeOrder::query()->count());
    }

    #[Test]
    public function special_cake_can_store_and_edit_multiple_sizes_while_preserving_legacy_primary_size(): void
    {
        Notification::fake();

        $branch = Location::query()->create([
            'name' => 'Multi size branch',
            'code' => 'MSBR-' . Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'Multi size factory',
            'code' => 'MSFA-' . Str::upper(Str::random(5)),
            'type' => 'factory',
            'is_active' => true,
        ]);
        $creator = $this->userAt($branch, [
            'cake_orders.create',
            'cake_orders.view',
            'cake_orders.edit',
        ]);
        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Multiple Sizes Customer',
            'phone' => '0592345601',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
        $cash = PaymentMethod::query()->create([
            'name' => 'Cash',
            'name_ar' => 'نقدي',
            'code' => 'cash-multi-' . Str::lower(Str::random(5)),
            'type' => 'cash',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        LocationPaymentMethod::query()->create([
            'location_id' => $branch->id,
            'payment_method_id' => $cash->id,
            'is_active' => true,
        ]);

        $this->actingAs($creator)
            ->get(route('cake-orders.create'))
            ->assertOk()
            ->assertSee('cake_sizes_submitted')
            ->assertSee('cake_sizes[]');

        $this->actingAs($creator)
            ->post(route('cake-orders.store'), [
                'customer_id' => $customer->id,
                'required_date' => now()->addDays(3)->toDateString(),
                'required_time' => '15:30',
                'cake_type' => 'chocolate',
                'shape' => 'round',
                'cake_sizes_submitted' => 1,
                'cake_sizes' => [],
                'total_price' => 150,
                'payment_arrangement' => 'pay_on_pickup',
                'payment_method_id' => $cash->id,
                'discount_type' => 'none',
                'discount_value' => 0,
                'image_cover_type' => 'none',
            ])->assertSessionHasErrors(['cake_sizes']);

        $this->actingAs($creator)
            ->post(route('cake-orders.store'), [
                'customer_id' => $customer->id,
                'required_date' => now()->addDays(3)->toDateString(),
                'required_time' => '15:30',
                'cake_type' => 'chocolate',
                'shape' => 'round',
                'cake_sizes_submitted' => 1,
                'cake_sizes' => ['small', 'large'],
                'cake_size' => 'large',
                'total_price' => 150,
                'payment_arrangement' => 'pay_on_pickup',
                'payment_method_id' => $cash->id,
                'discount_type' => 'none',
                'discount_value' => 0,
                'image_cover_type' => 'none',
            ])->assertSessionHasNoErrors();

        $order = SpecialCakeOrder::query()->sole();
        $this->assertSame(['small', 'large'], $order->cake_sizes);
        $this->assertSame('small', $order->cake_size);

        $this->actingAs($creator)
            ->get(route('cake-orders.show', $order))
            ->assertOk()
            ->assertSee('صغير، كبير');

        // The edit form is only available for pending or draft cake orders.
        $order->update(['status' => CakeOrderStatus::Pending]);

        $this->actingAs($creator)
            ->get(route('cake-orders.edit', $order))
            ->assertOk()
            ->assertSee('cake_sizes_submitted');

        $this->actingAs($creator)
            ->put(route('cake-orders.update', $order), [
                'required_date' => now()->addDays(4)->toDateString(),
                'required_time' => '15:30',
                'cake_type' => 'chocolate',
                'shape' => 'round',
                'cake_sizes_submitted' => 1,
                'cake_sizes' => ['medium', 'large'],
                'total_price' => 180,
            ])->assertSessionHasNoErrors();

        $this->assertSame(['medium', 'large'], $order->fresh()->cake_sizes);
        $this->assertSame('medium', $order->fresh()->cake_size);
    }

    private function userAt(
        Location $location,
        array $permissions
    ): User {
        $user = User::factory()->create();

        $user->employee->locations()->attach(
            $location->id,
            [
                'is_primary' => true,
            ]
        );

        foreach ($permissions as $name) {
            $user->givePermissionTo(
                Permission::findOrCreate($name, 'web')
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user->fresh();
    }
}

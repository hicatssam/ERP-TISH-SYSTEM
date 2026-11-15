<?php

namespace Tests\Feature;

use App\Enums\PaymentArrangement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Payments\PaymentCheckoutSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentCheckoutSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function missing_configuration_preserves_all_existing_options(): void
    {
        $policy = app(PaymentCheckoutSettings::class);

        $this->assertCount(count(PaymentArrangement::cases()), $policy->posOptions());
        $this->assertTrue($policy->customerCashEnabled());
        $this->assertTrue($policy->customerNonCashEnabled());
    }

    #[Test]
    public function authorized_admin_can_save_options_and_restricted_user_cannot(): void
    {
        $admin = User::factory()->create([
            'is_active' => true, 'must_change_password' => false,
        ]);
        $admin->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        $limited = User::factory()->create([
            'is_active' => true, 'must_change_password' => false,
        ]);

        $payload = [
            'pos_arrangements' => ['pay_now', 'pay_on_pickup'],
            'customer_cash' => '0',
            'customer_non_cash' => '1',
        ];

        $this->actingAs($limited)->post(route('settings.payment-checkout.update'), $payload)
            ->assertForbidden();

        $this->actingAs($admin)->get(route('settings.payment-checkout.index'))
            ->assertOk()->assertSee('إعدادات الدفع والطلبات');

        $this->actingAs($admin)->post(route('settings.payment-checkout.update'), $payload)
            ->assertRedirect(route('settings.payment-checkout.index'));

        $policy = app(PaymentCheckoutSettings::class);
        $this->assertSame(['pay_now', 'pay_on_pickup'],
            collect($policy->posOptions())->map(fn ($option) => $option->value)->all());
        $this->assertFalse($policy->customerCashEnabled());
        $this->assertTrue($policy->customerNonCashEnabled());
        $this->assertFalse($policy->permitsPos('on_account'));
        $this->assertFalse($policy->permitsCustomerMethodType('cash'));

        $this->actingAs($admin)->post(route('settings.payment-checkout.update'), [
            'pos_arrangements' => [],
            'customer_cash' => '1',
            'customer_non_cash' => '1',
        ])->assertSessionHasErrors('pos_arrangements');

        $this->assertSame('0', (string) SystemSetting::get('checkout_customer_cash_enabled'));
    }
}

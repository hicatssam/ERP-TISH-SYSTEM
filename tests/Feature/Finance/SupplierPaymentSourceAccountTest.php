<?php

namespace Tests\Feature\Finance;

use App\Models\Currency;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Procurement\SupplierPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierPaymentSourceAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_cash_payment_requires_matching_authorized_branch_account_and_records_recipient(): void
    {
        $this->seed(DatabaseSeeder::class);
        $actor = User::where('username', 'admin')->firstOrFail();
        $branch = Location::where('code', 'B01')->firstOrFail();
        $currency = Currency::where('code', 'ILS')->firstOrFail();
        $method = PaymentMethod::where('code', 'bank_of_palestine')->firstOrFail();

        $supplier = Supplier::create([
            'supplier_code' => 'TISH-SUP-TEST-SMALL',
            'name' => 'بسطة تجريبية',
            'currency_id' => $currency->id,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'TISH-SOURCE-TEST-1',
            'supplier_id' => $supplier->id,
            'location_id' => $branch->id,
            'currency_id' => $currency->id,
            'exchange_rate' => 1,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 100,
            'base_grand_total' => 100,
            'paid_amount' => 0,
            'credited_amount' => 0,
            'remaining_amount' => 100,
            'status' => 'unpaid',
            'created_by' => $actor->id,
        ]);
        $account = LocationPaymentAccount::create([
            'location_id' => $branch->id,
            'payment_method_id' => $method->id,
            'name' => 'بنك فلسطين — حساب فرع TISH التجريبي',
            'provider_name' => 'Bank of Palestine',
            'is_active' => true,
            'can_disburse' => false,
        ]);
        $data = [
            'supplier_id' => $supplier->id,
            'supplier_invoice_id' => $invoice->id,
            'location_id' => $branch->id,
            'currency_id' => $currency->id,
            'amount' => 40,
            'payment_date' => today()->toDateString(),
            'payment_method_id' => $method->id,
            'reference_number' => 'DEMO-BOP-TRX-001',
            'beneficiary_name' => 'بسطة تجريبية',
            'beneficiary_account' => 'DEMO-ACCOUNT-NOT-REAL',
        ];
        $service = app(SupplierPaymentService::class);
        foreach ([
            ['source_payment_account_id' => null],
            ['source_payment_account_id' => $account->id],
        ] as $variation) {
            try {
                $service->record(array_merge($data, $variation), $actor);
                $this->fail('Non-cash disbursement unexpectedly accepted without an enabled source account.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('source_payment_account_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('supplier_payments', 0);

        $account->update(['can_disburse' => true, 'is_customer_visible' => false]);
        // The public menu must not reveal a treasury-only source account.
        $this->get('/menu/B01/payment-options')->assertOk()
            ->assertDontSee('بنك فلسطين — حساب فرع TISH التجريبي');
        $payment = $service->record($data + ['source_payment_account_id' => $account->id], $actor);
        $this->assertSame($account->id, $payment->source_payment_account_id);
        $this->assertSame('بسطة تجريبية', $payment->beneficiary_name);
        $this->assertSame('DEMO-BOP-TRX-001', $payment->reference_number);
        $this->assertEqualsWithDelta(60, (float) $invoice->fresh()->remaining_amount, .02);
        $this->assertSame(1, SupplierPayment::where('source_payment_account_id', $account->id)->count());
    }
}

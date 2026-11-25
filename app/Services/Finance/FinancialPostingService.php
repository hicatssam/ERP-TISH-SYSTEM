<?php

namespace App\Services\Finance;

use App\Enums\LedgerEntryType;
use App\Models\FinancialPeriod;
use App\Models\EmployeePurchase;
use App\Models\EmployeePurchaseReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FinancialPostingService
{
    public function __construct(
        private readonly FinancialPeriodResolver $periods,
        private readonly OperationalJournalService $journals,
    ) {}

    public function sale(Invoice $invoice, User $actor): void
    {
        $this->post(
            key: "invoice:{$invoice->id}:sale",
            type: LedgerEntryType::Sale,
            amount: (float) $invoice->total_amount,
            date: $invoice->issued_at ?? now(),
            locationId: (int) $invoice->location_id,
            actorId: $actor->id,
            referenceType: 'invoices',
            referenceId: $invoice->id,
            invoiceId: $invoice->id,
            description: "فاتورة {$invoice->invoice_number}",
        );
    }

    /**
     * A confirmed order can be edited without issuing a new invoice. Post only
     * the difference; never overwrite a historical sale journal.
     *
     * The caller must hold the order lock and invoke this in its transaction.
     */
    public function saleAdjustment(Invoice $invoice, float $previousTotal, User $actor): void
    {
        $delta = round((float) $invoice->total_amount - $previousTotal, 2);
        if (abs($delta) < 0.005) {
            return;
        }

        $this->post(
            key: "invoice:{$invoice->id}:adjustment:".Str::uuid(),
            type: $delta > 0 ? LedgerEntryType::Sale : LedgerEntryType::SaleCancellation,
            amount: abs($delta),
            date: now(),
            locationId: (int) $invoice->location_id,
            actorId: $actor->id,
            referenceType: 'invoices',
            referenceId: $invoice->id,
            invoiceId: $invoice->id,
            description: "تسوية قيمة الفاتورة {$invoice->invoice_number}",
        );
    }

    public function collection(Payment $payment, User $actor, ?string $keySuffix = null, ?float $amount = null): void
    {
        $this->post(
            key: "payment:{$payment->id}:collection".($keySuffix ? ":{$keySuffix}" : ''),
            type: LedgerEntryType::PaymentCollection,
            amount: $amount ?? (float) $payment->amount,
            date: $keySuffix ? now() : ($payment->paid_at ?? now()),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'payments',
            referenceId: $payment->id,
            orderId: $payment->orderTypeValue() === 'order' ? (int) $payment->order_id : null,
            specialCakeOrderId: $payment->orderTypeValue() === 'special_cake_order' ? (int) $payment->order_id : null,
            description: "تحصيل دفعة #{$payment->id}",
        );
    }

    /**
     * Move pre-invoice customer advances into receivables when the sale is posted.
     * This never touches the bank again and is idempotent per invoice.
     * Must be called in the same transaction as invoice issue and sale posting.
     */
    public function settleOrderAdvances(Invoice $invoice, User $actor): void
    {
        $sourceType = 'invoice_advance_settlements';
        if (DB::table('accounting_journals')->where('source_type', $sourceType)
            ->where('source_id', $invoice->id)->exists()) {
            return;
        }

        $orderType = $invoice->order_type instanceof \BackedEnum
            ? $invoice->order_type->value : (string) $invoice->order_type;
        $column = $orderType === 'order' ? 'order_id' : 'special_cake_order_id';
        if (! in_array($orderType, ['order', 'special_cake_order'], true)) {
            return;
        }

        $advance = DB::table('accounting_journal_lines as line')
            ->join('accounting_accounts as account', 'account.id', '=', 'line.accounting_account_id')
            ->join('accounting_journals as journal', 'journal.id', '=', 'line.accounting_journal_id')
            ->join('sales_ledger_entries as entry', function ($join) {
                $join->on('entry.id', '=', 'journal.source_id')
                    ->where('journal.source_type', '=', 'sales_ledger_entries');
            })
            ->where('account.code', '2400')
            ->where('entry.'.$column, $invoice->order_id)
            ->where('entry.location_id', $invoice->location_id)
            ->selectRaw('COALESCE(SUM(line.credit - line.debit), 0) as amount')
            ->value('amount');

        $amount = round(min((float) $invoice->total_amount, max(0, (float) $advance)), 2);
        if ($amount <= 0) {
            return;
        }

        $accounts = DB::table('accounting_accounts')->whereIn('code', ['1100', '2400'])
            ->where('is_active', true)->pluck('id', 'code');
        if (! isset($accounts['1100'], $accounts['2400'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'accounting' => 'حسابات تسوية الدفعات المقدمة غير جاهزة.',
            ]);
        }

        app(AccountingBookService::class)->postSourceEntry(
            $sourceType, (int) $invoice->id, (int) $invoice->location_id,
            $invoice->issued_at->toDateString(),
            "تسوية دفعات مقدمة للفاتورة {$invoice->invoice_number}",
            [
                ['account_id' => $accounts['2400'], 'debit' => $amount, 'credit' => 0],
                ['account_id' => $accounts['1100'], 'debit' => 0, 'credit' => $amount],
            ],
            $actor
        );
    }

    public function paymentReversal(Payment $payment, User $actor, string $keySuffix, float $amount): void
    {
        $this->post(
            key: "payment:{$payment->id}:reversal:{$keySuffix}",
            type: LedgerEntryType::PaymentReversal,
            amount: $amount,
            date: now(),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'payments',
            referenceId: $payment->id,
            description: "عكس/تصحيح دفعة #{$payment->id}",
        );
    }

    public function refund(Refund $refund, Payment $payment, User $actor): void
    {
        $this->post(
            key: "refund:{$refund->id}",
            type: LedgerEntryType::Refund,
            amount: (float) $refund->amount,
            date: $refund->processed_at ?? now(),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'refunds',
            referenceId: $refund->id,
            orderId: $payment->orderTypeValue() === 'order' ? (int) $payment->order_id : null,
            specialCakeOrderId: $payment->orderTypeValue() === 'special_cake_order' ? (int) $payment->order_id : null,
            description: "استرداد دفعة #{$payment->id}",
        );
    }

    public function saleCancellation(Invoice $invoice, User $actor): void
    {
        $this->post(
            key: "invoice:{$invoice->id}:cancel",
            type: LedgerEntryType::SaleCancellation,
            amount: (float) $invoice->total_amount,
            date: now(),
            locationId: (int) $invoice->location_id,
            actorId: $actor->id,
            referenceType: 'invoices',
            referenceId: $invoice->id,
            invoiceId: $invoice->id,
            description: "إلغاء فاتورة {$invoice->invoice_number}",
        );
    }

    public function employeePurchase(EmployeePurchase $purchase, User $actor): void
    {
        $this->post(
            key: "employee-purchase:{$purchase->id}:sale",
            type: LedgerEntryType::EmployeePurchase,
            amount: (float) $purchase->total_amount,
            date: $purchase->purchased_at,
            locationId: (int) $purchase->location_id,
            actorId: $actor->id,
            referenceType: 'employee_purchases',
            referenceId: $purchase->id,
            description: "مشتريات الموظف {$purchase->number}",
            currencyCode: $purchase->currency->code,
        );
    }

    public function employeeCollection(EmployeePurchaseReceipt $receipt, User $actor): void
    {
        $purchase = $receipt->purchase;
        $this->post(
            key: "employee-purchase-receipt:{$receipt->id}",
            type: LedgerEntryType::EmployeeCollection,
            amount: (float) $receipt->amount,
            date: $receipt->posted_at,
            locationId: (int) $receipt->location_id,
            actorId: $actor->id,
            referenceType: 'employee_purchase_receipts',
            referenceId: $receipt->id,
            description: "سداد مشتريات الموظف {$purchase->number}",
            currencyCode: $purchase->currency->code,
        );
    }

    private function post(
        string $key,
        LedgerEntryType $type,
        float $amount,
        CarbonInterface|string $date,
        int $locationId,
        int $actorId,
        string $referenceType,
        int $referenceId,
        ?int $invoiceId = null,
        ?int $orderId = null,
        ?int $specialCakeOrderId = null,
        ?string $description = null,
        string $currencyCode = 'ILS',
    ): void {
        if ($amount <= 0) {
            return;
        }
        if (! Schema::hasTable('sales_ledger_entries') || ! Schema::hasTable('accounting_journals')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'accounting' => 'دفاتر المحاسبة غير متاحة؛ لا يمكن تأكيد الحركة المالية.',
            ]);
        }

        DB::transaction(function () use (
            $date, $key, $type, $amount, $locationId, $actorId, $referenceType,
            $referenceId, $invoiceId, $orderId, $specialCakeOrderId, $description, $currencyCode
        ): void {
            $existing = SalesLedgerEntry::query()->where('idempotency_key', $key)->first();
            // New operational sources must never be saved without a matching open period.
            // Existing idempotent retries retain their original period and journal.
            $periodId = $existing ? null : $this->periods->openForDate($date, true)->id;

            $entry = $existing ?? SalesLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'location_id' => $locationId,
                    'financial_period_id' => $periodId,
                    'entry_date' => $date instanceof CarbonInterface ? $date->toDateString() : $date,
                    'entry_type' => $type,
                    'amount' => round($amount, 2),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'invoice_id' => $invoiceId,
                    'order_id' => $orderId,
                    'special_cake_order_id' => $specialCakeOrderId,
                    'description' => $description,
                    'currency_code' => $currencyCode,
                    'created_by' => $actorId,
                    'created_at' => now(),
                ]
            );
            if (! $entry->wasRecentlyCreated && (
                (int) round((float) $entry->amount * 100) !== (int) round($amount * 100)
                || (int) $entry->location_id !== $locationId
                || $entry->currency_code !== $currencyCode
            )) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'تغيّرت بيانات حركة مالية مرحّلة؛ سجّل تسوية موثقة بدلاً من إعادة استخدامها.',
                ]);
            }
            $this->journals->post($entry, User::query()->findOrFail($actorId));
        });
    }
}

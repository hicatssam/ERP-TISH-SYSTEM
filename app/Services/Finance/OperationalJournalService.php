<?php

namespace App\Services\Finance;

use App\Enums\LedgerEntryType;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\Currency;
use App\Models\EmployeePurchaseReceipt;
use App\Models\Expense;
use App\Models\FinancialPeriod;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperationalJournalService
{
    public function __construct(private readonly AccountingBookService $books) {}

    /** Only known, base-currency transactions are eligible for automatic double entry. */
    public function post(SalesLedgerEntry $entry, User $actor): void
    {
        if (! Schema::hasTable('accounting_journals')) {
            throw ValidationException::withMessages([
                'accounting' => 'دفتر الأستاذ غير متاح؛ لا يمكن تأكيد حركة مالية دون قيد محاسبي.',
            ]);
        }
        $currency = Currency::query()->where('is_base', true)->where('is_active', true)->first();
        if (! $currency || $entry->currency_code !== $currency->code) {
            throw ValidationException::withMessages([
                'currency_code' => 'العملة الأساسية غير مهيأة أو عملة العملية غير مدعومة للترحيل.',
            ]);
        }
        if (! FinancialPeriod::query()->exists()) {
            throw ValidationException::withMessages([
                'financial_period' => 'لا توجد فترة مالية؛ افتح فترة تغطي تاريخ العملية قبل التأكيد.',
            ]);
        }

        // A retry must validate the existing source without requiring accounts that were
        // deactivated after the original posting.
        if (AccountingJournal::query()->where('source_type', 'sales_ledger_entries')
            ->where('source_id', $entry->id)->exists()) {
            $this->books->postOperationalEntry($entry, [], $actor);

            return;
        }

        $type = $entry->entry_type;
        $amount = $entry->amount;
        [$debit, $credit] = match ($type) {
            LedgerEntryType::Sale => ['1100', '4000'],
            LedgerEntryType::SaleCancellation => ['4000', '1100'],
            LedgerEntryType::EmployeePurchase => ['1150', '4000'],
            LedgerEntryType::PaymentCollection => [$this->treasuryCode($this->paymentMethod($entry)), $this->customerCounter($entry)],
            LedgerEntryType::PaymentReversal => [$this->customerCounter($entry), $this->treasuryCode($this->paymentMethod($entry))],
            LedgerEntryType::Refund => [$this->customerCounter($entry), $this->treasuryCode($this->paymentMethod($entry))],
            LedgerEntryType::EmployeeCollection => [$this->treasuryCode($this->paymentMethod($entry)), '1150'],
            LedgerEntryType::Expense => ['6000', $this->expenseCounter($entry)],
            LedgerEntryType::ExpenseReversal => [$this->expenseReversalCounter($entry), '6000'],
            default => [null, null],
        };
        if ($debit === null) {
            throw ValidationException::withMessages([
                'accounting' => 'نوع الحركة المالية غير مدعوم للترحيل الآلي.',
            ]);
        }

        $accounts = AccountingAccount::query()->whereIn('code', [$debit, $credit])
            ->where('is_active', true)->get()->keyBy('code');
        if (! isset($accounts[$debit], $accounts[$credit])) {
            throw ValidationException::withMessages(['accounting' => 'حسابات دفتر الأستاذ المطلوبة غير متاحة.']);
        }

        $this->books->postOperationalEntry($entry, [
            ['account_id' => $accounts[$debit]->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $accounts[$credit]->id, 'debit' => 0, 'credit' => $amount],
        ], $actor);
    }

    private function paymentMethod(SalesLedgerEntry $entry): PaymentMethod
    {
        $id = match ($entry->entry_type) {
            LedgerEntryType::EmployeeCollection => EmployeePurchaseReceipt::query()
                ->whereKey($entry->reference_id)->value('payment_method_id'),
            LedgerEntryType::Refund => Refund::query()->whereKey($entry->reference_id)->value('payment_method_id'),
            default => Payment::query()->whereKey($entry->reference_id)->value('payment_method_id'),
        };

        $method = $id ? PaymentMethod::withTrashed()->find($id) : null;
        if (! $method) {
            throw ValidationException::withMessages(['payment_method_id' => 'طريقة دفع العملية غير متاحة للترحيل.']);
        }

        return $method;
    }

    /**
     * Payments received before an invoice are customer liabilities, not receivables.
     * Refunds and corrections use the current invoice state: once the advance is
     * settled against an invoice, a subsequent refund reduces receivables.
     */
    private function customerCounter(SalesLedgerEntry $entry): string
    {
        $payment = $entry->entry_type === LedgerEntryType::Refund
            ? Payment::query()->findOrFail(Refund::query()->findOrFail($entry->reference_id)->payment_id)
            : Payment::query()->findOrFail($entry->reference_id);

        $orderType = $payment->orderTypeValue();
        $invoiced = Invoice::query()
            ->where('order_type', $orderType)
            ->where('order_id', $payment->order_id)
            ->exists();

        return $invoiced ? '1100' : '2400';
    }

    private function expenseCounter(SalesLedgerEntry $entry): string
    {
        $id = Expense::query()->whereKey($entry->reference_id)->value('payment_method_id');

        return $id ? $this->treasuryCode(PaymentMethod::withTrashed()->findOrFail($id)) : '2200';
    }

    private function expenseReversalCounter(SalesLedgerEntry $entry): string
    {
        // Voiding a paid expense does not prove the money returned to the till.
        // Keep the original treasury outflow and recognize a recovery receivable.
        return Expense::query()->whereKey($entry->reference_id)->value('payment_method_id')
            ? '1160' : '2200';
    }

    private function treasuryCode(PaymentMethod $method): string
    {
        return $method->type === 'cash' ? '1000' : '1010';
    }
}

<?php

namespace App\Services\Finance;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\BranchCashMovement;
use App\Models\Currency;
use App\Models\CustomerPayment;
use App\Models\FinancialPeriod;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PayrollPayment;
use App\Models\PayrollItem;
use App\Services\Procurement\CurrencyConversionService;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseReturn;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bridges source-of-truth operating records into the double-entry books.
 *
 * No backfill is performed implicitly: historical unposted sources must be
 * reconciled explicitly. New postings use the source's unique identifier.
 */
class GeneralLedgerIntegrationService
{
    public function __construct(private readonly AccountingBookService $books) {}

    public function customerReceipt(CustomerPayment $payment, User $actor): void
    {
        $this->post('customer_payments', $payment->id, (int) $payment->location_id,
            $payment->paid_at, (float) $payment->amount, $this->treasuryCode(PaymentMethod::withTrashed()->findOrFail($payment->payment_method_id)),
            '1100', "تحصيل من حساب العميل #{$payment->id}", $actor);
    }

    public function supplierInvoice(SupplierInvoice $invoice, User $actor): void
    {
        $this->postSupplierInvoice($invoice, $actor, false);
    }

    public function supplierInvoiceCancellation(SupplierInvoice $invoice, User $actor): void
    {
        if ($this->hasJournal('supplier_invoices', $invoice->id)) {
            $this->postSupplierInvoice($invoice, $actor, true);
        }
    }

    /**
     * Allocate the invoice's base-currency grand total proportionally.
     * Stock-linked products increase inventory; free-text/service items
     * are expensed rather than incorrectly inflating inventory value.
     */
    private function postSupplierInvoice(SupplierInvoice $invoice, User $actor, bool $reversal): void
    {
        if (! $this->ready()) {
            return;
        }
        $total = round((float) $invoice->base_grand_total, 2);
        if ($total <= 0) {
            return;
        }
        $invoice->loadMissing('items');
        $linesTotal = (float) $invoice->items->sum('line_total');
        $stockTotal = (float) $invoice->items
            ->filter(fn ($item) => $item->product_id !== null)->sum('line_total');
        $stock = $linesTotal > 0 ? round($total * $stockTotal / $linesTotal, 2) : 0;
        $services = round($total - $stock, 2);

        $accounts = AccountingAccount::query()
            ->whereIn('code', ['1200', '6000', '2000'])
            ->where('is_active', true)->get()->keyBy('code');
        if (! isset($accounts['1200'], $accounts['6000'], $accounts['2000'])) {
            throw ValidationException::withMessages(['accounting' => 'حسابات المخزون والمصروفات وذمم الموردين غير مكتملة.']);
        }

        $lines = [];
        foreach ([['code' => '1200', 'amount' => $stock], ['code' => '6000', 'amount' => $services]] as $row) {
            if ($row['amount'] > 0) {
                $lines[] = [
                    'account_id' => $accounts[$row['code']]->id,
                    'debit' => $reversal ? 0 : $row['amount'],
                    'credit' => $reversal ? $row['amount'] : 0,
                ];
            }
        }
        $lines[] = [
            'account_id' => $accounts['2000']->id,
            'debit' => $reversal ? $total : 0,
            'credit' => $reversal ? 0 : $total,
        ];

        $this->books->postSourceEntry(
            $reversal ? 'supplier_invoice_cancellations' : 'supplier_invoices',
            $invoice->id, (int) $invoice->location_id,
            $reversal ? now()->toDateString() : $invoice->invoice_date->toDateString(),
            ($reversal ? 'عكس ' : '')."فاتورة مورد {$invoice->invoice_number}",
            $lines, $actor
        );
    }

    public function purchaseReturn(PurchaseReturn $return, User $actor): void
    {
        // Linked return reduces accounts payable; an unallocated supplier
        // credit remains a recoverable receivable until applied or refunded.
        $debit = $return->supplier_invoice_id ? '2000' : '1160';
        $this->post('purchase_returns', $return->id, (int) $return->location_id,
            $return->posted_at ?? now(), (float) $return->base_grand_total,
            $debit, '1200', "مرتجع للمورد {$return->return_number}", $actor);
    }

    /**
     * Realized FX is the difference between the settled invoice liability
     * at its original invoice rate and the base-currency cash/bank outflow.
     *
     * A stronger transaction currency costs more base currency when its
     * rate rises: debit FX loss. On a fall, credit FX gain.
     */
    public function supplierPayment(SupplierPayment $payment, User $actor): void
    {
        if (! $this->ready()) {
            return;
        }

        $method = PaymentMethod::withTrashed()->findOrFail($payment->payment_method_id);
        $treasuryCode = $this->treasuryCode($method);
        $basePaid = round((float) $payment->base_amount, 2);
        $invoice = $payment->supplierInvoice;
        $bookedLiability = $invoice
            ? round((float) $payment->applied_amount * (float) $invoice->exchange_rate, 2)
            : $basePaid;
        $variance = round($basePaid - $bookedLiability, 2);
        if ($basePaid <= 0 || $bookedLiability <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة التسوية المحاسبية للمورد غير صالحة.']);
        }

        $required = ['2000', $treasuryCode];
        if ($variance > 0.004) {
            $required[] = '6200';
        } elseif ($variance < -0.004) {
            $required[] = '4200';
        }
        $accounts = AccountingAccount::query()->whereIn('code', $required)
            ->where('is_active', true)->get()->keyBy('code');

        foreach ($required as $code) {
            if (! isset($accounts[$code])) {
                throw ValidationException::withMessages([
                    'accounting' => "الحساب المحاسبي {$code} غير متاح لترحيل فروقات العملة.",
                ]);
            }
        }

        $lines = [
            ['account_id' => $accounts['2000']->id, 'debit' => $bookedLiability, 'credit' => 0],
            ['account_id' => $accounts[$treasuryCode]->id, 'debit' => 0, 'credit' => $basePaid],
        ];
        if ($variance > 0.004) {
            $lines[] = ['account_id' => $accounts['6200']->id, 'debit' => $variance, 'credit' => 0];
        } elseif ($variance < -0.004) {
            $lines[] = ['account_id' => $accounts['4200']->id, 'debit' => 0, 'credit' => abs($variance)];
        }

        $this->books->postSourceEntry(
            'supplier_payments', $payment->id, (int) $payment->location_id,
            $payment->payment_date->toDateString(),
            "سداد فاتورة مورد {$payment->supplier_invoice_id} مع فروقات الصرف المحققة",
            $lines, $actor
        );
    }

    /**
     * Optional payroll accrual, one immutable journal per approved payroll item.
     * Feature stays OFF until current liabilities and opening balances have
     * been reconciled by the accountant.
     */
    public function payrollAccrual(PayrollItem $item, User $actor): void
    {
        if (! $this->ready() || (float) $item->net_salary <= 0) {
            return;
        }
        $item->loadMissing('period');
        $period = $item->period;
        $locationId = $period->location_id ?: DB::table('employee_locations')
            ->where('employee_id', $item->employee_id)
            ->where('is_primary', true)->value('location_id');
        if (! $locationId) {
            throw ValidationException::withMessages(['payroll' => 'لا يمكن ترحيل الاستحقاق دون تحديد فرع الموظف.']);
        }

        $currencyId = $period->currency_id ?: Currency::query()->where('is_base', true)->value('id');
        $rate = app(CurrencyConversionService::class)->resolveRate(
            (int) $currencyId, null, $period->end_date
        );
        $baseAmount = round((float) $item->net_salary * (float) $rate, 2);
        $this->post('payroll_item_accruals', $item->id, (int) $locationId,
            $period->end_date, $baseAmount, '6100', '2100',
            "استحقاق راتب الموظف #{$item->employee_id} للفترة {$period->code}", $actor);
    }

    /** Settle the accrued liability first; legacy/unaccrued salary is expensed at payment. */
    public function payrollPayment(PayrollPayment $payment, User $actor): void
    {
        if (! $this->ready()) {
            return;
        }
        $basePaid = round((float) $payment->base_amount, 2);
        if ($basePaid <= 0) {
            return;
        }
        $method = PaymentMethod::withTrashed()->findOrFail($payment->payment_method_id);
        $treasuryCode = $this->treasuryCode($method);
        $accrual = AccountingJournal::query()
            ->where('source_type', 'payroll_item_accruals')
            ->where('source_id', $payment->payroll_item_id)->first();

        $accruedLiability = 0.0;
        $legacyExpense = $basePaid;
        if ($accrual) {
            $item = $payment->item;
            $net = (float) $item->net_salary;
            if ($net <= 0) {
                throw ValidationException::withMessages(['payroll' => 'مبلغ الاستحقاق المعتمد غير صالح.']);
            }
            $paidBefore = (float) PayrollPayment::query()
                ->where('payroll_item_id', $item->id)
                ->where('id', '!=', $payment->id)
                ->where('status', 'posted')->sum('amount');
            $nominal = min((float) $payment->amount, max(0, $net - $paidBefore));
            $accruedLiability = round((float) $accrual->total_credit * $nominal / $net, 2);
            $legacyExpense = round($basePaid * (1 - $nominal / (float) $payment->amount), 2);
        }

        $variance = round($basePaid - $accruedLiability - $legacyExpense, 2);
        $codes = array_values(array_unique(array_filter([
            $treasuryCode,
            $accruedLiability > 0 ? '2100' : null,
            $legacyExpense > 0 ? '6100' : null,
            $variance > 0.004 ? '6200' : ($variance < -0.004 ? '4200' : null),
        ])));
        $accounts = AccountingAccount::query()->whereIn('code', $codes)->where('is_active', true)
            ->get()->keyBy('code');
        foreach ($codes as $code) {
            if (! isset($accounts[$code])) {
                throw ValidationException::withMessages(['accounting' => "الحساب {$code} غير متاح."]);
            }
        }

        $lines = [];
        if ($accruedLiability > 0) {
            $lines[] = ['account_id' => $accounts['2100']->id, 'debit' => $accruedLiability, 'credit' => 0];
        }
        if ($legacyExpense > 0) {
            $lines[] = ['account_id' => $accounts['6100']->id, 'debit' => $legacyExpense, 'credit' => 0];
        }
        if ($variance > 0.004) {
            $lines[] = ['account_id' => $accounts['6200']->id, 'debit' => $variance, 'credit' => 0];
        } elseif ($variance < -0.004) {
            $lines[] = ['account_id' => $accounts['4200']->id, 'debit' => 0, 'credit' => abs($variance)];
        }
        $lines[] = ['account_id' => $accounts[$treasuryCode]->id, 'debit' => 0, 'credit' => $basePaid];
        $this->books->postSourceEntry('payroll_payments', $payment->id,
            (int) $payment->location_id, $payment->paid_at->toDateString(),
            "صرف راتب #{$payment->id}", $lines, $actor);
    }

    /**
     * A void does not restore the physical cash. Create a recoverable claim
     * while reinstating only the non-treasury legs of the original payment.
     */
    public function reversePayrollPayment(PayrollPayment $payment, User $actor): void
    {
        $original = AccountingJournal::query()->where('source_type', 'payroll_payments')
            ->where('source_id', $payment->id)->first();
        if (! $original) {
            return;
        }

        $recoverable = AccountingAccount::query()->where('code', '1160')
            ->where('is_active', true)->firstOrFail();
        $lines = [];
        $treasury = 0.0;
        foreach ($original->lines()->with('account')->get() as $line) {
            if (in_array($line->account->code, ['1000', '1010'], true)) {
                $treasury += (float) $line->credit - (float) $line->debit;
                continue;
            }
            $lines[] = [
                'account_id' => $line->accounting_account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ];
        }
        if ($treasury <= 0) {
            throw ValidationException::withMessages(['payroll' => 'قيد الصرف الأصلي لا يحتوي على مبلغ خزينة صحيح.']);
        }
        $lines[] = [
            'account_id' => $recoverable->id, 'debit' => round($treasury, 2), 'credit' => 0,
        ];

        $this->books->postSourceEntry('payroll_payment_reversals', $payment->id,
            (int) $payment->location_id, now()->toDateString(),
            "إلغاء راتب #{$payment->id} وفتح ذمة استرداد", $lines, $actor);
    }

    public function cashMovement(BranchCashMovement $movement, User $actor): void
    {
        [$debit, $credit] = match ($movement->type) {
            'other_income' => ['1000', '4100'],
            'transfer_in' => ['1000', '2300'],
            'transfer_out' => ['2300', '1000'],
            default => throw ValidationException::withMessages(['type' => 'حركة نقدية غير مدعومة']),
        };
        $this->post('branch_cash_movements', $movement->id, (int) $movement->location_id,
            $movement->occurred_at, (float) $movement->amount, $debit, $credit,
            "حركة صندوق #{$movement->id}", $actor);
    }

    public function reverseCashMovement(BranchCashMovement $movement, User $actor): void
    {
        if (! $this->hasJournal('branch_cash_movements', $movement->id)) {
            return;
        }
        [$debit, $credit] = match ($movement->type) {
            'other_income' => ['4100', '1000'],
            'transfer_in' => ['2300', '1000'],
            'transfer_out' => ['1000', '2300'],
            default => throw ValidationException::withMessages(['type' => 'حركة نقدية غير مدعومة']),
        };
        $this->post('branch_cash_movement_reversals', $movement->id, (int) $movement->location_id,
            now(), (float) $movement->amount, $debit, $credit,
            "عكس حركة صندوق #{$movement->id}", $actor);
    }

    /** Post complete frozen inventory cost; never invent missing unit costs. */
    public function orderCost(Order $order, User $actor): void
    {
        $items = $order->items()->get();
        if ($items->isEmpty() || $items->contains(fn ($item) => ! $item->cost_snapshotted_at)) {
            return;
        }
        $cost = round((float) $items->sum('cost_total_snapshot'), 2);
        if ($cost <= 0) {
            return;
        }
        $this->post('order_costs', $order->id, (int) $order->location_id,
            now(), $cost, '5000', '1200', "تكلفة طلب {$order->order_number}", $actor);
    }

    public function orderCostDifference(Order $order, float $previous, User $actor): void
    {
        if (! $this->hasJournal('order_costs', $order->id)) {
            return;
        }
        $items = $order->items()->get();
        if ($items->isEmpty() || $items->contains(fn ($item) => ! $item->cost_snapshotted_at)) {
            throw ValidationException::withMessages(['cost' => 'تكلفة أحد بنود الطلب المعدل غير معروفة.']);
        }
        $delta = round((float) $items->sum('cost_total_snapshot') - $previous, 2);
        if (abs($delta) < 0.005) {
            return;
        }
        $this->post('order_cost_adjust_'.Str::uuid(), $order->id, (int) $order->location_id,
            now(), abs($delta), $delta > 0 ? '5000' : '1200',
            $delta > 0 ? '1200' : '5000', "تسوية تكلفة طلب {$order->order_number}", $actor);
    }

    public function cancelOrderCost(Order $order, User $actor, ?float $restoredCost = null): void
    {
        if (! $this->hasJournal('order_costs', $order->id)) {
            return;
        }
        $cost = round($restoredCost ?? (float) $order->items()->sum('cost_total_snapshot'), 2);
        if ($cost > 0) {
            $this->post('order_cost_cancellations', $order->id, (int) $order->location_id,
                now(), $cost, '1200', '5000', "إلغاء تكلفة طلب {$order->order_number}", $actor);
        }
    }

    public function classifyOrderWaste(Order $order, float $cost, User $actor): void
    {
        // Materials were deducted already. Reclassify the original expense;
        // do not credit inventory again or record a duplicate expense.
        if ($this->hasJournal('order_costs', $order->id) && $cost > 0) {
            $this->post('order_waste', $order->id, (int) $order->location_id,
                now(), $cost, '6300', '5000', "هدر طلب ملغى {$order->order_number}", $actor);
        }
    }

    private function treasuryCode(PaymentMethod $method): string
    {
        return $method->type === 'cash' ? '1000' : '1010';
    }

    private function ready(): bool
    {
        return Schema::hasTable('accounting_journals')
            && FinancialPeriod::query()->exists()
            && Currency::query()->where('is_base', true)->where('is_active', true)->exists();
    }

    private function hasJournal(string $type, int $id): bool
    {
        return Schema::hasTable('accounting_journals')
            && AccountingJournal::query()->where('source_type', $type)->where('source_id', $id)->exists();
    }

    private function post(
        string $source, int $sourceId, int $locationId, CarbonInterface|string $date,
        float $amount, string $debitCode, string $creditCode, string $description, User $actor
    ): void {
        $amount = round($amount, 2);
        if (! $this->ready() || $amount <= 0) {
            return;
        }
        $accounts = AccountingAccount::query()->whereIn('code', [$debitCode, $creditCode])
            ->where('is_active', true)->get()->keyBy('code');
        if (! isset($accounts[$debitCode], $accounts[$creditCode])) {
            throw ValidationException::withMessages([
                'accounting' => 'يلزم تفعيل الحسابات المحاسبية المرتبطة بهذه الحركة قبل ترحيلها.',
            ]);
        }
        $dateValue = $date instanceof CarbonInterface ? $date->toDateString() : substr((string) $date, 0, 10);
        $this->books->postSourceEntry($source, $sourceId, $locationId, $dateValue,
            $description, [
                ['account_id' => $accounts[$debitCode]->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $accounts[$creditCode]->id, 'debit' => 0, 'credit' => $amount],
            ], $actor);
    }
}

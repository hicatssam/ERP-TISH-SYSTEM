<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\SupplierInvoiceService;
use App\Services\Procurement\SupplierPaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * End-to-end procurement demo using the exact domain services used by controllers.
 * No direct invoice, payment or general-ledger inserts.
 */
class TishProcurementLifecycleDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Never seed financial demo transactions in production.');
        }
        $actor = User::where('username', 'admin')->firstOrFail();

        // The meat PO is fully received and half paid; bakery bread is
        // partially received and fully paid for the received quantity.
        foreach (['MEAT' => 1.0, 'BAKERY' => 0.5] as $group => $receivedFraction) {
            $po = PurchaseOrder::query()
                ->where('purchase_order_number', 'TISH-DEMO-PO-'.$group)
                ->firstOrFail();
            $existing = SupplierInvoice::query()
                ->where('invoice_number', 'TISH-DEMO-INV-'.$group)
                ->first();
            if ($existing) {
                if (! $existing->goods_receipt_id
                    || ! GoodsReceipt::query()->whereKey($existing->goods_receipt_id)->where('status', 'posted')->exists()) {
                    throw new RuntimeException('Incomplete seeded procurement lifecycle for '.$group);
                }
                continue;
            }

            $orderService = app(PurchaseOrderService::class);
            if ($po->statusValue() === 'draft') {
                $po = $orderService->submit($po, $actor);
            }
            if ($po->statusValue() === 'submitted') {
                $po = $orderService->approve($po, $actor);
            }
            if ($po->statusValue() !== 'approved') {
                throw new RuntimeException('Unexpected purchase order state for '.$group);
            }

            $items = $po->items()->get();
            $rows = $items->map(function ($item) use ($receivedFraction) {
                $qty = round((float) $item->ordered_quantity * $receivedFraction, 3);
                return [
                    'purchase_order_item_id' => $item->id,
                    'received_quantity' => $qty,
                    'accepted_quantity' => $qty,
                    'rejected_quantity' => 0,
                    'unit_cost' => $item->unit_price,
                ];
            })->all();

            $receiptService = app(GoodsReceiptService::class);
            $before = $items->mapWithKeys(fn ($item) => [
                $item->product_id => (float) Inventory::query()
                    ->where('location_id', $po->location_id)
                    ->where('product_id', $item->product_id)->value('quantity'),
            ])->all();

            $receipt = $receiptService->create([
                'purchase_order_id' => $po->id,
                'received_at' => now(),
                'items' => $rows,
                'notes' => 'DEMO: استلام '.$group,
            ], $actor);
            $receipt = $receiptService->post($receipt, $actor);

            foreach ($receipt->items as $line) {
                $after = (float) Inventory::query()
                    ->where('location_id', $po->location_id)
                    ->where('product_id', $line->product_id)->value('quantity');
                if (abs($after - ($before[$line->product_id] + (float) $line->accepted_base_quantity)) > 0.001) {
                    throw new RuntimeException('Receipt did not reconcile inventory: '.$line->product_id);
                }
            }

            $invoice = app(SupplierInvoiceService::class)->create([
                'invoice_number' => 'TISH-DEMO-INV-'.$group,
                'supplier_id' => $po->supplier_id,
                'goods_receipt_id' => $receipt->id,
                'location_id' => $po->location_id,
                'currency_id' => $po->currency_id,
                'exchange_rate' => 1,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'notes' => 'DEMO: فاتورة مرتبطة باستلام مواد خام',
            ], $actor);

            $total = (float) $invoice->grand_total;
            $amount = $group === 'MEAT' ? round($total / 2, 2) : $total;
            $method = PaymentMethod::where('code', 'CASH')->firstOrFail();
            app(SupplierPaymentService::class)->record([
                'supplier_id' => $invoice->supplier_id,
                'supplier_invoice_id' => $invoice->id,
                'location_id' => $invoice->location_id,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => 1,
                'amount' => $amount,
                'payment_date' => now()->toDateString(),
                'payment_method_id' => $method->id,
                'notes' => 'DEMO: payment via the normal procurement service',
            ], $actor);

            $invoice->refresh();
            if (abs((float) $invoice->remaining_amount - ($total - $amount)) > 0.02) {
                throw new RuntimeException('Supplier payment mismatch for '.$group);
            }
        }

        if (SupplierPayment::query()->whereHas('supplierInvoice', fn ($q) =>
            $q->where('invoice_number', 'like', 'TISH-DEMO-INV-%'))->count() !== 2) {
            throw new RuntimeException('Expected exactly two demo supplier payments.');
        }
        $this->command?->info('TISH procurement: real PO approvals, posted goods receipts, invoices, and partial/full payments created.');
    }
}

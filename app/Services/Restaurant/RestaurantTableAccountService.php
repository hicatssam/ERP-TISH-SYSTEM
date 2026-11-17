<?php

namespace App\Services\Restaurant;

use App\Models\RestaurantTableSession;

/**
 * Operational, non-tax session account. Never edits/merges issued invoices.
 * A late order creates its own invoice; this view reconciles the whole visit.
 */
class RestaurantTableAccountService
{
    public function summary(RestaurantTableSession $session): array
    {
        $session->loadMissing(['orders.invoice', 'orders.payments']);
        $orders = $session->orders
            ->filter(fn ($order) => $order->statusValue() !== 'cancelled')
            ->sortBy('id')
            ->values();

        $rows = $orders->map(function ($order): array {
            $invoice = $order->invoice;
            $validInvoice = $invoice && $invoice->statusValue() !== 'cancelled';
            $total = $validInvoice ? $invoice->total_amount : $order->total_amount;
            // InvoiceService already reconciles confirmed/corrected payments,
            // verified customer credits and refunds. Reuse its net amounts.
            $totalCents = (int) round(((float) $total) * 100);
            $paidCents = $validInvoice
                ? (int) round(((float) $invoice->paid_amount) * 100)
                : 0;
            $outstandingCents = $validInvoice
                ? max(0, (int) round(((float) $invoice->remaining_amount) * 100))
                : max(0, $totalCents);

            return [
                'order' => $order,
                'invoice' => $validInvoice ? $invoice : null,
                'total_cents' => $totalCents,
                'paid_cents' => $paidCents,
                'outstanding_cents' => $outstandingCents,
                'requires_review' => ! $validInvoice && $totalCents > 0,
            ];
        });

        return [
            'rows' => $rows,
            'total_cents' => $rows->sum('total_cents'),
            'paid_cents' => $rows->sum('paid_cents'),
            'outstanding_cents' => $rows->sum('outstanding_cents'),
            'requires_review' => $rows->contains('requires_review', true),
        ];
    }
}

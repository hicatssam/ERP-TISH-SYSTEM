<?php

namespace App\Services\Restaurant;

use App\Models\RestaurantTableSession;
use Illuminate\Support\Collection;

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
            // Confirmation is authoritative: pending verification is not received money.
            $paid = $order->payments
                ->filter(fn ($payment) => (
                    $payment->status instanceof \BackedEnum
                        ? $payment->status->value
                        : (string) $payment->status
                ) === 'confirmed')
                ->sum(fn ($payment) => (float) $payment->amount);

            $totalCents = (int) round(((float) $total) * 100);
            $paidCents = (int) round(((float) $paid) * 100);
            $outstandingCents = max(0, $totalCents - $paidCents);

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

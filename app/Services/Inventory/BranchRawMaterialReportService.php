<?php

namespace App\Services\Inventory;

use App\Models\StockRequest;
use App\Models\StockTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only operational and valuation reconciliation for factory -> branch raw materials.
 *
 * Stock requisitions are not sales or expenses. Receiving value is the cost
 * snapshot from dispatched transfer items, in the inventory base currency.
 * Nothing here posts journals or alters daily financial totals.
 */
class BranchRawMaterialReportService
{
    /** @return array<string, mixed> */
    public function forDay(int $branchId, string $date): array
    {
        $requests = StockRequest::query()
            ->with(['items.product.unitDefinition'])
            ->where('branch_location_id', $branchId)
            ->whereDate('created_at', $date)
            ->orderBy('created_at')
            ->get();

        $dispatched = $this->transfers([$branchId], $date, $date, 'dispatched_at');
        $received = $this->transfers([$branchId], $date, $date, 'received_at');

        return [
            'requests' => $requests->filter(fn (StockRequest $req) =>
                $req->items->contains(fn ($item) => (bool) $item->product?->is_raw_material)
            )->values(),
            'dispatches' => $this->lines($dispatched, false),
            'receipts' => $this->lines($received, true),
            'totals' => $this->totals($dispatched, $received),
        ];
    }

    /** @return array<string, mixed> */
    public function forPeriod(array $branchIds, string $from, string $to): array
    {
        if ($branchIds === []) {
            return ['dispatches' => collect(), 'receipts' => collect(),
                'totals' => $this->totals(collect(), collect())];
        }

        $dispatched = $this->transfers($branchIds, $from, $to, 'dispatched_at', true);
        $received = $this->transfers($branchIds, $from, $to, 'received_at');

        return [
            'dispatches' => $this->lines($dispatched, false),
            'receipts' => $this->lines($received, true),
            'totals' => $this->totals($dispatched, $received),
        ];
    }

    private function transfers(array $branchIds, string $from, string $to, string $column, bool $includeSource = false): Collection
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();

        return StockTransfer::query()
            ->with(['stockRequest', 'fromLocation', 'toLocation',
                'items.product.unitDefinition'])
            ->where(function ($query) use ($branchIds, $includeSource): void {
                $query->whereIn('to_location_id', $branchIds);
                if ($includeSource) {
                    $query->orWhereIn('from_location_id', $branchIds);
                }
            })
            ->whereBetween($column, [$start, $end])
            ->whereHas('items.product', fn ($query) =>
                $query->where('is_raw_material', true))
            ->orderBy($column)
            ->get();
    }

    private function lines(Collection $transfers, bool $receipt): Collection
    {
        return $transfers->flatMap(function (StockTransfer $transfer) use ($receipt) {
            return $transfer->items
                ->filter(fn ($item) => (bool) $item->product?->is_raw_material)
                ->map(function ($item) use ($transfer, $receipt): array {
                    $sent = (float) $item->sent_quantity;
                    $received = (float) ($item->received_quantity ?? 0);
                    $damaged = (float) ($item->damaged_quantity ?? 0);
                    $unitCost = (float) ($item->unit_cost ?? 0);

                    return [
                        'transfer' => $transfer,
                        'product' => $item->product,
                        'sent' => $sent,
                        'received' => $received,
                        'damaged' => $damaged,
                        'shortage' => max(0, round($sent - $received - $damaged, 3)),
                        'quantity' => $receipt ? $received : $sent,
                        'unit_cost' => $unitCost,
                        'value' => round(($receipt ? $received : $sent) * $unitCost, 2),
                    ];
                });
        })->values();
    }

    private function totals(Collection $dispatched, Collection $received): array
    {
        $sentLines = $this->lines($dispatched, false);
        $receivedLines = $this->lines($received, true);
        return [
            'requests_shipped' => $dispatched->count(),
            'receipts' => $received->count(),
            'dispatched_value' => round($sentLines->sum('value'), 2),
            'received_value' => round($receivedLines->sum('value'), 2),
            'damaged_value' => round($receivedLines->sum(fn ($row) => $row['damaged'] * $row['unit_cost']), 2),
            'shortage_value' => round($receivedLines->sum(fn ($row) => $row['shortage'] * $row['unit_cost']), 2),
        ];
    }
}

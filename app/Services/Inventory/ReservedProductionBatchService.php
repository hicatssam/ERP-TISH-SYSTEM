<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consume a production order's existing reservation from unexpired batches
 * in FEFO order, retaining each source batch's unit cost and movement.
 */
class ReservedProductionBatchService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /** @return array{unit_cost: float, total_cost: float, inventory: Inventory} */
    public function consume(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        ?string $note = null,
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'كمية الاستهلاك يجب أن تكون أكبر من صفر.']);
        }

        return DB::transaction(function () use (
            $locationId, $productId, $quantity, $userId,
            $referenceType, $referenceId, $idempotencyKey, $note
        ): array {
            $product = Product::query()->findOrFail($productId);
            if (! $product->tracks_batch && ! $product->tracks_expiry) {
                $consumed = $this->inventory->consumeReserved(
                    $locationId, $productId, $quantity, $userId,
                    $referenceType, $referenceId, $idempotencyKey, $note
                );
                return [
                    'unit_cost' => (float) $consumed['unit_cost'],
                    'total_cost' => round($quantity * (float) $consumed['unit_cost'], 2),
                    'inventory' => $consumed['inventory'],
                ];
            }

            $prefix = $idempotencyKey.':batch:';
            $existing = StockMovement::query()
                ->where('idempotency_key', 'like', $prefix.'%')->get();
            if ($existing->isNotEmpty()) {
                $sum = (float) $existing->sum('quantity');
                if (abs($sum - $quantity) > 0.0005
                    || $existing->contains(fn ($movement) =>
                        (int) $movement->product_id !== $productId
                        || (int) $movement->location_id !== $locationId
                        || $movement->reference_type !== $referenceType
                        || (int) $movement->reference_id !== $referenceId)) {
                    throw ValidationException::withMessages([
                        'quantity' => 'مفتاح استهلاك الإنتاج مسجل ببيانات مختلفة.',
                    ]);
                }
                $total = round((float) $existing->sum('base_total_cost'), 2);
                return [
                    'unit_cost' => round($total / $quantity, 4),
                    'total_cost' => $total,
                    'inventory' => Inventory::query()
                        ->where('location_id', $locationId)
                        ->where('product_id', $productId)->firstOrFail(),
                ];
            }

            $batches = InventoryBatch::query()
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->where('available_quantity', '>', 0)
                ->where(fn ($query) => $product->tracks_expiry
                    ? $query->whereDate('expiry_date', '>=', today())
                    : $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->lockForUpdate()->get();

            if ((float) $batches->sum('available_quantity') + 0.0005 < $quantity) {
                throw ValidationException::withMessages([
                    'inventory_batch_id' => 'الدفعات الصالحة غير كافية لإكمال استهلاك أمر الإنتاج.',
                ]);
            }

            $remaining = round($quantity, 3);
            $totalCost = 0.0;
            $inventory = null;

            foreach ($batches as $batch) {
                if ($remaining <= 0.0005) {
                    break;
                }
                $take = round(min($remaining, (float) $batch->available_quantity), 3);
                if ($take <= 0) {
                    continue;
                }

                $consumed = $this->inventory->consumeReserved(
                    $locationId, $productId, $take, $userId,
                    $referenceType, $referenceId, $prefix.$batch->id,
                    $note, (int) $batch->id
                );

                $inventory = $consumed['inventory'];
                $totalCost += $take * (float) $consumed['unit_cost'];
                $remaining = round($remaining - $take, 3);
            }

            if ($remaining > 0.0005 || ! $inventory) {
                throw ValidationException::withMessages([
                    'quantity' => 'تعذر إكمال تخصيص دفعات الإنتاج.',
                ]);
            }

            return [
                'unit_cost' => round($totalCost / $quantity, 4),
                'total_cost' => round($totalCost, 2),
                'inventory' => $inventory,
            ];
        });
    }
}

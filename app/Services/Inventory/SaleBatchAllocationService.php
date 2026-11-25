<?php

namespace App\Services\Inventory;

use App\Enums\MovementReason;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consume tracked sale ingredients using FEFO, in a single transaction.
 * A movement is recorded for every consumed batch. The caller must also
 * perform order-consumption snapshot writes within its surrounding transaction.
 */
class SaleBatchAllocationService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Restore tracked sale stock to the original batches, including batches
     * that have since expired. Never treat returned expired stock as sellable:
     * a subsequent FEFO allocation still filters it by expiry.
     *
     * $consumptionPrefix identifies one frozen order-consumption row across
     * confirmation, quantity revisions, and cancellations.
     */
    public function restore(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        string $consumptionPrefix,
        string $idempotencyKey,
        ?string $note = null,
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'كمية الإرجاع يجب أن تكون موجبة.']);
        }

        return DB::transaction(function () use (
            $locationId, $productId, $quantity, $userId,
            $referenceType, $referenceId, $consumptionPrefix,
            $idempotencyKey, $note
        ): Inventory {
            $previousReturns = StockMovement::query()
                ->where('idempotency_key', 'like', $idempotencyKey.':batch:%')
                ->get();
            if ($previousReturns->isNotEmpty()) {
                if (abs((float) $previousReturns->sum('quantity') - $quantity) > 0.0005) {
                    throw ValidationException::withMessages(['quantity' => 'مفتاح الإرجاع سبق استخدامه بكمية مختلفة.']);
                }
                return Inventory::query()->where('location_id', $locationId)
                    ->where('product_id', $productId)->firstOrFail();
            }

            $movements = StockMovement::query()
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->whereNotNull('inventory_batch_id')
                ->where('idempotency_key', 'like', $consumptionPrefix.'%')
                ->orderBy('id')->get();

            $net = [];
            foreach ($movements as $movement) {
                $batchId = (int) $movement->inventory_batch_id;
                $reason = $movement->reason instanceof MovementReason
                    ? $movement->reason->value : (string) $movement->reason;
                if (! in_array($reason, [
                    MovementReason::OrderSale->value,
                    MovementReason::OrderCancellation->value,
                ], true)) {
                    continue;
                }
                $sign = $reason === MovementReason::OrderSale->value ? 1 : -1;
                $net[$batchId] = round(($net[$batchId] ?? 0) + $sign * (float) $movement->quantity, 3);
            }
            $available = round(array_sum(array_map(fn ($v) => max(0, $v), $net)), 3);
            if ($available + 0.0005 < $quantity) {
                throw ValidationException::withMessages([
                    'inventory_batch_id' => 'لا توجد تخصيصات سابقة كافية لإعادة الكمية إلى دفعاتها الأصلية.',
                ]);
            }

            // Return newer allocations first. Every reversal remains linked
            // to the same inventory_batch_id as the original sale movement.
            $batchIds = array_keys($net);
            rsort($batchIds, SORT_NUMERIC);
            $remaining = round($quantity, 3);
            $inventory = null;

            foreach ($batchIds as $batchId) {
                $canRestore = max(0, $net[$batchId]);
                $take = round(min($remaining, $canRestore), 3);
                if ($take <= 0) {
                    continue;
                }
                $batch = InventoryBatch::query()->whereKey($batchId)
                    ->where('location_id', $locationId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()->firstOrFail();

                $inventory = $this->inventory->increase(
                    locationId: $locationId,
                    productId: $productId,
                    quantity: $take,
                    reason: MovementReason::OrderCancellation,
                    userId: $userId,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    idempotencyKey: $idempotencyKey.':batch:'.$batchId,
                    unitCost: (string) $batch->base_unit_cost,
                    inventoryBatchId: $batchId,
                    note: $note,
                );
                $batch->increment('available_quantity', $take);
                $remaining = round($remaining - $take, 3);
                if ($remaining <= 0.0005) {
                    break;
                }
            }
            if ($remaining > 0.0005 || ! $inventory) {
                throw ValidationException::withMessages(['quantity' => 'تعذر استعادة كمية الإلغاء بالكامل.']);
            }
            return $inventory;
        });
    }

    public function consume(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        ?string $note = null,
        MovementReason $reason = MovementReason::OrderSale,
    ): Inventory {
        if (! in_array($reason, [MovementReason::OrderSale, MovementReason::TransferDispatch], true)) {
            throw ValidationException::withMessages(['reason' => 'لا يدعم FEFO هذا النوع من الاستهلاك.']);
        }
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'كمية الاستهلاك يجب أن تكون موجبة.']);
        }

        return DB::transaction(function () use (
            $locationId, $productId, $quantity, $userId,
            $referenceType, $referenceId, $idempotencyKey, $note, $reason
        ): Inventory {
            $product = Product::query()->findOrFail($productId);
            if (! $product->tracks_batch && ! $product->tracks_expiry) {
                return $this->inventory->decrease(
                    locationId: $locationId,
                    productId: $productId,
                    quantity: $quantity,
                    reason: $reason,
                    userId: $userId,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    idempotencyKey: $idempotencyKey,
                    note: $note,
                );
            }

            $existing = StockMovement::query()->where('idempotency_key', 'like', $idempotencyKey.':batch:%')->get();
            if ($existing->isNotEmpty()) {
                if (abs((float) $existing->sum('quantity') - $quantity) > 0.0005
                    || $existing->contains(fn ($m) =>
                        (int) $m->product_id !== $productId || (int) $m->location_id !== $locationId
                    )) {
                    throw ValidationException::withMessages([
                        'quantity' => 'مفتاح الاستهلاك مسجل بكمية أو صنف مختلف.',
                    ]);
                }

                return Inventory::query()->where('location_id', $locationId)
                    ->where('product_id', $productId)->firstOrFail();
            }

            $batches = InventoryBatch::query()
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->where('available_quantity', '>', 0)
                ->where(fn ($q) => $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today()))
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')->orderBy('id')
                ->lockForUpdate()->get();

            if ((float) $batches->sum('available_quantity') + 0.0005 < $quantity) {
                throw ValidationException::withMessages([
                    'inventory_batch_id' => 'الدفعات الصالحة لا تغطي كمية البيع المطلوبة.',
                ]);
            }

            $remaining = round($quantity, 3);
            $inventory = null;
            foreach ($batches as $batch) {
                if ($remaining <= 0.0005) {
                    break;
                }
                $take = round(min($remaining, (float) $batch->available_quantity), 3);
                if ($take <= 0) {
                    continue;
                }

                // InventoryService validates expiry and decrements the batch
                // and aggregate stock atomically, with one movement per batch.
                $inventory = $this->inventory->decrease(
                    locationId: $locationId,
                    productId: $productId,
                    quantity: $take,
                    reason: $reason,
                    userId: $userId,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    idempotencyKey: $idempotencyKey.':batch:'.$batch->id,
                    note: $note,
                    unitCost: (string) $batch->base_unit_cost,
                    inventoryBatchId: $batch->id,
                );
                $remaining = round($remaining - $take, 3);
            }

            if ($remaining > 0.0005 || ! $inventory) {
                throw ValidationException::withMessages([
                    'inventory_batch_id' => 'تعذر إكمال التخصيص من الدفعات الصالحة.',
                ]);
            }

            return $inventory;
        });
    }
}

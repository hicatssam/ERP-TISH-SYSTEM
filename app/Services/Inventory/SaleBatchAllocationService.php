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

    public function consume(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        ?string $note = null,
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'كمية الاستهلاك يجب أن تكون موجبة.']);
        }

        return DB::transaction(function () use (
            $locationId, $productId, $quantity, $userId,
            $referenceType, $referenceId, $idempotencyKey, $note
        ): Inventory {
            $product = Product::query()->findOrFail($productId);
            if (! $product->tracks_batch && ! $product->tracks_expiry) {
                return $this->inventory->decrease(
                    locationId: $locationId,
                    productId: $productId,
                    quantity: $quantity,
                    reason: MovementReason::OrderSale,
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
                    reason: MovementReason::OrderSale,
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

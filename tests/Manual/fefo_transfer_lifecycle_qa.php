<?php
/**
 * Rollback-only E2E transfer QA: FEFO dispatch, partial tracked receipt,
 * damage discrepancy, destination batch metadata and stock reconciliation.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\LocationType;
use App\Enums\StockTransferStatus;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\InternalTransferService;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
$expected = realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $expected || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $expected
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

$product = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();
$actor = User::query()->findOrFail(1);
$source = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
$before = (float) $source->quantity;
$batchBefore = InventoryBatch::query()->count();
$movementBefore = StockMovement::query()->count();
$transferBefore = StockTransfer::query()->count();
if ($product->tracks_batch || $product->tracks_expiry
    || $batchBefore !== 0 || abs($before - 205.35) > 0.001) {
    throw new RuntimeException('QA INITIAL STATE FAILED');
}

DB::beginTransaction();
try {
    $destination = Location::query()->create([
        'name' => 'QA FEFO Destination',
        'code' => 'QA-FEFO-RECEIPT-20261125',
        'type' => LocationType::Branch,
        'is_active' => true,
    ]);
    $product->update(['tracks_batch' => true, 'tracks_expiry' => true]);

    $batches = [];
    foreach ([['EXPIRED', 2, -1], ['EARLY', 2, 1], ['LATER', 3, 5]]
        as [$name, $qty, $days]) {
        $batches[$name] = InventoryBatch::query()->create([
            'product_id' => $product->id,
            'location_id' => 1,
            'batch_number' => 'QA-TRANSFER-'.$name,
            'manufacturing_date' => today()->subDays(7)->toDateString(),
            'expiry_date' => today()->addDays($days)->toDateString(),
            'received_quantity' => $qty,
            'available_quantity' => $qty,
            'unit_cost' => 50,
            'base_unit_cost' => 50,
            'currency_id' => 1,
        ]);
    }

    $transfer = StockTransfer::query()->create([
        'transfer_number' => 'QA-FEFO-LIFECYCLE-999997',
        'from_location_id' => 1,
        'to_location_id' => $destination->id,
        'status' => StockTransferStatus::Draft,
    ]);
    $item = $transfer->items()->create([
        'product_id' => $product->id,
        'sent_quantity' => 4,
    ]);

    $service = app(InternalTransferService::class);
    $sent = $service->dispatch($transfer, $actor);
    $sentStatus = $sent->status instanceof \BackedEnum ? $sent->status->value : (string) $sent->status;

    $sourceStock = (float) Inventory::query()->where('location_id', 1)
        ->where('product_id', $product->id)->value('quantity');
    $destinationTransit = (float) Inventory::query()->where('location_id', $destination->id)
        ->where('product_id', $product->id)->value('in_transit_quantity');

    echo "DISPATCH STATUS: {$sentStatus}\n";
    echo 'SOURCE STOCK DELTA: '.($before - $sourceStock)." kg\n";
    echo "IN TRANSIT: {$destinationTransit} kg\n";
    if ($sentStatus !== 'dispatched' || abs(($before - $sourceStock) - 4) > 0.001
        || abs($destinationTransit - 4) > 0.001
        || abs((float)$batches['EXPIRED']->fresh()->available_quantity - 2) > 0.001
        || abs((float)$batches['EARLY']->fresh()->available_quantity) > 0.001
        || abs((float)$batches['LATER']->fresh()->available_quantity - 1) > 0.001) {
        throw new RuntimeException('FEFO TRANSFER DISPATCH FAILED');
    }

    $service->receive($transfer, [
        $item->id => ['received_quantity' => 3, 'damaged_quantity' => 1],
    ], $actor);

    $destinationStock = (float) Inventory::query()->where('location_id', $destination->id)
        ->where('product_id', $product->id)->value('quantity');
    $transitAfter = (float) Inventory::query()->where('location_id', $destination->id)
        ->where('product_id', $product->id)->value('in_transit_quantity');
    $receivedBatches = InventoryBatch::query()->where('location_id', $destination->id)
        ->where('product_id', $product->id)->orderBy('expiry_date')->get();
    $receiptStatus = $transfer->fresh()->status;
    $receiptStatus = $receiptStatus instanceof \BackedEnum ? $receiptStatus->value : (string)$receiptStatus;

    echo "RECEIPT STATUS: {$receiptStatus}\n";
    echo "DESTINATION STOCK: {$destinationStock} kg\n";
    echo "IN TRANSIT AFTER RECEIPT: {$transitAfter} kg\n";
    echo "DESTINATION BATCH COUNT: ".$receivedBatches->count()."\n";
    foreach ($receivedBatches as $batch) {
        echo "DESTINATION {$batch->batch_number}: {$batch->available_quantity} kg, expiry {$batch->expiry_date->toDateString()}\n";
    }

    if ($receiptStatus !== StockTransferStatus::DiscrepancyOpen->value
        || abs($destinationStock - 3) > 0.001 || abs($transitAfter) > 0.001
        || $receivedBatches->count() !== 2
        || $receivedBatches[0]->batch_number !== 'QA-TRANSFER-EARLY'
        || abs((float)$receivedBatches[0]->available_quantity - 2) > 0.001
        || $receivedBatches[0]->expiry_date->toDateString() !== $batches['EARLY']->expiry_date->toDateString()
        || $receivedBatches[1]->batch_number !== 'QA-TRANSFER-LATER'
        || abs((float)$receivedBatches[1]->available_quantity - 1) > 0.001
        || $receivedBatches[1]->expiry_date->toDateString() !== $batches['LATER']->expiry_date->toDateString()) {
        throw new RuntimeException('FEFO TRANSFER RECEIPT FAILED');
    }

    echo "=== TRACKED TRANSFER LIFECYCLE PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}

$stockFinal = (float)Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->value('quantity');
if (abs($stockFinal - $before) > 0.001
    || InventoryBatch::query()->count() !== $batchBefore
    || StockMovement::query()->count() !== $movementBefore
    || StockTransfer::query()->count() !== $transferBefore
    || Product::query()->findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== TRANSFER FEFO QA COMPLETE ===\n";

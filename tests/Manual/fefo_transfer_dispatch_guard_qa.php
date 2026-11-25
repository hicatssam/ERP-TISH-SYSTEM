<?php
/**
 * Rollback-only QA: actual InternalTransferService::dispatch must fail closed
 * on a tracked ingredient without batch allocation, without in-transit changes.
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
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
$path = realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $path || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $path
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

$product = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();
$user = User::query()->findOrFail(1);
$source = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
$stockBefore = (float) $source->quantity;
$movementBefore = StockMovement::query()->count();
$transferBefore = StockTransfer::query()->count();
$batchBefore = InventoryBatch::query()->count();
if ($product->tracks_batch || $product->tracks_expiry || $batchBefore !== 0) {
    throw new RuntimeException('QA INITIAL STATE FAILED');
}

DB::beginTransaction();
try {
    $destination = Location::query()->create([
        'name' => 'QA Transfer Destination',
        'code' => 'QA-FEFO-DEST-20261125',
        'type' => LocationType::Branch,
        'is_active' => true,
    ]);
    $product->update(['tracks_batch' => true, 'tracks_expiry' => true]);

    InventoryBatch::query()->create([
        'product_id' => $product->id,
        'location_id' => 1,
        'batch_number' => 'QA-TRANSFER-EXPIRED',
        'expiry_date' => today()->subDay()->toDateString(),
        'received_quantity' => 2,
        'available_quantity' => 2,
        'unit_cost' => 50,
        'base_unit_cost' => 50,
        'currency_id' => 1,
    ]);

    $transfer = StockTransfer::query()->create([
        'transfer_number' => 'QA-FEFO-TRANSFER-999998',
        'from_location_id' => 1,
        'to_location_id' => $destination->id,
        'status' => StockTransferStatus::Draft,
    ]);
    $transfer->items()->create([
        'product_id' => $product->id,
        'sent_quantity' => 1,
    ]);

    $blocked = false;
    try {
        app(InternalTransferService::class)->dispatch($transfer, $user);
    } catch (ValidationException $e) {
        $blocked = true;
        echo "TRACKED TRANSFER DISPATCH: BLOCKED\n";
    }

    $sourceAfter = (float) Inventory::query()->where('location_id', 1)
        ->where('product_id', $product->id)->value('quantity');
    $toInventory = Inventory::query()->where('location_id', $destination->id)
        ->where('product_id', $product->id)->first();
    $inTransit = (float) ($toInventory?->in_transit_quantity ?? 0);
    $unexpectedMoves = StockMovement::query()->count() - $movementBefore;
    $status = $transfer->fresh()->status;
    $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;

    echo 'SOURCE STOCK DELTA: '.($sourceAfter - $stockBefore)." kg\n";
    echo "DESTINATION IN TRANSIT: {$inTransit} kg\n";
    echo "UNEXPECTED MOVEMENTS: {$unexpectedMoves}\n";
    echo "TRANSFER STATUS: {$statusValue}\n";

    if (! $blocked || abs($sourceAfter - $stockBefore) > 0.001
        || abs($inTransit) > 0.001 || $unexpectedMoves !== 0
        || $statusValue !== StockTransferStatus::Draft->value) {
        throw new RuntimeException('TRANSFER GUARD FAILED');
    }
    echo "=== TRANSFER DISPATCH GUARD PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}

$finalStock = (float) Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->value('quantity');
if (abs($finalStock - $stockBefore) > 0.001
    || StockMovement::query()->count() !== $movementBefore
    || StockTransfer::query()->count() !== $transferBefore
    || InventoryBatch::query()->count() !== $batchBefore
    || Product::query()->findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== TRANSFER QA COMPLETE ===\n";

<?php
/**
 * Rollback-only QA for production reserved FEFO. Verifies batch allocation,
 * expiry exclusion, cost, reserved reduction, idempotency and insufficient stock.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservedProductionBatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
$expected = realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $expected || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $expected
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}
$product = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();
$before = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
$stockBefore = (float)$before->quantity;
$reservedBefore = (float)$before->reserved_quantity;
$batchesBefore = InventoryBatch::count();
$movesBefore = StockMovement::count();

if ($product->tracks_batch || $product->tracks_expiry
    || $batchesBefore !== 0 || abs($reservedBefore) > 0.001) {
    throw new RuntimeException('INVALID QA INITIAL STATE');
}

DB::beginTransaction();
try {
    $product->update(['tracks_batch' => true, 'tracks_expiry' => true]);
    $batches = [];
    foreach ([['EXPIRED', 8, -1, 20], ['EARLY', 2, 1, 50], ['LATER', 2, 5, 70]]
        as [$name, $qty, $days, $cost]) {
        $batches[$name] = InventoryBatch::query()->create([
            'product_id' => $product->id,
            'location_id' => 1,
            'batch_number' => 'QA-PRODUCTION-'.$name,
            'expiry_date' => today()->addDays($days)->toDateString(),
            'received_quantity' => $qty,
            'available_quantity' => $qty,
            'unit_cost' => $cost,
            'base_unit_cost' => $cost,
            'currency_id' => 1,
        ]);
    }

    $inventory = app(InventoryService::class);
    $service = app(ReservedProductionBatchService::class);

    $inventory->reserve(1, (int)$product->id, 3, 1, 'qa_production', 1);
    $args = [
        'locationId' => 1,
        'productId' => (int)$product->id,
        'quantity' => 3,
        'userId' => 1,
        'referenceType' => 'production_orders',
        'referenceId' => 999999,
        'idempotencyKey' => 'qa-production-reserved-fefo-001',
    ];
    $result = $service->consume(...$args);
    $after = Inventory::query()->where('location_id', 1)
        ->where('product_id', $product->id)->firstOrFail();

    echo 'SOURCE STOCK DELTA: '.($stockBefore - (float)$after->quantity)." kg\n";
    echo 'RESERVED REMAINING: '.$after->reserved_quantity." kg\n";
    foreach ($batches as $name => $batch) {
        echo $name.': '.$batch->fresh()->available_quantity." kg remaining\n";
    }
    echo 'TOTAL MATERIAL COST: '.$result['total_cost']."\n";
    $moves = StockMovement::query()->where('reference_type', 'production_orders')
        ->where('reference_id', 999999)
        ->where('idempotency_key', 'like', 'qa-production-reserved-fefo-001:batch:%')
        ->get();
    echo 'BATCH MOVEMENTS: '.$moves->count()."\n";

    if (abs($stockBefore - (float)$after->quantity - 3) > 0.001
        || abs((float)$after->reserved_quantity) > 0.001
        || abs((float)$batches['EXPIRED']->fresh()->available_quantity - 8) > 0.001
        || abs((float)$batches['EARLY']->fresh()->available_quantity) > 0.001
        || abs((float)$batches['LATER']->fresh()->available_quantity - 1) > 0.001
        || abs((float)$result['total_cost'] - 170) > 0.01
        || $moves->count() !== 2) {
        throw new RuntimeException('PRODUCTION FEFO ALLOCATION FAILED');
    }

    $service->consume(...$args);
    if (StockMovement::query()->where('reference_type', 'production_orders')
        ->where('reference_id', 999999)->count() !== 2) {
        throw new RuntimeException('DUPLICATE PRODUCTION CONSUMPTION');
    }
    echo "IDEMPOTENCY: PASSED\n";

    // A second reservation exists, but only 1 kg of valid batches remains.
    $inventory->reserve(1, (int)$product->id, 2, 1, 'qa_production', 2);
    $blocked = false;
    try {
        $service->consume(
            locationId: 1, productId: (int)$product->id,
            quantity: 2, userId: 1,
            referenceType: 'production_orders', referenceId: 999998,
            idempotencyKey: 'qa-production-reserved-fefo-insufficient'
        );
    } catch (ValidationException $e) {
        $blocked = true;
    }
    if (! $blocked) {
        throw new RuntimeException('EXPIRED PRODUCTION STOCK CONSUMED');
    }
    echo "INSUFFICIENT VALID BATCHES: BLOCKED\n";
    echo "=== PRODUCTION RESERVED FEFO PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}
$after = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
if (abs((float)$after->quantity - $stockBefore) > 0.001
    || abs((float)$after->reserved_quantity - $reservedBefore) > 0.001
    || InventoryBatch::count() !== $batchesBefore
    || StockMovement::count() !== $movesBefore
    || Product::findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== PRODUCTION FEFO QA COMPLETE ===\n";

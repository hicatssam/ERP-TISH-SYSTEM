<?php
/**
 * Read-write operations only inside one rolled-back QA transaction.
 * Verifies reserved production consumption cannot bypass batch tracking.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
$path = realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $path || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $path
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

$product = Product::where('sku', 'BRG-ING-BEEF')->firstOrFail();
$before = Inventory::where('location_id', 1)->where('product_id', $product->id)->firstOrFail();
$stock = (float)$before->quantity;
$reserved = (float)$before->reserved_quantity;
$movementCount = StockMovement::count();
if ($product->tracks_batch || $product->tracks_expiry || $reserved > 0.0005) {
    throw new RuntimeException('QA FIXTURE NOT SUITABLE');
}
DB::beginTransaction();
try {
    $product->update(['tracks_batch' => true, 'tracks_expiry' => true]);
    $inventory = app(InventoryService::class);
    $inventory->reserve(1, (int)$product->id, 1, 1, 'qa_production_guard', 1);
    echo "PRODUCTION RESERVATION: CREATED IN TEST\n";

    $blocked = false;
    try {
        DB::transaction(function () use ($inventory, $product) {
            $inventory->consumeReserved(
                1, (int)$product->id, 1, 1, 'production_orders', 999999,
                'qa-production-reserved-guard-001'
            );
        });
    } catch (ValidationException $e) {
        $blocked = true;
    }
    $current = Inventory::where('location_id', 1)->where('product_id', $product->id)->firstOrFail();
    $newMovements = StockMovement::count() - $movementCount;
    echo 'TRACKED RESERVED CONSUMPTION: '.($blocked ? 'BLOCKED' : 'ALLOWED')."\n";
    echo 'STOCK DELTA: '.((float)$current->quantity - $stock)." kg\n";
    echo "UNEXPECTED MOVEMENTS: {$newMovements}\n";
    if (! $blocked || abs((float)$current->quantity - $stock) > 0.001
        || abs((float)$current->reserved_quantity - 1) > 0.001
        || $newMovements !== 0) {
        throw new RuntimeException('PRODUCTION GUARD FAILED');
    }
    echo "=== PRODUCTION RESERVED GUARD PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}
$after = Inventory::where('location_id', 1)->where('product_id', $product->id)->firstOrFail();
if (abs((float)$after->quantity - $stock) > 0.001
    || abs((float)$after->reserved_quantity - $reserved) > 0.001
    || StockMovement::count() !== $movementCount
    || Product::findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";

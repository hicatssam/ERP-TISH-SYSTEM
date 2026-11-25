<?php

/**
 * Manual, rollback-only QA for restaurant order confirmation when tracked
 * stock is insufficient even though aggregate inventory remains positive.
 *
 * Run from the QA checkout using testing + explicit SQLite env vars.
 * Do not run on production. No database changes persist.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OrderStatus;
use App\Enums\RestaurantServiceType;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$expected = realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $expected || DB::connection()->getDriverName() !== 'sqlite'
    || realpath(DB::connection()->getDatabaseName()) !== $expected
    || is_file(dirname(__DIR__, 2).'/.env')
    || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY CHECK FAILED');
}

$product = Product::query()->where('sku', 'BRG-ING-BEEF')->where('is_raw_material', true)->firstOrFail();
$actor = User::query()->findOrFail(1);
$stockBefore = (float) Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->value('quantity');
$before = [
    'orders' => Order::query()->count(),
    'snapshots' => OrderInventoryConsumption::query()->count(),
    'movements' => StockMovement::query()->count(),
    'batches' => InventoryBatch::query()->count(),
];
if ($product->tracks_batch || $product->tracks_expiry
    || $before['batches'] !== 0 || abs($stockBefore - 205.35) > 0.001) {
    throw new RuntimeException('QA INITIAL STATE DOES NOT MATCH EXPECTATIONS');
}

DB::beginTransaction();
try {
    $product->update(['tracks_batch' => true, 'tracks_expiry' => true]);
    $created = [];
    foreach ([['EXPIRED', 10, -1], ['VALID', 2, 2]] as [$name, $qty, $days]) {
        $created[$name] = InventoryBatch::query()->create([
            'location_id' => 1,
            'product_id' => $product->id,
            'batch_number' => 'QA-FAIL-'.$name,
            'expiry_date' => today()->addDays($days)->toDateString(),
            'received_quantity' => $qty,
            'available_quantity' => $qty,
            'unit_cost' => 50,
            'base_unit_cost' => 50,
            'currency_id' => 1,
        ]);
    }

    RestaurantMenuItem::query()->updateOrCreate(
        ['location_id' => 1, 'product_id' => $product->id],
        ['display_name' => 'QA Expiry Failure', 'inventory_mode' => 'product', 'is_active' => true]
    );

    $order = Order::query()->create([
        'order_number' => 'QA-EXPIRY-FAIL-999998',
        'location_id' => 1,
        'created_by' => $actor->id,
        'status' => OrderStatus::Draft,
        'restaurant_service_type' => RestaurantServiceType::Takeaway,
        'subtotal' => 250,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 250,
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => 'QA Beef',
        'quantity' => 5,
        'unit_price' => 50,
        'discount_amount' => 0,
        'line_total' => 250,
    ]);

    $blocked = false;
    try {
        DB::transaction(function () use ($order, $actor): void {
            app(RestaurantOrderInventoryService::class)->consumeForConfirmation($order, $actor);
        });
    } catch (ValidationException $exception) {
        $blocked = true;
        echo "INSUFFICIENT VALID BATCHES: BLOCKED\n";
    }

    $snapshots = OrderInventoryConsumption::query()->where('order_id', $order->id)->count();
    $moves = StockMovement::query()->where('reference_type', 'orders')
        ->where('reference_id', $order->id)->count();
    $stockAfter = (float) Inventory::query()->where('location_id', 1)
        ->where('product_id', $product->id)->value('quantity');
    $expired = (float) $created['EXPIRED']->fresh()->available_quantity;
    $valid = (float) $created['VALID']->fresh()->available_quantity;

    echo "ORPHAN CONSUMPTION SNAPSHOTS: {$snapshots}\n";
    echo "UNEXPECTED STOCK MOVEMENTS: {$moves}\n";
    echo "STOCK DELTA: ".($stockAfter - $stockBefore)." kg\n";
    echo "EXPIRED BATCH REMAINING: {$expired} kg\n";
    echo "VALID BATCH REMAINING: {$valid} kg\n";

    if (! $blocked || $snapshots !== 0 || $moves !== 0
        || abs($stockAfter - $stockBefore) > 0.001
        || abs($expired - 10) > 0.001 || abs($valid - 2) > 0.001) {
        throw new RuntimeException('FAILED ORDER ATOMICITY TEST');
    }

    echo "=== FAILED ORDER ROLLBACK PASSED ===\n";
} finally {
    DB::rollBack();
    echo "OUTER TEST TRANSACTION: ROLLED BACK\n";
}

$after = [
    'orders' => Order::query()->count(),
    'snapshots' => OrderInventoryConsumption::query()->count(),
    'movements' => StockMovement::query()->count(),
    'batches' => InventoryBatch::query()->count(),
];
$stockFinal = (float) Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->value('quantity');
if ($before !== $after || Product::query()->findOrFail($product->id)->tracks_expiry
    || abs($stockFinal - $stockBefore) > 0.001) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== FAILED ORDER QA COMPLETE ===\n";

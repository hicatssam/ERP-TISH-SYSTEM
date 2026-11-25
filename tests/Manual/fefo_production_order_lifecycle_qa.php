<?php
/**
 * Rollback-only actual ProductionOrderService lifecycle QA.
 * Uses existing recipe metadata for a synthetic released production order.
 * No production, no external HTTP and no persistent DB changes.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ProductionOrderStatus;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Production\ProductionOrderService;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
$expected = realpath('/tmp/tish-finance-qa-20261125/database/tish-production-schema-qa.sqlite');
if (! $expected || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $expected
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

$product = Product::query()->where('sku', 'BRG-ING-BEEF')->firstOrFail();
$recipe = Recipe::query()->whereNotNull('product_id')
    ->where('yield_quantity', '>', 0)
    ->where('product_id', '!=', $product->id)
    ->whereHas('product')->orderBy('id')->firstOrFail();
$user = User::findOrFail(1);
$stock = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
$initial = [
    'raw_stock' => (float) $stock->quantity,
    'raw_reserved' => (float) $stock->reserved_quantity,
    'batch_count' => InventoryBatch::count(),
    'movement_count' => StockMovement::count(),
    'production_orders' => ProductionOrder::count(),
];
$outputProductId = (int) $recipe->product_id;
$outputBefore = (float) (Inventory::query()->where('location_id', 1)
    ->where('product_id', $outputProductId)->value('quantity') ?? 0);
if ($product->tracks_batch || $product->tracks_expiry
    || $initial['batch_count'] !== 0 || abs($initial['raw_reserved']) > 0.001) {
    throw new RuntimeException('QA INITIAL STATE FAILED');
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
            'batch_number' => 'QA-ORDER-PRODUCTION-'.$name,
            'expiry_date' => today()->addDays($days)->toDateString(),
            'received_quantity' => $qty,
            'available_quantity' => $qty,
            'unit_cost' => $cost,
            'base_unit_cost' => $cost,
            'currency_id' => 1,
        ]);
    }

    $order = ProductionOrder::query()->create([
        'production_number' => 'QA-FEFO-PRD-999999',
        'location_id' => 1,
        'recipe_id' => $recipe->id,
        'product_id' => $recipe->product_id,
        'status' => ProductionOrderStatus::Released,
        'quality_required' => false,
        'planned_output_quantity' => 1,
        'planned_material_cost' => 170,
        'planned_labor_cost' => 0,
        'planned_overhead_cost' => 0,
        'planned_total_cost' => 170,
        'planned_unit_cost' => 170,
        'created_by' => $user->id,
    ]);
    $material = $order->items()->create([
        'product_id' => $product->id,
        'recipe_quantity' => 3,
        'planned_stock_quantity' => 3,
        'planned_total_cost' => 170,
        'planned_quantity' => 3,
        'reserved_quantity' => 0,
        'waste_percent' => 0,
        'planned_unit_cost' => 56.6667,
        'planned_cost' => 170,
    ]);

    $service = app(ProductionOrderService::class);
    $started = $service->start($order, $user);
    $startStatus = $started->statusValue();
    $reservedAfterStart = (float) Inventory::query()
        ->where('location_id', 1)->where('product_id', $product->id)
        ->value('reserved_quantity');
    echo "PRODUCTION START STATUS: {$startStatus}\n";
    echo "RESERVED AFTER START: {$reservedAfterStart} kg\n";
    if ($startStatus !== ProductionOrderStatus::InProgress->value
        || abs($reservedAfterStart - 3) > 0.001) {
        throw new RuntimeException('PRODUCTION START FAILED');
    }

    $completed = $service->submitCompletion($order, [
        'actual_output_quantity' => 1,
        'items' => [['id' => $material->id, 'actual_quantity' => 3]],
    ], $user);

    $finished = $completed->statusValue();
    $material = $material->fresh();
    $raw = Inventory::query()->where('location_id', 1)
        ->where('product_id', $product->id)->firstOrFail();
    $actualCost = (float) $material->actual_cost;
    $moves = StockMovement::query()
        ->where('reference_type', 'production_orders')
        ->where('reference_id', $order->id)
        ->where('reason', 'production_consumption')
        ->whereNotNull('inventory_batch_id')->get();

    echo "PRODUCTION COMPLETION STATUS: {$finished}\n";
    echo "RAW STOCK DELTA: ".($initial['raw_stock'] - (float)$raw->quantity)." kg\n";
    echo "RAW RESERVED REMAINING: {$raw->reserved_quantity} kg\n";
    echo "MATERIAL COST: {$actualCost}\n";
    echo "BATCH MOVEMENTS: ".$moves->count()."\n";
    foreach ($batches as $name => $batch) {
        echo "{$name}: ".$batch->fresh()->available_quantity." kg remaining\n";
    }

    if ($finished !== ProductionOrderStatus::Completed->value
        || abs($initial['raw_stock'] - (float)$raw->quantity - 3) > 0.001
        || abs((float)$raw->reserved_quantity) > 0.001
        || abs($actualCost - 170) > 0.01
        || $moves->count() !== 2
        || abs((float)$batches['EXPIRED']->fresh()->available_quantity - 8) > 0.001
        || abs((float)$batches['EARLY']->fresh()->available_quantity) > 0.001
        || abs((float)$batches['LATER']->fresh()->available_quantity - 1) > 0.001) {
        throw new RuntimeException('PRODUCTION ORDER FEFO FAILED');
    }

    if ($outputProductId !== (int)$product->id) {
        $outputAfter = (float) (Inventory::query()->where('location_id', 1)
            ->where('product_id', $outputProductId)->value('quantity') ?? 0);
        if (abs(($outputAfter - $outputBefore) - 1) > 0.001) {
            throw new RuntimeException('FINISHED OUTPUT NOT POSTED');
        }
    }

    echo "=== PRODUCTION ORDER FEFO LIFECYCLE PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}

$finalStock = Inventory::query()->where('location_id', 1)
    ->where('product_id', $product->id)->firstOrFail();
$outputFinal = (float) (Inventory::query()->where('location_id', 1)
    ->where('product_id', $outputProductId)->value('quantity') ?? 0);

if (abs((float)$finalStock->quantity - $initial['raw_stock']) > 0.001
    || abs((float)$finalStock->reserved_quantity - $initial['raw_reserved']) > 0.001
    || InventoryBatch::count() !== $initial['batch_count']
    || StockMovement::count() !== $initial['movement_count']
    || ProductionOrder::count() !== $initial['production_orders']
    || abs($outputFinal - $outputBefore) > 0.001
    || Product::findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}

echo "QA DATA UNCHANGED: YES\n";
echo "=== PRODUCTION ORDER E2E QA COMPLETE ===\n";

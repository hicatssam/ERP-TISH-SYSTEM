<?php
/**
 * Rollback-only integration QA for ProductionOrderService::create/release/cancel
 * on the migrated copy of the legacy production schema.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ProductionOrderStatus;
use App\Models\Inventory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Production\ProductionOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__, 2);
$expected = realpath('/tmp/tish-finance-qa-20261125/database/tish-migration-review-qa.sqlite');
if (! $expected || DB::getDriverName() !== 'sqlite'
    || realpath(DB::getDatabaseName()) !== $expected
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

foreach (['quality_required','materials_consumed_at'] as $column) {
    if (! Schema::hasColumn('production_orders', $column)) {
        throw new RuntimeException("Missing migration column: {$column}");
    }
}
foreach (['planned_quantity','reserved_quantity','planned_stock_quantity','recipe_quantity'] as $column) {
    if (! Schema::hasColumn('production_order_items', $column)) {
        throw new RuntimeException("Missing production item column: {$column}");
    }
}

$recipe = Recipe::query()->active()->whereHas('items')
    ->whereHas('product')->with(['items.ingredient'])->orderBy('id')->firstOrFail();
$user = User::query()->findOrFail(1);
$ordersBefore = ProductionOrder::query()->count();
$itemsBefore = ProductionOrderItem::query()->count();
$stocksBefore = Inventory::query()->orderBy('id')->get(['id','quantity','reserved_quantity'])
    ->map(fn ($row) => [$row->id, (string)$row->quantity, (string)$row->reserved_quantity])->all();

DB::beginTransaction();
try {
    $service = app(ProductionOrderService::class);
    $order = $service->create([
        'recipe_id' => $recipe->id,
        'location_id' => 1,
        'planned_output_quantity' => (float) $recipe->yield_quantity,
        'notes' => 'QA only - schema compatibility',
    ], $user);
    $order = $order->fresh(['items']);

    if ($order->statusValue() !== ProductionOrderStatus::Draft->value
        || ! str_starts_with($order->production_number, 'PRD-')
        || str_contains($order->production_number, 'TMP')
        || $order->items->count() !== $recipe->items->count()) {
        throw new RuntimeException('PRODUCTION CREATE FAILED');
    }

    foreach ($order->items as $item) {
        $legacy = DB::table('production_order_items')->where('id', $item->id)->firstOrFail();
        if (abs((float)$legacy->planned_stock_quantity - (float)$item->planned_quantity) > 0.001
            || abs((float)$legacy->recipe_quantity - (float)$item->planned_quantity) > 0.001
            || abs((float)$legacy->planned_total_cost - (float)$item->planned_cost) > 0.01) {
            throw new RuntimeException('LEGACY PRODUCTION SNAPSHOT MISMATCH');
        }
    }
    echo "PRODUCTION CREATE: PASSED\n";
    echo "CREATED ITEMS: ".$order->items->count()."\n";
    echo "LEGACY SNAPSHOTS: MATCHED\n";

    $released = $service->release($order, $user);
    if ($released->statusValue() !== ProductionOrderStatus::Released->value) {
        throw new RuntimeException('PRODUCTION RELEASE FAILED');
    }
    echo "PRODUCTION RELEASE: PASSED\n";

    $cancelled = $service->cancel($order, $user, 'QA rollback verification');
    if ($cancelled->statusValue() !== ProductionOrderStatus::Cancelled->value) {
        throw new RuntimeException('PRODUCTION CANCELLATION FAILED');
    }
    echo "PRODUCTION CANCELLATION: PASSED\n";
    echo "=== LEGACY PRODUCTION CREATE LIFECYCLE PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}

$stocksAfter = Inventory::query()->orderBy('id')->get(['id','quantity','reserved_quantity'])
    ->map(fn ($row) => [$row->id, (string)$row->quantity, (string)$row->reserved_quantity])->all();
if (ProductionOrder::count() !== $ordersBefore
    || ProductionOrderItem::count() !== $itemsBefore
    || $stocksAfter !== $stocksBefore) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== LEGACY PRODUCTION CREATE QA COMPLETE ===\n";

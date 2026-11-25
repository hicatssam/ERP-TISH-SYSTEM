<?php
/**
 * QA-only rollback test: expiry-tracked products must reject undated batches
 * for restaurant sales, transfers, and reserved production consumption.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\MovementReason;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\SaleBatchAllocationService;
use App\Services\Inventory\ReservedProductionBatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root=dirname(__DIR__,2);
$expected=realpath('/tmp/tish-finance-qa-20261125/database/tish-procurement-e2e.sqlite');
if (! $expected || DB::getDriverName()!=='sqlite'
    || realpath(DB::getDatabaseName())!==$expected
    || is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('QA DATABASE SAFETY FAILED');
}

$product=Product::where('sku','BRG-ING-BEEF')->firstOrFail();
$original=Inventory::where('location_id',1)->where('product_id',$product->id)->firstOrFail();
$before=(float)$original->quantity;
$reservedBefore=(float)$original->reserved_quantity;
$movementsBefore=StockMovement::count();
$batchesBefore=InventoryBatch::count();
if ($product->tracks_batch || $product->tracks_expiry || $batchesBefore!==0
    || abs($reservedBefore)>0.001) {
    throw new RuntimeException('QA INITIAL STATE DOES NOT MATCH');
}

DB::beginTransaction();
try {
    $product->update(['tracks_batch'=>true,'tracks_expiry'=>true]);
    $batch=InventoryBatch::create([
        'product_id'=>$product->id,'location_id'=>1,
        'batch_number'=>'QA-NO-EXPIRY-20261125','expiry_date'=>null,
        'received_quantity'=>5,'available_quantity'=>5,
        'unit_cost'=>50,'base_unit_cost'=>50,'currency_id'=>1,
    ]);
    $allocation=app(SaleBatchAllocationService::class);
    foreach ([MovementReason::OrderSale,MovementReason::TransferDispatch] as $reason) {
        $blocked=false;
        try {
            $allocation->consume(
                locationId:1, productId:(int)$product->id, quantity:1,
                userId:1, referenceType:'qa_missing_expiry',referenceId:999998,
                idempotencyKey:'qa-expiry-'. $reason->value,reason:$reason
            );
        } catch (ValidationException $e) { $blocked=true; }
        echo strtoupper($reason->value).': '.($blocked?'BLOCKED':'ALLOWED')."\n";
        if (!$blocked) throw new RuntimeException('UNDATED BATCH ACCEPTED');
    }

    $inventory=app(InventoryService::class);
    $inventory->reserve(1,(int)$product->id,1,1,'qa_missing_expiry',999998);
    $blocked=false;
    try {
        app(ReservedProductionBatchService::class)->consume(
            locationId:1,productId:(int)$product->id,quantity:1,
            userId:1,referenceType:'qa_missing_expiry',referenceId:999998,
            idempotencyKey:'qa-undated-prod-999998'
        );
    } catch (ValidationException $e) {$blocked=true;}
    echo 'PRODUCTION: '.($blocked?'BLOCKED':'ALLOWED')."\n";
    if (!$blocked) throw new RuntimeException('UNDATED BATCH USED IN PRODUCTION');

    $directBlocked=false;
    try {
        $inventory->decrease(
            locationId:1,productId:(int)$product->id,quantity:1,
            reason:MovementReason::TransferDispatch,userId:1,
            referenceType:'qa_missing_expiry',referenceId:999998,
            idempotencyKey:'qa-undated-direct-999998',
            inventoryBatchId:(int)$batch->id
        );
    } catch (ValidationException $e) {$directBlocked=true;}
    echo 'DIRECT BATCH DISPATCH: '.($directBlocked?'BLOCKED':'ALLOWED')."\n";

    $current=Inventory::where('location_id',1)->where('product_id',$product->id)->firstOrFail();
    if (!$directBlocked || abs((float)$current->quantity-$before)>0.001
        || abs((float)$batch->fresh()->available_quantity-5)>0.001
        || StockMovement::count()!==$movementsBefore) {
        throw new RuntimeException('UNDATED BATCH GUARD FAILED');
    }
    echo "=== MISSING EXPIRY GUARD PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST TRANSACTION: ROLLED BACK\n";
}
$after=Inventory::where('location_id',1)->where('product_id',$product->id)->firstOrFail();
if (abs((float)$after->quantity-$before)>0.001
    || abs((float)$after->reserved_quantity-$reservedBefore)>0.001
    || InventoryBatch::count()!==$batchesBefore
    || StockMovement::count()!==$movementsBefore
    || Product::findOrFail($product->id)->tracks_expiry) {
    throw new RuntimeException('QA CLEANUP FAILED');
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== MISSING EXPIRY QA COMPLETE ===\n";

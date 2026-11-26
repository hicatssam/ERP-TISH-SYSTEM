<?php
/**
 * Actual Laravel FEFO service test on the dedicated "Tish-Data" QA MySQL DB.
 * Never use against a production DB; deliberately refuses any other schema.
 * Adds ONLY minimal synthetic tables in QA; does not run general migrations.
 * All test rows are rolled back. Schema fixture tables remain for later QA.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\MovementReason;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\SaleBatchAllocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
if (DB::getDriverName() !== 'mysql'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'Tish-Data'
    || DB::selectOne('SELECT CURRENT_USER() AS user')->user !== 'tishqa@%'
    || is_file($root.'/.env')
    || is_file($root.'/bootstrap/cache/config.php')
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_USERNAME') !== 'tishqa'
    || getenv('DB_DATABASE') !== 'Tish-Data') {
    throw new RuntimeException('MYSQL QA SAFETY CHECK FAILED');
}

$originalTables = collect(DB::select(
    "SELECT TABLE_NAME AS name FROM information_schema.tables
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'"
))->pluck('name')->sort()->values()->all();
$baseTables = ['migrations', 'production_order_items', 'production_orders'];
sort($baseTables);

$fixtureTables = [
    'categories', 'locations', 'users', 'products',
    'inventories', 'inventory_batches', 'stock_movements',
    'location_products',
];
$allTables = array_merge($baseTables, $fixtureTables);
sort($allTables);
if ($originalTables !== $baseTables && $originalTables !== $allTables) {
    throw new RuntimeException('Unexpected QA tables; refusing to modify schema: '.implode(',', $originalTables));
}
if ((int) DB::table('production_orders')->where('production_number', 'QA-LEGACY-0001')->count() !== 1) {
    throw new RuntimeException('MYSQL QA LEGACY FIXTURE NOT FOUND');
}

if ($originalTables === $baseTables) {
    Schema::create('categories', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->timestamps();
    });
    Schema::create('locations', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('code')->unique(); $t->string('type')->default('branch'); $t->timestamps();
    });
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('username')->unique(); $t->string('email')->unique();
        $t->string('password'); $t->timestamps();
    });
    Schema::create('products', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('category_id'); $t->string('name');
        $t->string('sku')->unique(); $t->string('unit')->default('kg');
        $t->boolean('is_active')->default(true);
        $t->boolean('tracks_batch')->default(false);
        $t->boolean('tracks_expiry')->default(false); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('inventories', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('location_id');
        $t->unsignedBigInteger('product_id'); $t->decimal('quantity', 14, 3)->default(0);
        $t->decimal('reserved_quantity', 14, 3)->default(0);
        $t->decimal('damaged_quantity', 14, 3)->default(0);
        $t->decimal('in_transit_quantity', 14, 3)->default(0);
        $t->decimal('unit_cost', 16, 4)->default(0);
        $t->timestamp('last_movement_at')->nullable(); $t->timestamps();
        $t->unique(['location_id', 'product_id']);
    });
    Schema::create('inventory_batches', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('location_id');
        $t->string('batch_number')->nullable(); $t->date('manufacturing_date')->nullable();
        $t->date('expiry_date')->nullable();
        $t->decimal('received_quantity', 14, 3)->default(0);
        $t->decimal('available_quantity', 14, 3)->default(0);
        $t->decimal('unit_cost', 16, 4)->default(0);
        $t->decimal('base_unit_cost', 16, 4)->default(0);
        $t->unsignedBigInteger('currency_id')->nullable(); $t->timestamps();
    });
    Schema::create('stock_movements', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('location_id');
        $t->unsignedBigInteger('product_id');
        $t->string('movement_type'); $t->string('reason');
        $t->decimal('quantity', 14, 3);
        $t->decimal('balance_before', 14, 3)->default(0);
        $t->decimal('balance_after', 14, 3)->default(0);
        $t->string('reference_type')->nullable(); $t->unsignedBigInteger('reference_id')->nullable();
        $t->unsignedBigInteger('created_by'); $t->text('note')->nullable();
        $t->unsignedBigInteger('currency_id')->nullable();
        $t->decimal('exchange_rate', 18, 8)->nullable();
        $t->decimal('unit_cost', 16, 4)->nullable(); $t->decimal('base_unit_cost', 16, 4)->nullable();
        $t->decimal('total_cost', 18, 2)->nullable(); $t->decimal('base_total_cost', 18, 2)->nullable();
        $t->unsignedBigInteger('inventory_batch_id')->nullable();
        $t->string('idempotency_key', 190)->nullable()->unique();
        $t->timestamp('created_at')->useCurrent();
    });
    Schema::create('location_products', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('location_id'); $t->unsignedBigInteger('product_id');
        $t->decimal('minimum_stock_level', 14, 3)->default(0); $t->timestamps();
    });
    echo "MYSQL QA MINIMAL SCHEMA: CREATED\n";
} else {
    echo "MYSQL QA MINIMAL SCHEMA: ALREADY PRESENT\n";
}

foreach ($fixtureTables as $table) {
    if (DB::table($table)->count() !== 0) {
        throw new RuntimeException("QA fixture table is not empty: {$table}");
    }
}

// A previous QA run created products before its missing soft-delete field
// was discovered. Only reconcile this known empty fixture table after the
// strict database identity, table-list and emptiness checks above.
if (! Schema::hasColumn('products', 'deleted_at')) {
    Schema::table('products', function (Blueprint $table): void {
        $table->softDeletes();
    });
    echo "MYSQL QA PRODUCT SOFT-DELETE COLUMN: ADDED\n";
}

DB::beginTransaction();
try {
    DB::table('categories')->insert(['id'=>999901,'name'=>'QA Only','slug'=>'qa-fefo-only']);
    DB::table('locations')->insert(['id'=>999901,'name'=>'QA Store','code'=>'QA-FEFO','type'=>'branch']);
    DB::table('users')->insert(['id'=>999901,'username'=>'qa-fefo','email'=>'qa-fefo@example.invalid','password'=>'not-for-login']);
    DB::table('products')->insert([
        'id'=>999901,'category_id'=>999901,'name'=>'QA Batch Ingredient','sku'=>'QA-FEFO-ING',
        'unit'=>'kg','tracks_batch'=>1,'tracks_expiry'=>1,'is_active'=>1,
    ]);
    DB::table('inventories')->insert([
        'location_id'=>999901,'product_id'=>999901,'quantity'=>12,'unit_cost'=>50,
    ]);
    $ids = [];
    foreach ([['EXPIRED',8,-1,20],['EARLY',2,1,50],['LATER',2,5,70]] as [$name,$qty,$days,$cost]) {
        $ids[$name] = DB::table('inventory_batches')->insertGetId([
            'product_id'=>999901,'location_id'=>999901,'batch_number'=>'QA-'.$name,
            'expiry_date'=>today()->addDays($days)->toDateString(),
            'received_quantity'=>$qty,'available_quantity'=>$qty,
            'unit_cost'=>$cost,'base_unit_cost'=>$cost,
        ]);
    }

    $allocator = app(SaleBatchAllocationService::class);
    $args = [
        'locationId'=>999901, 'productId'=>999901, 'quantity'=>3.0,
        'userId'=>999901, 'referenceType'=>'qa_fefo', 'referenceId'=>999901,
        'idempotencyKey'=>'mysql-qa-fefo-consumption-999901',
    ];
    $allocator->consume(...$args);
    $quantity = (float) DB::table('inventories')->where('product_id',999901)->value('quantity');
    $movementCount = DB::table('stock_movements')->where('reference_type','qa_fefo')->count();
    $balances = [];
    foreach ($ids as $name=>$id) {
        $balances[$name] = (float) DB::table('inventory_batches')->where('id',$id)->value('available_quantity');
    }
    if (abs($quantity-9)>0.001 || $movementCount!==2
        || abs($balances['EXPIRED']-8)>0.001 || abs($balances['EARLY'])>0.001
        || abs($balances['LATER']-1)>0.001) {
        throw new RuntimeException('MYSQL LARAVEL FEFO ALLOCATION FAILED');
    }
    echo "LARAVEL FEFO CONSUMPTION: PASSED\n";
    echo "INVENTORY: {$quantity} kg | EXPIRED {$balances['EXPIRED']} | EARLY {$balances['EARLY']} | LATER {$balances['LATER']}\n";
    echo "BATCH MOVEMENTS: {$movementCount}\n";

    $allocator->consume(...$args);
    if ((float)DB::table('inventories')->where('product_id',999901)->value('quantity') !== $quantity
        || DB::table('stock_movements')->where('reference_type','qa_fefo')->count()!==2) {
        throw new RuntimeException('MYSQL IDEMPOTENCY FAILED');
    }
    echo "IDEMPOTENCY: PASSED\n";

    $blocked=false;
    try {
        $allocator->consume(
            locationId:999901, productId:999901, quantity:2,
            userId:999901, referenceType:'qa_fefo', referenceId:999901,
            idempotencyKey:'mysql-qa-insufficient-999901'
        );
    } catch (ValidationException $e) { $blocked=true; }
    if (!$blocked) throw new RuntimeException('INVALID FEFO QUANTITY WAS ACCEPTED');
    echo "INSUFFICIENT VALID BATCHES: BLOCKED\n";

    // A failed allocation must not leave movements or stock changes.
    if (DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 2
        || abs((float) DB::table('inventories')->where('product_id', 999901)->value('quantity') - 9) > 0.001) {
        throw new RuntimeException('MYSQL INSUFFICIENT ALLOCATION WAS NOT ATOMIC');
    }
    echo "FAILED ALLOCATION ROLLBACK: PASSED\n";

    $directBlocked = false;
    try {
        app(InventoryService::class)->decrease(
            locationId: 999901, productId: 999901, quantity: 1,
            reason: MovementReason::OrderSale, userId: 999901,
            referenceType: 'qa_fefo', referenceId: 999901,
            idempotencyKey: 'mysql-qa-direct-without-batch-999901'
        );
    } catch (ValidationException $e) { $directBlocked = true; }
    if (! $directBlocked
        || DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 2
        || abs((float) DB::table('inventories')->where('product_id', 999901)->value('quantity') - 9) > 0.001) {
        throw new RuntimeException('MYSQL DIRECT TRACKED SALE GUARD FAILED');
    }
    echo "DIRECT UNALLOCATED SALE: BLOCKED\n";

    // Return 1kg to the actual sale batch, never to expired goods.
    $returnArgs = [
        'locationId' => 999901, 'productId' => 999901, 'quantity' => 1.0,
        'userId' => 999901, 'referenceType' => 'qa_fefo', 'referenceId' => 999901,
        'consumptionPrefix' => 'mysql-qa-fefo-consumption-999901',
        'idempotencyKey' => 'mysql-qa-fefo-return-999901',
    ];
    $allocator->restore(...$returnArgs);
    $restoredQuantity = (float) DB::table('inventories')->where('product_id', 999901)->value('quantity');
    $restoredExpired = (float) DB::table('inventory_batches')->where('id', $ids['EXPIRED'])->value('available_quantity');
    $restoredEarly = (float) DB::table('inventory_batches')->where('id', $ids['EARLY'])->value('available_quantity');
    $restoredLater = (float) DB::table('inventory_batches')->where('id', $ids['LATER'])->value('available_quantity');
    if (abs($restoredQuantity - 10) > 0.001
        || abs($restoredExpired - 8) > 0.001
        || abs($restoredEarly) > 0.001
        || abs($restoredLater - 2) > 0.001
        || DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 3) {
        throw new RuntimeException('MYSQL ORIGINAL BATCH RESTORATION FAILED');
    }
    echo "RESTORE TO ORIGINAL BATCH: PASSED\n";

    $allocator->restore(...$returnArgs);
    if (abs((float) DB::table('inventories')->where('product_id', 999901)->value('quantity') - 10) > 0.001
        || abs((float) DB::table('inventory_batches')->where('id', $ids['LATER'])->value('available_quantity') - 2) > 0.001
        || DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 3) {
        throw new RuntimeException('MYSQL RESTORE IDEMPOTENCY FAILED');
    }
    echo "RESTORE IDEMPOTENCY: PASSED\n";

    // Resell all 2kg still valid; the remaining 8kg expired must stay untouched.
    $allocator->consume(
        locationId: 999901, productId: 999901, quantity: 2.0,
        userId: 999901, referenceType: 'qa_fefo', referenceId: 999901,
        idempotencyKey: 'mysql-qa-fefo-resell-999901'
    );
    if (abs((float) DB::table('inventories')->where('product_id', 999901)->value('quantity') - 8) > 0.001
        || abs((float) DB::table('inventory_batches')->where('id', $ids['LATER'])->value('available_quantity')) > 0.001
        || abs((float) DB::table('inventory_batches')->where('id', $ids['EXPIRED'])->value('available_quantity') - 8) > 0.001
        || DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 4) {
        throw new RuntimeException('MYSQL RESALE AFTER RESTORE FAILED');
    }
    echo "RESALE AFTER RESTORE: PASSED\n";
    echo "EXPIRED BATCH REMAINS UNTOUCHED: YES\n";

    $exhaustedBlocked = false;
    try {
        $allocator->consume(
            locationId: 999901, productId: 999901, quantity: 1.0,
            userId: 999901, referenceType: 'qa_fefo', referenceId: 999901,
            idempotencyKey: 'mysql-qa-fefo-exhausted-999901'
        );
    } catch (ValidationException $e) { $exhaustedBlocked = true; }
    if (! $exhaustedBlocked
        || abs((float) DB::table('inventories')->where('product_id', 999901)->value('quantity') - 8) > 0.001
        || DB::table('stock_movements')->where('reference_type', 'qa_fefo')->count() !== 4) {
        throw new RuntimeException('MYSQL EXHAUSTED VALID STOCK WAS NOT BLOCKED ATOMICALLY');
    }
    echo "EXPIRED-ONLY INVENTORY SALE: BLOCKED\n";
    echo "=== MYSQL LARAVEL FEFO SERVICE AND RESTORE PASSED ===\n";
} finally {
    DB::rollBack();
    echo "TEST DATA: ROLLED BACK\n";
}
foreach ($fixtureTables as $table) {
    if (DB::table($table)->count()!==0) throw new RuntimeException("QA CLEANUP FAILED: {$table}");
}
echo "QA DATA UNCHANGED: YES\n";
echo "=== MYSQL LARAVEL FEFO QA COMPLETE ===\n";

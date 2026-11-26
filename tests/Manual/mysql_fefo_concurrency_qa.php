<?php
/**
 * Two REAL independent Laravel processes compete for the same FEFO stock on
 * the dedicated Tish-Data MySQL QA schema. No production or customer data.
 *
 * Unlike rollback-only single-process tests, this test must commit synthetic
 * fixture rows so the second connection can see them. It removes all of its
 * fixtures on success AND failure. If interrupted, it fails closed on rerun.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Inventory\SaleBatchAllocationService;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
if (! function_exists('proc_open')
    || DB::getDriverName() !== 'mysql'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'Tish-Data'
    || DB::selectOne('SELECT CURRENT_USER() AS u')->u !== 'tishqa@%'
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_USERNAME') !== 'tishqa'
    || getenv('DB_DATABASE') !== 'Tish-Data'
    || ! getenv('DB_PASSWORD')
    || is_file($root.'/.env')
    || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('MYSQL CONCURRENCY QA SAFETY CHECK FAILED');
}

$requiredTables = [
    'categories', 'inventories', 'inventory_batches', 'location_products',
    'locations', 'migrations', 'production_order_items',
    'production_orders', 'products', 'stock_movements', 'users',
];
sort($requiredTables);
$tables = collect(DB::select(
    "SELECT TABLE_NAME AS name FROM information_schema.tables
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
))->pluck('name')->sort()->values()->all();
if ($tables !== $requiredTables) {
    throw new RuntimeException('Unexpected QA tables; refusing writes');
}

$innoDBTables = ['inventories','inventory_batches','stock_movements'];
$engines = collect(DB::select(
    "SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.tables
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
     ('inventories','inventory_batches','stock_movements')"
))->pluck('engine','name');
foreach ($innoDBTables as $table) {
    if (($engines[$table] ?? null) !== 'InnoDB') {
        throw new RuntimeException("QA engine is not InnoDB: {$table}");
    }
}
if ((int) DB::table('production_orders')
    ->where('production_number', 'QA-LEGACY-0001')->count() !== 1) {
    throw new RuntimeException('Legacy QA sentinel missing');
}

$fixtureTables = [
    'categories', 'locations', 'users', 'products', 'inventories',
    'inventory_batches', 'stock_movements', 'location_products',
];
foreach ($fixtureTables as $table) {
    if (DB::table($table)->count() !== 0) {
        throw new RuntimeException("Nonempty QA fixture table {$table}; refusing writes");
    }
}

$fixtureId = 999902;
$proc = null;
$pipes = [];
$seeded = false;
$success = false;
try {
    DB::transaction(function () use ($fixtureId): void {
        DB::table('categories')->insert([
            'id'=>$fixtureId,'name'=>'QA Concurrent','slug'=>'qa-fefo-concurrent',
        ]);
        DB::table('locations')->insert([
            'id'=>$fixtureId,'name'=>'QA Concurrent Store','code'=>'QAFCO','type'=>'branch',
        ]);
        DB::table('users')->insert([
            'id'=>$fixtureId,'username'=>'qa-fefo-concurrent',
            'email'=>'qa-fefo-concurrent@example.invalid','password'=>'not-for-login',
        ]);
        DB::table('products')->insert([
            'id'=>$fixtureId,'category_id'=>$fixtureId,
            'name'=>'QA Concurrent Ingredient','sku'=>'QA-FEFO-CONCURRENT',
            'unit'=>'kg','tracks_batch'=>true,'tracks_expiry'=>true,'is_active'=>true,
        ]);
        DB::table('inventories')->insert([
            'location_id'=>$fixtureId,'product_id'=>$fixtureId,
            'quantity'=>12,'unit_cost'=>50,
        ]);
        foreach ([
            ['EXPIRED', 8, -1, 20],
            ['EARLY', 2, 1, 50],
            ['LATER', 2, 5, 70],
        ] as [$name,$qty,$days,$cost]) {
            DB::table('inventory_batches')->insert([
                'location_id'=>$fixtureId,'product_id'=>$fixtureId,
                'batch_number'=>'QA-CONCURRENT-'.$name,
                'expiry_date'=>today()->addDays($days)->toDateString(),
                'received_quantity'=>$qty,'available_quantity'=>$qty,
                'unit_cost'=>$cost,'base_unit_cost'=>$cost,
            ]);
        }
    });
    $seeded = true;
    echo "QA SYNTHETIC FIXTURE: COMMITTED FOR TWO CONNECTIONS\n";

    // The parent remains inside its outer transaction after the service
    // deducts 3kg. The child then tries to consume 2kg from only 1kg valid
    // remaining, and must not complete while this parent holds its row locks.
    DB::beginTransaction();
    app(SaleBatchAllocationService::class)->consume(
        locationId:$fixtureId, productId:$fixtureId, quantity:3,
        userId:$fixtureId, referenceType:'mysql_qa_concurrency',
        referenceId:$fixtureId, idempotencyKey:'mysql-qa-concurrency-first-999902'
    );

    $descriptors = [
        0 => ['pipe','r'],
        1 => ['pipe','w'],
        2 => ['pipe','w'],
    ];
    $env = getenv();
    $env['TISH_MYSQL_QA_CHILD'] = '1';
    $proc = proc_open(
        [PHP_BINARY, __DIR__.'/mysql_fefo_concurrent_worker_qa.php'],
        $descriptors,
        $pipes,
        $root,
        $env
    );
    if (! is_resource($proc)) {
        throw new RuntimeException('Could not start QA Laravel worker');
    }
    fclose($pipes[0]);
    unset($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $readyDeadline = microtime(true) + 10;
    while (! str_contains($stdout, "WORKER READY\n")
        && microtime(true) < $readyDeadline) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        if (! proc_get_status($proc)['running']) {
            throw new RuntimeException("Worker exited before ready: {$stdout} {$stderr}");
        }
        usleep(50000);
    }
    if (! str_contains($stdout, "WORKER READY\n")) {
        throw new RuntimeException("Worker startup timeout: {$stdout} {$stderr}");
    }
    usleep(800000);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    if (! proc_get_status($proc)['running']) {
        throw new RuntimeException("Second operation finished while first held locks: {$stdout} {$stderr}");
    }
    echo "SECOND LARAVEL REQUEST: WAITING ON ROW LOCK\n";

    DB::commit();
    echo "FIRST LARAVEL REQUEST: COMMITTED\n";

    $workerDeadline = microtime(true) + 12;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $running = proc_get_status($proc)['running'];
        if (! $running) break;
        usleep(50000);
    } while (microtime(true) < $workerDeadline);

    if ($running) {
        throw new RuntimeException("Worker timeout: {$stdout} {$stderr}");
    }
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    if (! str_contains($stdout, 'WORKER FEFO SHORTAGE: BLOCKED')
        || ! preg_match('/WORKER WAIT_SECONDS: ([0-9.]+)/', $stdout, $m)
        || (float)$m[1] < 0.5) {
        throw new RuntimeException("Concurrent FEFO outcome invalid: {$stdout} {$stderr}");
    }
    echo trim($stdout)."\n";

    $qty = (float) DB::table('inventories')
        ->where('product_id',$fixtureId)->value('quantity');
    $movements = (int) DB::table('stock_movements')
        ->where('reference_type','mysql_qa_concurrency')->count();
    $balances = DB::table('inventory_batches')
        ->where('product_id',$fixtureId)
        ->pluck('available_quantity','batch_number');
    if (abs($qty - 9) > 0.001
        || $movements !== 2
        || abs((float)$balances['QA-CONCURRENT-EXPIRED'] - 8) > 0.001
        || abs((float)$balances['QA-CONCURRENT-EARLY']) > 0.001
        || abs((float)$balances['QA-CONCURRENT-LATER'] - 1) > 0.001) {
        throw new RuntimeException('Concurrent stock ledger was inconsistent');
    }
    echo "FINAL STOCK: 9 kg | BATCH MOVEMENTS: 2\n";
    echo "=== MYSQL LARAVEL FEFO CONCURRENCY PASSED ===\n";
    $success = true;
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if (is_resource($proc)) {
        if (proc_get_status($proc)['running']) {
            proc_terminate($proc);
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) fclose($pipe);
        }
        proc_close($proc);
    }

    if ($seeded) {
        DB::transaction(function () use ($fixtureId): void {
            DB::table('stock_movements')->where('product_id',$fixtureId)->delete();
            DB::table('inventory_batches')->where('product_id',$fixtureId)->delete();
            DB::table('inventories')->where('product_id',$fixtureId)->delete();
            DB::table('location_products')->where('product_id',$fixtureId)->delete();
            DB::table('products')->where('id',$fixtureId)->delete();
            DB::table('users')->where('id',$fixtureId)->delete();
            DB::table('categories')->where('id',$fixtureId)->delete();
            DB::table('locations')->where('id',$fixtureId)->delete();
        });
        echo "COMMITTED QA FIXTURES: REMOVED\n";
    }
}
foreach ($fixtureTables as $table) {
    if (DB::table($table)->count() !== 0) {
        throw new RuntimeException("QA cleanup failed: {$table}");
    }
}
echo "QA DATA UNCHANGED: YES\n";
if (! $success) throw new RuntimeException('QA concurrency test failed');
echo "=== MYSQL FEFO CONCURRENCY QA COMPLETE ===\n";

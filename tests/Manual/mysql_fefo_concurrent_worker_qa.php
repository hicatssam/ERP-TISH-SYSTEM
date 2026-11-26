<?php
/**
 * Child process for mysql_fefo_concurrency_qa.php.
 * Invoked only by the guarded parent (not a standalone test).
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Inventory\SaleBatchAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
if (DB::getDriverName() !== 'mysql'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'Tish-Data'
    || DB::selectOne('SELECT CURRENT_USER() AS u')->u !== 'tishqa@%'
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'Tish-Data'
    || getenv('DB_USERNAME') !== 'tishqa'
    || is_file($root.'/.env')
    || is_file($root.'/bootstrap/cache/config.php')
    || getenv('TISH_MYSQL_QA_CHILD') !== '1') {
    throw new RuntimeException('MYSQL QA WORKER SAFETY FAILED');
}

if ((int) DB::table('products')->where('id', 999902)
    ->where('sku', 'QA-FEFO-CONCURRENT')->count() !== 1) {
    throw new RuntimeException('MYSQL QA WORKER FIXTURE MISSING');
}

DB::statement('SET SESSION innodb_lock_wait_timeout = 8');
echo "WORKER READY\n";
flush();

$start = microtime(true);
try {
    app(SaleBatchAllocationService::class)->consume(
        locationId: 999902,
        productId: 999902,
        quantity: 2,
        userId: 999902,
        referenceType: 'mysql_qa_concurrency',
        referenceId: 999902,
        idempotencyKey: 'mysql-qa-concurrency-second-999902'
    );

    echo "WORKER INVALID SALE: ALLOWED\n";
    exit(5);
} catch (ValidationException $e) {
    $elapsed = microtime(true) - $start;
    echo "WORKER FEFO SHORTAGE: BLOCKED\n";
    echo "WORKER WAIT_SECONDS: ".number_format($elapsed, 3, '.', '')."\n";
}

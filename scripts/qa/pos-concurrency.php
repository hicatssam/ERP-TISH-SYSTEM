<?php

/**
 * POS cross-process MySQL race regression.
 *
 * Runs ONLY against an isolated MySQL testing database whose name ends in
 * "_test". Spawns two PHP CLI processes with the same checkout token and
 * inspects the committed order, payment, invoice and stock. It does not make
 * any external payment calls.
 *
 * php scripts/qa/pos-concurrency.php
 */
declare(strict_types=1);

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = (string) config('database.connections.mysql.database');
if (config('database.default') !== 'mysql'
    || ! str_ends_with($db, '_test')
    || ! app()->environment('testing')) {
    fwrite(STDERR, "REFUSED: requires APP_ENV=testing and MySQL database *_test.\n");
    exit(3);
}

function scenarioPayload(array $v, string $requestToken): array
{
    return [
        'location_id' => (int) $v['location'],
        'service_type' => 'takeaway',
        'payment_arrangement' => 'pay_now',
        'payment_method_id' => (int) $v['method'],
        'reference_number' => 'QA-MYSQL-RACE-'.(int) $v['product'],
        'payment_received_confirmed' => true,
        'sales_channel_id' => (int) $v['channel'],
        'items' => [['product_id' => (int) $v['product'], 'quantity' => 1]],
        'pos_request_token' => $requestToken,
    ];
}

if (($argv[1] ?? null) === '--worker') {
    $args = json_decode(base64_decode((string) ($argv[2] ?? '')), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::query()->findOrFail((int) $args['user']);
    $payload = scenarioPayload($args, (string) $args['token']);
    while (microtime(true) < (float) $args['ready_at']) {
        usleep(1000);
    }
    try {
        $order = app(RestaurantOrderService::class)->createPosOrder($payload, $actor);
        echo json_encode(['status' => 'success', 'order_id' => $order->id], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo json_encode(['status' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
        exit(1);
    }
}

function fixture(int $stock): array
{
    $suffix = strtoupper(Str::random(10));
    $location = Location::query()->create([
        'name' => 'POS race test', 'code' => 'RACE-'.$suffix,
        'type' => 'branch', 'is_active' => true,
    ]);
    $category = Category::query()->create([
        'name' => 'POS race category '.$suffix,
        'name_ar' => 'اختبار التزامن', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id, 'name' => 'POS-RACE-BURGER',
        'sku' => 'POS-RACE-'.$suffix, 'barcode' => 'RACE-BC-'.$suffix,
        'base_selling_price' => 10, 'is_active' => true,
    ]);
    RestaurantMenuItem::query()->create([
        'location_id' => $location->id, 'product_id' => $product->id,
        'is_active' => true, 'show_in_pos' => true,
        'inventory_mode' => 'product',
    ]);
    LocationProduct::query()->create([
        'location_id' => $location->id, 'product_id' => $product->id,
        'is_available' => true,
    ]);
    Inventory::query()->create([
        'location_id' => $location->id, 'product_id' => $product->id,
        'quantity' => $stock, 'reserved_quantity' => 0, 'unit_cost' => 4,
    ]);
    $channel = SalesChannel::query()->create([
        'name' => 'QA-Counter', 'slug' => 'qa-race-'.strtolower($suffix),
        'type' => 'direct', 'is_active' => true,
    ]);
    $method = PaymentMethod::query()->create([
        'name' => 'QA-Wallet', 'name_ar' => 'محفظة اختبار',
        'code' => 'QA-RACE-'.$suffix,
        'type' => 'electronic_wallet', 'is_active' => true,
        'requires_verification' => false,
    ]);
    $actor = User::factory()->create();

    return [
        'location' => $location->id,
        'product' => $product->id,
        'channel' => $channel->id,
        'method' => $method->id,
        'user' => $actor->id,
    ];
}

function runTwoWorkers(array $vars, string $firstToken, string $secondToken): array
{
    $start = microtime(true) + 2.0;
    $processes = [];
    foreach ([$firstToken, $secondToken] as $token) {
        $arguments = base64_encode(json_encode(
            [...$vars, 'token' => $token, 'ready_at' => $start],
            JSON_THROW_ON_ERROR
        ));
        $process = new Process([PHP_BINARY, __FILE__, '--worker', $arguments]);
        $process->setTimeout(80);
        $process->start();
        $processes[] = $process;
    }
    $responses = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Parallel POS process failed: '.$process->getExitCode().' '.$process->getErrorOutput()
            );
        }
        $responses[] = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    }
    return $responses;
}

// Case 1: the same checkout arrives simultaneously from two PHP workers.
// Exactly ONE order, payment, invoice and inventory deduction may commit.
$a = fixture(5);
$token = (string) Str::uuid();
$duplicate = runTwoWorkers($a, $token, $token);
$orders = Order::query()->where('public_request_token', $token)->get();
if ($orders->count() !== 1
    || collect($duplicate)->pluck('status')->unique()->all() !== [0 => 'success']
    || (int) $duplicate[0]['order_id'] !== (int) $duplicate[1]['order_id']) {
    throw new RuntimeException('Duplicate concurrent checkout did not converge on one order: '.json_encode($duplicate));
}
$order = $orders->sole();
if ($order->payments()->count() !== 1
    || $order->invoice()->count() !== 1
    || (float) Inventory::query()->where('location_id', $a['location'])->where('product_id', $a['product'])->sole()->quantity !== 4.0) {
    throw new RuntimeException('Duplicate checkout changed payment, invoice or inventory more than once.');
}
echo "PASS POS-RACE-01: two simultaneous requests, one order/payment/invoice/stock movement\n";

// Case 2: two *different* legitimate checkouts fight for one remaining item.
// Exactly one succeeds, and the failed request must roll back entirely.
$b = fixture(1);
$first = (string) Str::uuid();
$second = (string) Str::uuid();
$contenders = runTwoWorkers($b, $first, $second);
$statuses = collect($contenders)->pluck('status')->sort()->values()->all();
if ($statuses !== ['rejected', 'success']) {
    throw new RuntimeException('Last-unit race did not resolve to one sold and one rejected: '.json_encode($contenders));
}
$locationOrders = Order::query()->where('location_id', $b['location'])->get();
if ($locationOrders->count() !== 1
    || $locationOrders->sole()->payments()->count() !== 1
    || $locationOrders->sole()->invoice()->count() !== 1
    || (float) Inventory::query()->where('location_id', $b['location'])->where('product_id', $b['product'])->sole()->quantity !== 0.0) {
    throw new RuntimeException('Last-unit race left duplicate orders, invalid payment or corrupted stock.');
}
echo "PASS POS-RACE-02: two competing cashiers, one last unit, no oversell\n";

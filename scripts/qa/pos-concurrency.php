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
use App\Models\Module;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Services\Kitchen\KitchenTicketService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Cache;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's global CLI exception renderer can print an error while preserving
// a zero shell exit code. Make race failures fail GitHub Actions explicitly.
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
    exit(1);
});

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
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
        exit(1);
    }
}

if (($argv[1] ?? null) === '--worker-kds') {
    $args = json_decode(base64_decode((string) ($argv[2] ?? '')), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::query()->findOrFail((int) $args['user']);
    while (microtime(true) < (float) $args['ready_at']) {
        usleep(1000);
    }
    try {
        if ($args['kind'] === 'kitchen') {
            $ticket = KitchenTicket::query()->findOrFail((int) $args['ticket_id']);
            $transition = (string) ($args['transition'] ?? 'start');
            if (! in_array($transition, ['start', 'markReady', 'serve'], true)) {
                throw new RuntimeException('Unsupported kitchen transition in QA worker.');
            }
            app(KitchenTicketService::class)->{$transition}($ticket, $actor);
            echo json_encode(['status' => 'success', 'action' => 'kitchen_started'], JSON_THROW_ON_ERROR)."\n";
        } else {
            $order = Order::query()->findOrFail((int) $args['order_id']);
            app(OrderService::class)->updateOrder($order, [
                'payment_arrangement' => 'pay_now',
                'items' => [['id' => (int) $args['item_id'], 'quantity' => 2]],
            ], $actor);
            echo json_encode(['status' => 'success', 'action' => 'cashier_changed_quantity'], JSON_THROW_ON_ERROR)."\n";
        }
        exit(0);
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo json_encode(['status' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
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
        'name' => 'QA-Counter-'.$suffix, 'slug' => 'qa-race-'.strtolower($suffix),
        'type' => 'direct', 'is_active' => true,
    ]);
    $method = PaymentMethod::query()->create([
        'name' => 'QA-Wallet-'.$suffix, 'name_ar' => 'محفظة اختبار',
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


// Case 3: kitchen status changes exactly while the cashier tries to edit.
// Both workers use independent MySQL connections. The kitchen starts, the
// cashier is rejected, and payment/invoice/stock remain unchanged.
$c = fixture(5);
Module::query()->firstOrCreate(['code' => 'kitchen'], [
    'name' => 'Kitchen', 'type' => 'industry', 'is_active' => true,
]);
Cache::forget('modules:registry:v1');
KitchenStation::query()->create([
    'location_id' => $c['location'], 'name' => 'QA kitchen',
    'code' => 'QA-MAIN-'.Str::random(6), 'is_default' => true, 'is_active' => true,
]);
$actor = User::query()->findOrFail((int) $c['user']);
$kdsOrder = app(RestaurantOrderService::class)->createPosOrder(
    scenarioPayload($c, (string) Str::uuid()), $actor
);
app(KitchenTicketService::class)->dispatchConfirmedOrder($kdsOrder, $actor);
$ticket = $kdsOrder->kitchenTickets()->sole();
$item = $kdsOrder->items()->sole();
$startAt = microtime(true) + 2.0;
$kdsTasks = [
    ['kind' => 'kitchen'],
    ['kind' => 'cashier'],
];
$workers = [];
foreach ($kdsTasks as $task) {
    $args = base64_encode(json_encode([
        ...$c, ...$task, 'order_id' => $kdsOrder->id,
        'ticket_id' => $ticket->id, 'item_id' => $item->id,
        'ready_at' => $startAt,
    ], JSON_THROW_ON_ERROR));
    $worker = new Process([PHP_BINARY, __FILE__, '--worker-kds', $args]);
    $worker->setTimeout(80);
    $worker->start();
    $workers[] = $worker;
}
$results = [];
foreach ($workers as $worker) {
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('KDS/checkout edit race failed: '.$worker->getExitCode().' '.$worker->getErrorOutput());
    }
    $results[] = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
}
if (($results[0]['status'] ?? null) !== 'success'
    || ($results[1]['status'] ?? null) !== 'rejected'
    || $ticket->fresh()->statusValue() !== 'preparing'
    || (float) $item->fresh()->quantity !== 1.0
    || (float) $kdsOrder->fresh()->total_amount !== 10.0
    || (float) $kdsOrder->fresh()->invoice?->total_amount !== 10.0
    || $kdsOrder->payments()->count() !== 1
    || (float) Inventory::query()->where('location_id', $c['location'])
        ->where('product_id', $c['product'])->sole()->quantity !== 4.0) {
    throw new RuntimeException('KDS-vs-POS race caused inconsistent state: '.json_encode($results));
}
echo "PASS POS-RACE-03: kitchen starts while cashier edits; edit denied and financial state preserved\n";

// Case 4: ten real independent processes compete for sufficient stock.
// Every unique checkout must survive; no lost charges or duplicate invoices.
$d = fixture(25);
$launch = microtime(true) + 2.0;
$parallelWorkers = [];
$parallelTokens = [];
for ($i = 0; $i < 10; $i++) {
    $token = (string) Str::uuid();
    $parallelTokens[] = $token;
    $args = base64_encode(json_encode([
        ...$d, 'token' => $token, 'ready_at' => $launch,
    ], JSON_THROW_ON_ERROR));
    $worker = new Process([PHP_BINARY, __FILE__, '--worker', $args]);
    $worker->setTimeout(100);
    $worker->start();
    $parallelWorkers[] = $worker;
}
$parallelResults = [];
foreach ($parallelWorkers as $worker) {
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Ten-client load failed: '.$worker->getExitCode().' '.$worker->getErrorOutput());
    }
    $parallelResults[] = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
}
$orders = Order::query()->where('location_id', $d['location'])->get();
if (count($parallelResults) !== 10
    || count(array_filter($parallelResults, static fn($r) => ($r['status'] ?? '') === 'success')) !== 10
    || $orders->count() !== 10
    || $orders->pluck('public_request_token')->unique()->count() !== 10
    || (int) $orders->sum(fn($order) => $order->payments()->count()) !== 10
    || (int) $orders->sum(fn($order) => $order->invoice()->count()) !== 10
    || (float) Inventory::query()->where('location_id', $d['location'])
        ->where('product_id', $d['product'])->sole()->quantity !== 15.0) {
    throw new RuntimeException('Ten-client parallel totals or inventory inconsistent: '.json_encode($parallelResults));
}
echo "PASS POS-RACE-04: ten simultaneous POS checkouts, ten invoices and payments, exact stock\n";



// Cases 5-6: kitchen marks ready / serves at the exact moment cashier attempts
// an invalid post-dispatch quantity change. Independent MySQL connections.
foreach ([
    ['transition' => 'markReady', 'expected' => 'ready', 'label' => 'POS-RACE-05'],
    ['transition' => 'serve', 'expected' => 'served', 'label' => 'POS-RACE-06'],
] as $stage) {
    $launch = microtime(true) + 2.0;
    $workers = [];
    foreach ([
        ['kind' => 'kitchen', 'transition' => $stage['transition']],
        ['kind' => 'cashier'],
    ] as $task) {
        $args = base64_encode(json_encode([
            ...$c, ...$task, 'order_id' => $kdsOrder->id,
            'ticket_id' => $ticket->id, 'item_id' => $item->id,
            'ready_at' => $launch,
        ], JSON_THROW_ON_ERROR));
        $worker = new Process([PHP_BINARY, __FILE__, '--worker-kds', $args]);
        $worker->setTimeout(80);
        $worker->start();
        $workers[] = $worker;
    }
    $results = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException($stage['label'].' worker error: '.$worker->getErrorOutput());
        }
        $results[] = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    }
    if (($results[0]['status'] ?? '') !== 'success'
        || ($results[1]['status'] ?? '') !== 'rejected'
        || $ticket->fresh()->statusValue() !== $stage['expected']
        || (float) $item->fresh()->quantity !== 1.0
        || $kdsOrder->payments()->count() !== 1
        || (float) $kdsOrder->fresh()->invoice?->total_amount !== 10.0
        || (float) Inventory::query()->where('location_id', $c['location'])
            ->where('product_id', $c['product'])->sole()->quantity !== 4.0) {
        throw new RuntimeException($stage['label'].' invalid financial/KDS state: '.json_encode($results));
    }
    echo 'PASS '.$stage['label'].': concurrent '.$stage['transition'].' vs cashier edit, immutable stock/financial records'."\n";
}

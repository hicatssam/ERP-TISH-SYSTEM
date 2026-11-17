<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TishPushSubscription;
use App\Services\Restaurant\CustomerOrderStatusService;
use App\Services\Restaurant\TishWebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TishPushSubscriptionController extends Controller
{
    public function key(TishWebPushSender $sender): JsonResponse
    {
        return response()->json([
            'enabled' => $sender->available(),
            'public_key' => $sender->available() ? config('services.tish_push.public_key') : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, string $token, TishWebPushSender $sender, CustomerOrderStatusService $status): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:300'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        if (! $sender->available() || ! $sender::allowedEndpoint($data['endpoint'])) {
            return response()->json(['message' => 'Push is not configured or unsupported'], 422);
        }
        $order = Order::query()->where('order_source', 'customer_menu')
            ->where('public_token', $token)->firstOrFail();
        $current = $status->payload($order)['state'];
        $hash = hash('sha256', $data['endpoint']);
        // Bound to the actual token-scoped order, never a client-provided order_id.
        // One browser/device may subscribe to multiple independent orders.
        TishPushSubscription::updateOrCreate(
            ['order_id' => $order->id, 'endpoint_hash' => $hash],
            ['order_id' => $order->id, 'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'], 'auth_secret' => $data['keys']['auth'],
                'last_state' => $current]
        );
        return response()->json(['ok' => true], 201)->header('Cache-Control', 'no-store');
    }

    public function delete(Request $request, string $token): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $order = Order::query()->where('order_source', 'customer_menu')
            ->where('public_token', $token)->firstOrFail();
        TishPushSubscription::query()->where('order_id', $order->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Services\Restaurant;

use App\Models\Order;
use App\Models\TishPushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TishOrderPushDispatcher
{
    public function __construct(
        private readonly CustomerOrderStatusService $states,
        private readonly TishWebPushSender $sender
    ) {
    }

    public function sendForOrder(int $orderId): void
    {
        if (! $this->sender->available()) return;
        // A queued event and the minute-scheduler fallback may run concurrently.
        // An order-scoped cache lock prevents duplicate send attempts.
        Cache::lock('tish-push-order-' . $orderId, 180)->get(function () use ($orderId): void {
            $order = Order::query()->find($orderId);
            if (! $order || $order->order_source !== 'customer_menu') {
                TishPushSubscription::query()->where('order_id', $orderId)->delete();
                return;
            }
            $subscriptions = TishPushSubscription::query()->where('order_id', $orderId)->get();
            if ($subscriptions->isEmpty()) return;
            $state = $this->states->payload($order);
            foreach ($subscriptions as $subscription) {
                if ($subscription->last_state === $state['state']) continue;
                try {
                    $code = $this->sender->deliver(
                        $subscription->endpoint, $subscription->p256dh, $subscription->auth_secret,
                        [
                            'title' => $state['label'],
                            'body' => $state['description'],
                            'url' => $state['track_url'],
                            'tag' => 'tish-order-' . $order->id,
                            'state' => $state['state'],
                        ]
                    );
                    if (in_array($code, [200, 201, 202], true)) {
                        $subscription->update(['last_state' => $state['state']]);
                    } elseif (in_array($code, [404, 410], true)) {
                        $subscription->delete();
                    } else {
                        Log::warning('TISH Push provider rejected request', [
                            'http_status' => $code, 'subscription_id' => $subscription->id,
                        ]);
                    }
                } catch (\Throwable $error) {
                    Log::warning('TISH Push delivery failed', [
                        'subscription_id' => $subscription->id, 'message' => $error->getMessage(),
                    ]);
                }
            }
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Models\TishPushSubscription;
use App\Services\Restaurant\CustomerOrderStatusService;
use App\Services\Restaurant\TishWebPushSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchTishOrderPush extends Command
{
    protected $signature = 'tish:dispatch-push';
    protected $description = 'Dispatch customer order state transitions to opted-in Web Push devices';

    public function handle(CustomerOrderStatusService $states, TishWebPushSender $sender): int
    {
        if (! $sender->available()) {
            $this->warn('TISH VAPID not configured; no push dispatched.');
            return self::SUCCESS;
        }
        TishPushSubscription::query()->with('order')->orderBy('id')->chunkById(100, function ($subscriptions) use ($states, $sender): void {
            foreach ($subscriptions as $subscription) {
                $order = $subscription->order;
                if (! $order || $order->order_source !== 'customer_menu') {
                    $subscription->delete();
                    continue;
                }
                try {
                    $state = $states->payload($order);
                    if ($state['state'] === $subscription->last_state) continue;
                    $code = $sender->deliver(
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
                        Log::warning('TISH Push provider rejected request', ['http_status' => $code, 'subscription_id' => $subscription->id]);
                    }
                } catch (\Throwable $error) {
                    Log::warning('TISH Push dispatch failed', [
                        'subscription_id' => $subscription->id, 'message' => $error->getMessage(),
                    ]);
                }
            }
        });
        return self::SUCCESS;
    }
}

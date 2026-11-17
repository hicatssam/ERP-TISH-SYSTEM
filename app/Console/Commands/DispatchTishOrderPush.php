<?php

namespace App\Console\Commands;

use App\Models\TishPushSubscription;
use App\Services\Restaurant\TishOrderPushDispatcher;
use Illuminate\Console\Command;

class DispatchTishOrderPush extends Command
{
    protected $signature = 'tish:dispatch-push';
    protected $description = 'Check order state and dispatch missing customer Web Push notifications';

    public function handle(TishOrderPushDispatcher $dispatcher): int
    {
        // Fallback when a kitchen event or queue worker was unavailable.
        $lastId = 0;
        while (true) {
            $ids = TishPushSubscription::query()
                ->where('order_id', '>', $lastId)
                ->distinct()
                ->orderBy('order_id')
                ->limit(100)
                ->pluck('order_id');

            if ($ids->isEmpty()) break;
            foreach ($ids as $id) {
                $lastId = (int) $id;
                $dispatcher->sendForOrder($lastId);
            }
        }
        return self::SUCCESS;
    }
}

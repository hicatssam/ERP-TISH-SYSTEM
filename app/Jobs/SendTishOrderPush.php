<?php

namespace App\Jobs;

use App\Services\Restaurant\TishOrderPushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTishOrderPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $orderId)
    {
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(TishOrderPushDispatcher $dispatcher): void
    {
        $dispatcher->sendForOrder($this->orderId);
    }
}

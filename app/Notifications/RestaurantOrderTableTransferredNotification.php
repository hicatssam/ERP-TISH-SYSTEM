<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Operational correction only. Does not create or re-send any kitchen ticket.
 */
class RestaurantOrderTableTransferredNotification extends Notification
{
    private readonly string $fingerprint;

    public function __construct(
        private readonly Order $order,
        private readonly string $fromTable,
        private readonly string $toTable
    ) {
        $this->fingerprint = 'restaurant_table_move_' . $order->id . '_' . Str::uuid();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'type' => 'restaurant_order_table_transferred',
            'event' => 'table_transferred',
            'title' => 'تم تصحيح رقم طاولة الطلب',
            'message' => 'الطلب ' . $this->order->order_number . ': '
                . $this->fromTable . ' ← ' . $this->toTable,
            'order_id' => $this->order->id,
            'location_id' => $this->order->location_id,
            'url' => '/kds?location_id=' . $this->order->location_id,
            'priority' => 'high',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\BranchShipmentTrip;
use Illuminate\Notifications\Notification;

class BranchTripActivityNotification extends Notification
{
    public function __construct(
        private readonly BranchShipmentTrip $trip,
        private readonly string $event,
        private readonly bool $completed = false
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        $trip = $this->trip->loadMissing(['shipment.source', 'shipment.toLocation', 'driver']);
        $request = $trip->shipment->source;
        $number = $request->request_number ?? $request->order_number ?? $trip->shipment->id;

        return [
            'fingerprint' => "branch_trip_{$this->event}_{$trip->id}",
            'type' => $this->event === 'loaded' ? 'branch_trip_loaded' : 'branch_trip_received',
            'title' => $this->event === 'loaded'
                ? "تم تحميل النقلة #{$trip->trip_number}"
                : ($this->completed ? 'اكتملت الكميات وصدرت فاتورة الاستلام' : "تم استلام النقلة #{$trip->trip_number} جزئيًا"),
            'message' => "طلب {$number} — النقلة {$trip->trip_number} — {$trip->shipment->toLocation?->name}. "
                .($this->event === 'loaded' ? 'الموصّل: '.($trip->driver?->display_name ?? '—') : 'تم تدقيق الكميات المستلمة.'),
            'branch_shipment_id' => $trip->branch_shipment_id,
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'completed' => $this->completed,
            'url' => route('branch-shipments.show', $trip->shipment, false),
        ];
    }
}

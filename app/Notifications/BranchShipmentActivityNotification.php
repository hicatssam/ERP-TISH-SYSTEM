<?php

namespace App\Notifications;

use App\Models\BranchShipment;
use Illuminate\Notifications\Notification;

class BranchShipmentActivityNotification extends Notification
{
    public function __construct(
        private readonly BranchShipment $shipment,
        private readonly string $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $shipment = $this->shipment;
        $shipment->loadMissing([
            'source', 'fromLocation', 'toLocation',
            'driver.employee', 'receivingInvoice',
        ]);

        $source = $shipment->source;
        $orderNumber = match (true) {
            $source instanceof \App\Models\SpecialCakeOrder => $source->order_number,
            $source instanceof \App\Models\ShowroomCakeRequest,
            $source instanceof \App\Models\ShowroomSweetsRequest => $source->request_number,
            default => (string) $shipment->source_id,
        };

        $sourceLabel = match (true) {
            $source instanceof \App\Models\SpecialCakeOrder => 'كيك خاص',
            $source instanceof \App\Models\ShowroomCakeRequest => 'كيك الفروع',
            $source instanceof \App\Models\ShowroomSweetsRequest => 'حلويات الفروع',
            default => 'طلب',
        };

        $invoice = $shipment->receivingInvoice;
        $difference = round((float) ($invoice?->total_difference ?? 0), 3);
        $isReceipt = $this->event === 'received';

        $message = $isReceipt
            ? "تم استلام طلب {$sourceLabel} رقم {$orderNumber} في {$shipment->toLocation?->name}. "
                . "فاتورة {$invoice?->invoice_number} — المرسل: "
                . number_format((float) ($invoice?->total_items_sent ?? 0), 3)
                . '، المستلم: '
                . number_format((float) ($invoice?->total_items_received ?? 0), 3)
                . '، الفرق: '
                . number_format($difference, 3)
                . ($difference > 0 ? ' (يرجى مراجعة النقص).' : '.')
            : "تم تحميل طلب {$sourceLabel} رقم {$orderNumber} من {$shipment->fromLocation?->name} "
                . "إلى {$shipment->toLocation?->name}. "
                . "الموصّل: {$shipment->driver?->display_name}.";

        return [
            'fingerprint' => "branch_shipment_{$this->event}_{$shipment->id}",
            'type' => $isReceipt
                ? 'branch_receiving_invoice_confirmed'
                : 'branch_shipment_loaded',
            'title' => $isReceipt
                ? ($difference > 0 ? 'استلام طلبية مع فروقات في الكميات' : 'تم استلام الطلبية وإصدار الفاتورة')
                : 'تم تحميل الطلبية للفرع',
            'message' => $message,
            'branch_shipment_id' => $shipment->id,
            'invoice_id' => $isReceipt ? $invoice?->id : null,
            'invoice_number' => $isReceipt ? $invoice?->invoice_number : null,
            'source_type' => $shipment->source_type,
            'source_id' => $shipment->source_id,
            'from_location_id' => $shipment->from_location_id,
            'to_location_id' => $shipment->to_location_id,
            'driver_id' => $shipment->driver_id,
            'sent_quantity' => $isReceipt ? (float) $invoice->total_items_sent : null,
            'received_quantity' => $isReceipt ? (float) $invoice->total_items_received : null,
            'difference_quantity' => $isReceipt ? $difference : null,
            'url' => $isReceipt && $invoice
                ? route('branch-receiving-invoices.show', $invoice, false)
                : route('branch-shipments.show', $shipment, false),
            'priority' => $difference > 0 ? 'high' : 'medium',
        ];
    }
}

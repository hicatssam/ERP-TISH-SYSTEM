<?php

namespace App\Services\Delivery;

use App\Models\BranchReceivingInvoice;
use App\Models\BranchShipment;
use App\Models\BranchShipmentTrip;
use App\Models\User;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Services\Notifications\ShowroomCakeRequestNotifier;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use App\Services\ActivityLogger;
use App\Services\Notifications\BranchShipmentNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchDeliveryTripService
{
    public function __construct(private readonly BranchDeliveryService $delivery) {}

    /** @return array{lines:array<int,array<string,mixed>>,required:float,received:float,remaining:float,pending:float,complete:bool} */
    public function progress(BranchShipment $shipment): array
    {
        $shipment->load(['receivingInvoice.items', 'trips.items']);
        $lines = [];
        $totalRequired = $totalReceived = $totalPending = 0.0;

        foreach ($shipment->receivingInvoice->items as $item) {
            $needed = (float) $item->sent_quantity;
            $received = 0.0;
            $pending = 0.0;

            foreach ($shipment->trips as $trip) {
                foreach ($trip->items as $tripItem) {
                    if ((int) $tripItem->branch_receiving_invoice_item_id !== (int) $item->id) {
                        continue;
                    }
                    if ($trip->status === 'received') {
                        $received += (float) $tripItem->received_quantity;
                    } else {
                        $pending += (float) $tripItem->loaded_quantity;
                    }
                }
            }

            $received = round($received, 3);
            $pending = round($pending, 3);
            $remaining = max(0, round($needed - $received - $pending, 3));
            $lines[$item->id] = [
                'item' => $item,
                'required' => $needed,
                'received' => $received,
                'pending' => $pending,
                'available' => $remaining,
                'outstanding' => max(0, round($needed - $received, 3)),
            ];
            $totalRequired += $needed;
            $totalReceived += $received;
            $totalPending += $pending;
        }

        return [
            'lines' => $lines,
            'required' => round($totalRequired, 3),
            'received' => round($totalReceived, 3),
            'pending' => round($totalPending, 3),
            'remaining' => max(0, round($totalRequired - $totalReceived, 3)),
            'complete' => count($lines) > 0
                && collect($lines)->every(fn ($line) => $line['outstanding'] < 0.0005),
        ];
    }


    /**
     * Dispatcher closes an irrecoverably short branch shipment after all trips
     * have been received; only physically received quantities are invoiced.
     * Closing is independent from successful/full completion.
     */
    public function closeShort(BranchShipment $shipment, User $actor, string $reason): BranchReceivingInvoice
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'closure_reason' => 'يجب توضيح سبب الإغلاق في 10 إلى 2000 حرف.',
            ]);
        }

        $outcome = DB::transaction(function () use ($shipment, $actor, $reason): array {
            $locked = BranchShipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (! $locked->multi_trip_enabled ||
                ! in_array($locked->status, [BranchShipment::PENDING_LOADING, BranchShipment::LOADED], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'هذه الطلبية مغلقة أو غير متاحة للإغلاق الناقص.',
                ]);
            }

            $source = $locked->source;
            if (! ($source instanceof ShowroomCakeRequest || $source instanceof ShowroomSweetsRequest)
                || $source->status->workflowValue() !== 'ready') {
                throw ValidationException::withMessages([
                    'shipment' => 'يمكن إغلاق طلبات حلويات وكيك الفروع الجاهزة فقط.',
                ]);
            }

            $invoice = $locked->receivingInvoice;
            if (! $invoice || $invoice->isConfirmed()) {
                throw ValidationException::withMessages([
                    'invoice' => 'تم اعتماد فاتورة هذه الطلبية مسبقًا.',
                ]);
            }

            $progress = $this->progress($locked);
            if ($progress['pending'] > 0.0005) {
                throw ValidationException::withMessages([
                    'shipment' => 'لا يمكن الإغلاق قبل تأكيد استلام جميع النقلات المفتوحة.',
                ]);
            }
            if ($progress['received'] < 0.0005) {
                throw ValidationException::withMessages([
                    'shipment' => 'يجب أن يستلم الفرع كمية فعلية واحدة على الأقل قبل الإغلاق الناقص.',
                ]);
            }
            if ($progress['remaining'] < 0.0005 || $progress['complete']) {
                throw ValidationException::withMessages([
                    'shipment' => 'كل الكميات مستلمة؛ لا يجوز تسجيل إغلاق ناقص.',
                ]);
            }

            foreach ($progress['lines'] as $line) {
                $line['item']->update([
                    'received_quantity' => $line['received'],
                    'difference_quantity' => $line['outstanding'],
                ]);
            }

            $lastReceipt = $locked->trips()->where('status', 'received')
                ->orderByDesc('received_at')->orderByDesc('id')->firstOrFail();

            $invoice->update([
                'invoice_number' => $invoice->invoice_number ?:
                    'BRI-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
                'status' => 'confirmed',
                'total_items_received' => $progress['received'],
                'total_difference' => $progress['remaining'],
                'driver_id' => $lastReceipt->driver_id,
                'received_by' => $lastReceipt->received_by,
                'issued_at' => now(),
                'notes' => 'أُغلقت بنقص بواسطة الـDispatcher. السبب: '.$reason,
            ]);

            $locked->update([
                'status' => BranchShipment::CLOSED_SHORT,
                'closure_reason' => $reason,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                // Preserve the actual receiving user and receipt timestamp.
                'received_by' => $lastReceipt->received_by,
                'received_at' => $lastReceipt->received_at,
            ]);

            $oldStatus = $source->status->workflowValue();
            if ($source instanceof ShowroomCakeRequest) {
                $source->update([
                    'status' => ShowroomCakeRequestStatus::ClosedShort,
                    'fulfilled_at' => now(),
                    'handled_by' => $actor->id,
                ]);
            } else {
                $source->update([
                    'status' => ShowroomSweetsRequestStatus::ClosedShort,
                    'received_by' => $lastReceipt->received_by,
                    'received_at' => $lastReceipt->received_at,
                    'fulfilled_at' => now(),
                ]);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'branch_shipment.closed_short',
                module: 'delivery',
                recordType: 'branch_shipments',
                recordId: $locked->id,
                oldValues: ['status' => $shipment->status],
                newValues: [
                    'status' => BranchShipment::CLOSED_SHORT,
                    'closure_reason' => $reason,
                    'received_quantity' => $progress['received'],
                    'missing_quantity' => $progress['remaining'],
                    'invoice_number' => $invoice->invoice_number,
                ],
                metadata: [
                    'source_type' => $locked->source_type,
                    'source_id' => $locked->source_id,
                    'branch_id' => $locked->to_location_id,
                ],
            );

            return ['invoice' => $invoice, 'shipment' => $locked,
                'source' => $source, 'old_status' => $oldStatus];
        });

        $source = $outcome['source']->fresh();
        if ($source instanceof ShowroomCakeRequest) {
            ShowroomCakeRequestNotifier::statusChanged(
                $source, $outcome['old_status'], ShowroomCakeRequestStatus::ClosedShort->value, $actor
            );
        } else {
            ShowroomSweetsRequestNotifier::statusChanged(
                $source, $outcome['old_status'], ShowroomSweetsRequestStatus::ClosedShort->value, $actor
            );
        }
        \App\Services\Notifications\BranchShipmentNotifier::closedShort(
            $outcome['shipment']->fresh(['source', 'receivingInvoice']), $actor
        );

        return $outcome['invoice']->fresh(['items', 'shipment.source']);
    }

    /**
     * @param array<int|string,array{quantity:float|int|string}> $items
     */
    public function loadTrip(BranchShipment $shipment, User $actor, User $driver, array $items, ?string $notes): BranchShipmentTrip
    {
        $trip = DB::transaction(function () use ($shipment, $actor, $driver, $items, $notes) {
            $shipment = BranchShipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (! $shipment->multi_trip_enabled
                || ! in_array($shipment->status, [BranchShipment::PENDING_LOADING, BranchShipment::LOADED], true)) {
                throw ValidationException::withMessages(['shipment' => 'الطلبية ليست مفتوحة لإضافة نقلة جديدة.']);
            }

            $invoice = $shipment->receivingInvoice;
            if (! $invoice || $invoice->isConfirmed()) {
                throw ValidationException::withMessages(['invoice' => 'فاتورة الاستلام مكتملة بالفعل.']);
            }

            $progress = $this->progress($shipment);
            $positive = false;
            $rows = [];
            $validIds = array_keys($progress['lines']);
            foreach (array_keys($items) as $itemId) {
                if (! in_array((int) $itemId, $validIds, true)) {
                    throw ValidationException::withMessages(['items' => 'أحد بنود التحميل لا ينتمي لهذه الطلبية.']);
                }
            }
            foreach ($progress['lines'] as $itemId => $line) {
                $quantity = round((float) ($items[$itemId]['quantity'] ?? 0), 3);
                if ($quantity < 0 || $quantity > $line['available'] + 0.0005) {
                    throw ValidationException::withMessages([
                        "items.{$itemId}.quantity" => 'التحميل يتجاوز الكمية المتبقية بعد احتساب النقلات السابقة.',
                    ]);
                }
                if ($quantity > 0) {
                    $rows[$itemId] = $quantity;
                    $positive = true;
                }
            }
            if (! $positive) {
                throw ValidationException::withMessages(['items' => 'أدخل كمية موجبة لصنف واحد على الأقل.']);
            }

            $sequence = (int) $shipment->trips()->max('trip_number') + 1;
            $trip = $shipment->trips()->create([
                'trip_number' => $sequence,
                'driver_id' => $driver->id,
                'loaded_by' => $actor->id,
                'loaded_at' => now(),
                'status' => 'loaded',
                'notes' => $notes,
            ]);
            foreach ($rows as $itemId => $quantity) {
                $trip->items()->create([
                    'branch_receiving_invoice_item_id' => $itemId,
                    'loaded_quantity' => $quantity,
                ]);
            }

            $shipment->update([
                'status' => BranchShipment::LOADED,
                'driver_id' => $driver->id,
                'loaded_by' => $actor->id,
                'loaded_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'branch_shipment.trip_loaded',
                module: 'delivery',
                recordType: 'branch_shipment_trips',
                recordId: $trip->id,
                oldValues: null,
                newValues: ['trip_number' => $sequence, 'items' => $rows],
                metadata: ['shipment_id' => $shipment->id, 'driver_id' => $driver->id],
            );

            return $trip;
        });

        BranchTripNotifier::loaded($trip, $actor);
        return $trip->fresh(['items.invoiceItem', 'driver.employee']);
    }

    /**
     * @param array<int|string,array{received_quantity:float|int|string,note?:string|null}> $items
     * @return array{trip:BranchShipmentTrip,invoice:BranchReceivingInvoice,complete:bool}
     */
    public function receiveTrip(BranchShipmentTrip $trip, User $actor, array $items, ?string $notes): array
    {
        $result = DB::transaction(function () use ($trip, $actor, $items, $notes) {
            // All actions on this shipment take the parent row lock first.
            $shipment = BranchShipment::query()->lockForUpdate()->findOrFail($trip->branch_shipment_id);
            $trip = $shipment->trips()->with('items')->lockForUpdate()->findOrFail($trip->id);

            if (! $shipment->multi_trip_enabled || $trip->status !== 'loaded') {
                throw ValidationException::withMessages(['trip' => 'هذه النقلة مستلمة مسبقًا أو غير متاحة للاستلام.']);
            }

            $validIds = $trip->items->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach (array_keys($items) as $itemId) {
                if (! in_array((int) $itemId, $validIds, true)) {
                    throw ValidationException::withMessages(['items' => 'تم إرسال بند لا يخص النقلة الحالية.']);
                }
            }

            foreach ($trip->items as $item) {
                $row = $items[$item->id] ?? null;
                if (! is_array($row) || ! array_key_exists('received_quantity', $row)) {
                    throw ValidationException::withMessages(['items' => 'يجب تأكيد الكميات لكل أصناف النقلة.']);
                }
                $received = round((float) $row['received_quantity'], 3);
                if ($received < 0 || $received > (float) $item->loaded_quantity + 0.0005) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.received_quantity" => 'الكمية المستلمة لا يجوز أن تتجاوز كمية هذه النقلة.',
                    ]);
                }
                $item->update([
                    'received_quantity' => $received,
                    'note' => isset($row['note']) ? trim((string) $row['note']) : null,
                ]);
            }
            $trip->update([
                'status' => 'received',
                'received_by' => $actor->id,
                'received_at' => now(),
                'receiving_notes' => $notes,
            ]);

            $progress = $this->progress($shipment->fresh());
            $invoice = $shipment->receivingInvoice;
            foreach ($progress['lines'] as $line) {
                $line['item']->update([
                    'received_quantity' => $line['received'],
                    'difference_quantity' => $line['outstanding'],
                ]);
            }

            $complete = $progress['complete'];
            $invoiceValues = [
                'total_items_received' => $progress['received'],
                'total_difference' => $progress['remaining'],
            ];
            if ($complete) {
                $invoiceValues += [
                    'invoice_number' => $invoice->invoice_number
                        ?: 'BRI-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
                    'status' => 'confirmed',
                    'driver_id' => $trip->driver_id,
                    'received_by' => $actor->id,
                    'issued_at' => now(),
                    'notes' => $notes,
                ];
                $shipment->update([
                    'status' => BranchShipment::RECEIVED,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                ]);

                $this->delivery->finalizeSourceAfterFullReceipt($shipment->source, $actor, $invoiceValues['invoice_number']);
            }
            $invoice->update($invoiceValues);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'branch_shipment.trip_received',
                module: 'delivery',
                recordType: 'branch_shipment_trips',
                recordId: $trip->id,
                oldValues: ['status' => 'loaded'],
                newValues: ['status' => 'received', 'invoice_complete' => $complete],
                metadata: ['shipment_id' => $shipment->id, 'received_quantity' => $progress['received']],
            );

            return ['trip' => $trip, 'invoice' => $invoice, 'complete' => $complete];
        });

        BranchTripNotifier::received($result['trip'], $actor, $result['complete']);
        if ($result['complete']) {
            BranchShipmentNotifier::received($result['invoice']->fresh(['shipment.source']), $actor);
        }
        return $result;
    }
}

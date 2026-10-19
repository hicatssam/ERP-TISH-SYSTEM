<?php

namespace App\Services\Delivery;

use App\Enums\CakeOrderStatus;
use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\BranchReceivingInvoice;
use App\Models\BranchShipment;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\ShowroomSweetsRequest;
use App\Models\ShowroomSweetsRequestItem;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifications\BranchShipmentNotifier;
use App\Services\Notifications\ShowroomCakeRequestNotifier;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchDeliveryService
{
    public function __construct(
        private readonly SpecialCakeStatusTransitionService $specialCakeTransitions,
    ) {
    }

    public function resolveSource(string $type, int $id): Model
    {
        return match ($type) {
            'special-cake' => SpecialCakeOrder::query()->with(['originBranch', 'factory'])->findOrFail($id),
            'showroom-cake' => ShowroomCakeRequest::query()->with(['requestingLocation', 'factoryLocation', 'items'])->findOrFail($id),
            'showroom-sweets' => ShowroomSweetsRequest::query()->with(['requestingLocation', 'factoryLocation', 'items.product'])->findOrFail($id),
            default => abort(404),
        };
    }

    public function sourceType(Model $source): string
    {
        return match (true) {
            $source instanceof SpecialCakeOrder => 'special-cake',
            $source instanceof ShowroomCakeRequest => 'showroom-cake',
            $source instanceof ShowroomSweetsRequest => 'showroom-sweets',
            default => throw ValidationException::withMessages([
                'source' => 'نوع الطلب غير مدعوم في التوصيل.',
            ]),
        };
    }

    public function sourceNumber(Model $source): string
    {
        return match (true) {
            $source instanceof SpecialCakeOrder => (string) $source->order_number,
            $source instanceof ShowroomCakeRequest,
            $source instanceof ShowroomSweetsRequest => (string) $source->request_number,
            default => (string) $source->getKey(),
        };
    }

    public function sourceLabel(Model $source): string
    {
        return match (true) {
            $source instanceof SpecialCakeOrder => 'طلب كيك خاص',
            $source instanceof ShowroomCakeRequest => 'طلب كيك فرع',
            $source instanceof ShowroomSweetsRequest => 'طلب حلويات فرع',
            default => 'طلب',
        };
    }

    public function sourceRoute(Model $source): string
    {
        return match (true) {
            $source instanceof SpecialCakeOrder => route('cake-orders.show', $source),
            $source instanceof ShowroomCakeRequest => route('showroom-cake-requests.show', $source),
            $source instanceof ShowroomSweetsRequest => route('showroom-sweets-requests.show', $source),
            default => route('dashboard'),
        };
    }

    public function createShipment(Model $source, User $actor): BranchShipment
    {
        if (! $this->isReadyForShipment($source)) {
            throw ValidationException::withMessages([
                'shipment' => 'لا يمكن إنشاء شحنة قبل وصول الطلب إلى حالة جاهز للاستلام.',
            ]);
        }

        return DB::transaction(function () use ($source, $actor): BranchShipment {
            $existing = BranchShipment::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->first();

            if ($existing) {
                // Upgrade only an untouched draft. Previously loaded or received
                // shipments retain their original one-step receipt workflow.
                if (! $existing->multi_trip_enabled
                    && $existing->status === BranchShipment::PENDING_LOADING
                    && ($source instanceof ShowroomCakeRequest || $source instanceof ShowroomSweetsRequest)
                    && ! $existing->receivingInvoice?->isConfirmed()) {
                    $existing->update(['multi_trip_enabled' => true]);
                }
                return $existing->load('receivingInvoice.items');
            }

            [$fromLocationId, $toLocationId] = $this->locations($source);

            $shipment = BranchShipment::query()->create([
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'status' => BranchShipment::PENDING_LOADING,
                'multi_trip_enabled' => $source instanceof ShowroomCakeRequest || $source instanceof ShowroomSweetsRequest,
            ]);

            $invoice = BranchReceivingInvoice::query()->create([
                'branch_shipment_id' => $shipment->id,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'sending_location_id' => $fromLocationId,
                'receiving_location_id' => $toLocationId,
                'status' => 'draft',
            ]);

            if (! $shipment->multi_trip_enabled) {
                $invoice->update([
                    'invoice_number' => 'BRI-' . now()->format('Y') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
                ]);
            }

            $totalSent = 0.0;

            foreach ($this->snapshotItems($source) as $index => $item) {
                $quantity = round((float) $item['sent_quantity'], 3);
                $totalSent += $quantity;

                $invoice->items()->create([
                    'source_item_id' => $item['source_item_id'] ?? null,
                    'description' => $item['description'],
                    'unit' => $item['unit'] ?? null,
                    'sent_quantity' => $quantity,
                    'metadata' => $item['metadata'] ?? null,
                    'sort_order' => $index + 1,
                ]);
            }

            $invoice->update([
                'total_items_sent' => round($totalSent, 3),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'branch_shipment.created',
                module: 'delivery',
                recordType: 'branch_shipments',
                recordId: $shipment->id,
                oldValues: null,
                newValues: [
                    'source_type' => $this->sourceType($source),
                    'source_id' => $source->getKey(),
                    'from_location_id' => $fromLocationId,
                    'to_location_id' => $toLocationId,
                ],
                metadata: [
                    'source_number' => $this->sourceNumber($source),
                    'receiving_invoice_id' => $invoice->id,
                ],
            );

            return $shipment->load([
                'source',
                'fromLocation',
                'toLocation',
                'receivingInvoice.items',
            ]);
        });
    }

    public function markLoaded(
        BranchShipment $shipment,
        User $actor,
        User $driver,
        ?string $notes = null
    ): BranchShipment {
        if ($shipment->multi_trip_enabled) {
            throw ValidationException::withMessages(['shipment' => 'استخدم شاشة النقلات لتسجيل الكميات الفعلية.']);
        }
        if ($shipment->status !== BranchShipment::PENDING_LOADING) {
            throw ValidationException::withMessages([
                'status' => 'يمكن تأكيد التحميل فقط للشحنة التي ما زالت بانتظار التحميل.',
            ]);
        }

        $shipment->update([
            'status' => BranchShipment::LOADED,
            'driver_id' => $driver->id,
            'loaded_by' => $actor->id,
            'loaded_at' => now(),
            'notes' => $notes,
        ]);

        $shipment->receivingInvoice?->update([
            'driver_id' => $driver->id,
        ]);

        $loadedShipment = $shipment->fresh([
            'source',
            'driver.employee',
            'fromLocation',
            'toLocation',
            'receivingInvoice.items',
        ]);

        BranchShipmentNotifier::loaded($loadedShipment, $actor);

        return $loadedShipment;
    }

    public function dispatch(BranchShipment $shipment, User $actor): BranchShipment
    {
        if ($shipment->status !== BranchShipment::LOADED) {
            throw ValidationException::withMessages([
                'status' => 'يجب تأكيد تحميل الطلبية قبل خروجها للتوصيل.',
            ]);
        }

        $shipment->update([
            'status' => BranchShipment::OUT_FOR_DELIVERY,
            'dispatched_by' => $actor->id,
            'dispatched_at' => now(),
        ]);

        return $shipment->fresh();
    }

    public function markArrived(BranchShipment $shipment, User $actor): BranchShipment
    {
        if ($shipment->status !== BranchShipment::OUT_FOR_DELIVERY) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تسجيل الوصول قبل خروج الشحنة للتوصيل.',
            ]);
        }

        $shipment->update([
            'status' => BranchShipment::ARRIVED,
            'arrived_by' => $actor->id,
            'arrived_at' => now(),
        ]);

        return $shipment->fresh();
    }

    /**
     * @param array<int|string, array{received_quantity:mixed,note?:mixed}> $submittedItems
     */
    public function receive(
        BranchShipment $shipment,
        array $submittedItems,
        User $actor,
        ?string $notes = null
    ): BranchReceivingInvoice {
        if ($shipment->multi_trip_enabled) {
            throw ValidationException::withMessages(['shipment' => 'استخدم تأكيد استلام النقلة، ولا تُصدر فاتورة قبل اكتمال الطلب.']);
        }
        $invoice = DB::transaction(function () use ($shipment, $submittedItems, $actor, $notes): BranchReceivingInvoice {
            $lockedShipment = BranchShipment::query()
                ->with(['receivingInvoice.items', 'source'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            // Receiving is the branch's physical confirmation of arrival.
            // The legacy dispatch/arrived states remain supported for old shipments,
            // but are no longer mandatory steps between loading and receiving.
            if (! in_array($lockedShipment->status, [
                BranchShipment::LOADED,
                BranchShipment::OUT_FOR_DELIVERY,
                BranchShipment::ARRIVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'يجب تأكيد تحميل الطلبية أولاً قبل تسجيل استلامها.',
                ]);
            }

            $previousShipmentStatus = $lockedShipment->status;

            $invoice = $lockedShipment->receivingInvoice;

            if (! $invoice || $invoice->isConfirmed()) {
                throw ValidationException::withMessages([
                    'invoice' => 'فاتورة الاستلام غير متاحة أو تم اعتمادها مسبقًا.',
                ]);
            }

            $submitted = collect($submittedItems);
            $totalReceived = 0.0;
            $totalDifference = 0.0;

            foreach ($invoice->items as $item) {
                $row = $submitted->get((string) $item->id)
                    ?? $submitted->get($item->id)
                    ?? [];

                if (! array_key_exists('received_quantity', $row)) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.received_quantity" => 'يجب إدخال الكمية المستلمة لكل بند.',
                    ]);
                }

                $received = round((float) $row['received_quantity'], 3);
                $sent = round((float) $item->sent_quantity, 3);

                if ($received < 0 || $received > $sent + 0.0005) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.received_quantity" => 'الكمية المستلمة يجب أن تكون بين صفر والكمية المرسلة.',
                    ]);
                }

                $difference = round($sent - $received, 3);
                $totalReceived += $received;
                $totalDifference += $difference;

                $item->update([
                    'received_quantity' => $received,
                    'difference_quantity' => $difference,
                    'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
                ]);
            }

            $now = now();

            $invoice->update([
                'driver_id' => $lockedShipment->driver_id,
                'received_by' => $actor->id,
                'status' => 'confirmed',
                'total_items_received' => round($totalReceived, 3),
                'total_difference' => round($totalDifference, 3),
                'notes' => $notes,
                'issued_at' => $now,
            ]);

            $lockedShipment->update([
                'status' => BranchShipment::RECEIVED,
                'received_by' => $actor->id,
                'received_at' => $now,
            ]);

            $this->completeSourceAfterReceipt(
                $lockedShipment->source,
                $actor,
                $invoice->invoice_number
            );

            ActivityLogger::log(
                userId: $actor->id,
                action: 'branch_shipment.received',
                module: 'delivery',
                recordType: 'branch_shipments',
                recordId: $lockedShipment->id,
                oldValues: ['status' => $previousShipmentStatus],
                newValues: ['status' => BranchShipment::RECEIVED],
                metadata: [
                    'invoice_number' => $invoice->invoice_number,
                    'total_sent' => (float) $invoice->total_items_sent,
                    'total_received' => round($totalReceived, 3),
                    'difference' => round($totalDifference, 3),
                ],
            );

            return $invoice->fresh([
                'shipment.driver.employee',
                'sendingLocation',
                'receivingLocation',
                'receivedBy.employee',
                'items',
                'source',
            ]);
        });

        // Notify only after the invoice transaction has completed. Failed or
        // rolled-back receipts must not generate delivery notifications.
        BranchShipmentNotifier::received($invoice, $actor);

        return $invoice;
    }

    public function isReadyForShipment(Model $source): bool
    {
        $status = $source->status instanceof \BackedEnum
            ? $source->status->value
            : (string) $source->status;

        return match (true) {
            $source instanceof SpecialCakeOrder =>
                SpecialCakeOrder::normalizeLegacyWorkflowStatus($status) === 'ready',

            $source instanceof ShowroomCakeRequest =>
                $source->status->workflowValue() === 'ready',

            $source instanceof ShowroomSweetsRequest =>
                $source->status->workflowValue() === 'ready',

            default => false,
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private function locations(Model $source): array
    {
        return match (true) {
            $source instanceof SpecialCakeOrder => [
                (int) $source->factory_location_id,
                (int) $source->origin_branch_id,
            ],
            $source instanceof ShowroomCakeRequest,
            $source instanceof ShowroomSweetsRequest => [
                (int) $source->factory_location_id,
                (int) $source->requesting_location_id,
            ],
            default => throw ValidationException::withMessages([
                'source' => 'لا يمكن تحديد مسار هذه الطلبية.',
            ]),
        };
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function snapshotItems(Model $source): array
    {
        if ($source instanceof SpecialCakeOrder) {
            // Multi-size special cakes are itemized per selected size so the
            // branch can confirm which actual sizes arrived, not just 1 order.
            $sizes = collect($source->cake_sizes ?: [$source->cake_size])
                ->filter()
                ->values();

            if ($sizes->isEmpty()) {
                $sizes = collect([null]);
            }

            return $sizes->map(function ($size) use ($source): array {
                $sizeLabel = match ($size) {
                    'small' => 'صغير',
                    'medium' => 'وسط',
                    'large' => 'كبير',
                    'standard' => 'ستاندر',
                    default => $size,
                };

                $description = collect([
                    'كيك خاص',
                    $source->cake_type,
                    $sizeLabel,
                    $source->flavor,
                    $source->shape,
                ])->filter()->implode(' — ');

                return [
                    'source_item_id' => null,
                    'description' => $description !== '' ? $description : 'كيك خاص',
                    'unit' => 'كيك',
                    'sent_quantity' => 1,
                    'metadata' => [
                        'order_number' => $source->order_number,
                        'cake_size' => $size,
                        'cake_weight' => $source->cake_weight,
                        'persons_count' => $source->persons_count,
                        'cake_text' => $source->cake_text,
                        'customer_id' => $source->customer_id,
                    ],
                ];
            })->all();
        }

        if ($source instanceof ShowroomCakeRequest) {
            $source->loadMissing('items');

            return $source->items->map(function (ShowroomCakeRequestItem $item): array {
                $description = collect([
                    $item->cake_type,
                    $item->cake_size,
                    $item->flavor,
                    $item->shape,
                ])->filter()->implode(' — ');

                return [
                    'source_item_id' => $item->id,
                    'description' => $description !== '' ? $description : 'كيك فرع',
                    'unit' => 'وحدة',
                    'sent_quantity' => (int) $item->quantity,
                    'metadata' => [
                        'cake_type' => $item->cake_type,
                        'cake_size' => $item->cake_size,
                        'flavor' => $item->flavor,
                        'shape' => $item->shape,
                    ],
                ];
            })->values()->all();
        }

        if ($source instanceof ShowroomSweetsRequest) {
            $source->loadMissing('items.product');

            return $source->items->map(function (ShowroomSweetsRequestItem $item): array {
                return [
                    'source_item_id' => $item->id,
                    'description' => $item->displayProductName(),
                    'unit' => $item->requested_unit ?: 'وحدة',
                    'sent_quantity' => (float) $item->quantity,
                    'metadata' => [
                        'product_id' => $item->product_id,
                        'product_name_snapshot' => $item->product_name_snapshot,
                    ],
                ];
            })->values()->all();
        }

        return [];
    }

    public function finalizeSourceAfterFullReceipt(Model $source, User $actor, ?string $invoiceNumber): void
    {
        $this->completeSourceAfterReceipt($source, $actor, $invoiceNumber);
    }

    private function completeSourceAfterReceipt(
        Model $source,
        User $actor,
        ?string $invoiceNumber
    ): void {
        $note = 'تم استلام الطلبية في الفرع عبر فاتورة الاستلام ' . ($invoiceNumber ?: '');

        if ($source instanceof SpecialCakeOrder) {
            $status = $source->status instanceof CakeOrderStatus
                ? $source->status->workflowValue()
                : SpecialCakeOrder::normalizeLegacyWorkflowStatus((string) $source->status);

            if ($status === 'ready') {
                $this->specialCakeTransitions->transition(
                    $source,
                    CakeOrderStatus::Completed->value,
                    $actor,
                    $note
                );
            }

            return;
        }

        if ($source instanceof ShowroomCakeRequest) {
            $old = $source->status->workflowValue();

            if ($old === 'ready') {
                $source->update([
                    'status' => ShowroomCakeRequestStatus::Completed,
                    'fulfilled_at' => now(),
                    'handled_by' => $actor->id,
                ]);

                ShowroomCakeRequestNotifier::statusChanged(
                    $source->fresh(),
                    $old,
                    ShowroomCakeRequestStatus::Completed->value,
                    $actor
                );
            }

            return;
        }

        if ($source instanceof ShowroomSweetsRequest) {
            $old = $source->status->workflowValue();

            if ($old === 'ready') {
                $source->update([
                    'status' => ShowroomSweetsRequestStatus::Completed,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                    'fulfilled_at' => now(),
                ]);

                ShowroomSweetsRequestNotifier::statusChanged(
                    $source->fresh(),
                    $old,
                    ShowroomSweetsRequestStatus::Completed->value,
                    $actor
                );
            }
        }
    }
}

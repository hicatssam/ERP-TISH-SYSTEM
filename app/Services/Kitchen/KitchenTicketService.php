<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenTicketStatus;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ActivityLog;
use App\Services\Orders\OrderService;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\KitchenTicketNotification;
use App\Services\ActivityLogger;
use App\Services\ModuleService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenTicketService
{
    public function __construct(
        private readonly ModuleService $modules,
        private readonly KitchenRoutingService $routing
    ) {
    }

    public function dispatchIfEligible(
        Order $order,
        User $actor
    ): Collection {
        if (
            ! $this->modules->isEnabled('kitchen')
            || ! $order->isRestaurantOrder()
        ) {
            return collect();
        }

        $status = $order->status instanceof \BackedEnum
            ? $order->status->value
            : (string) $order->status;

        if ($status !== 'confirmed') {
            return collect();
        }

        return $this->dispatchConfirmedOrder(
            $order,
            $actor
        );
    }

    public function dispatchConfirmedOrder(
        Order $order,
        User $actor
    ): Collection {
        $order->loadMissing([
            'items.product.category',
            'items.modifiers',
        ]);

        if ($order->items->isEmpty()) {
            return collect();
        }

        $shouldNotify = false;

        $tickets = DB::transaction(function () use (
            $order,
            $actor,
            &$shouldNotify
        ): Collection {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $lockedOrder->status instanceof \BackedEnum
                ? $lockedOrder->status->value
                : (string) $lockedOrder->status;

            if ($status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'kitchen' =>
                        'لا يمكن إرسال الطلب إلى المطبخ قبل تأكيده.',
                ]);
            }

            $lockedOrder->loadMissing([
                'items.product.category',
                'items.modifiers',
                'kitchenTickets.station',
                'kitchenTickets.items',
            ]);

            /*
             * Idempotency guard:
             * once an order has kitchen tickets, never route the same order items
             * again even if station routing is changed later.
             */
            if ($lockedOrder->kitchenTickets->isNotEmpty()) {
                if (! $lockedOrder->kitchen_dispatched_at) {
                    $lockedOrder->update([
                        'kitchen_dispatched_at' => now(),
                    ]);
                }

                return $lockedOrder->kitchenTickets
                    ->load([
                        'station',
                        'items',
                        'order.restaurantTable.area',
                        'order.waiter.employee',
                    ])
                    ->values();
            }

            $groups = [];

            foreach ($lockedOrder->items as $item) {
                if (! $item->product) {
                    throw ValidationException::withMessages([
                        'kitchen' =>
                            'تعذر إرسال أحد بنود الطلب للمطبخ لأن المنتج غير موجود.',
                    ]);
                }

                [$station, $source] = $this->routing->resolve(
                    (int) $lockedOrder->location_id,
                    $item->product
                );

                $groups[$station->id] ??= [
                    'station' => $station,
                    'items' => [],
                ];

                $groups[$station->id]['items'][] = [
                    'order_item' => $item,
                    'routing_source' => $source,
                ];
            }

            $createdTickets = collect();
            $shouldNotify = true;

            foreach ($groups as $group) {
                $station = $group['station'];

                $ticket = KitchenTicket::query()->firstOrCreate(
                    [
                        'order_id' => $lockedOrder->id,
                        'kitchen_station_id' => $station->id,
                    ],
                    [
                        'ticket_number' => $this->ticketNumber(
                            $lockedOrder->id,
                            $station->id
                        ),
                        'location_id' => $lockedOrder->location_id,
                        'status' => KitchenTicketStatus::QUEUED->value,
                        'priority' => 0,
                        'notes' => $lockedOrder->notes,
                        'queued_at' => now(),
                        'dispatched_by' => $actor->id,
                    ]
                );

                foreach ($group['items'] as $routedItem) {
                    $item = $routedItem['order_item'];

                    $ticket->items()->firstOrCreate(
                        [
                            'order_item_id' => $item->id,
                        ],
                        [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'status' => KitchenTicketStatus::QUEUED->value,
                            'routing_source' => $routedItem['routing_source']->value,
                            'kitchen_notes' => $this->kitchenLineNotes($item),
                        ]
                    );
                }

                $createdTickets->push(
                    $ticket->fresh([
                        'station',
                        'items',
                        'order.restaurantTable.area',
                        'order.waiter.employee',
                    ])
                );
            }

            if (! $lockedOrder->kitchen_dispatched_at) {
                $lockedOrder->update([
                    'kitchen_dispatched_at' => now(),
                ]);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'kitchen.order_dispatched',
                module: 'kitchen',
                recordType: 'orders',
                recordId: $lockedOrder->id,
                oldValues: null,
                newValues: [
                    'tickets_count' => $createdTickets->count(),
                    'station_ids' => $createdTickets
                        ->pluck('kitchen_station_id')
                        ->values()
                        ->all(),
                ],
                metadata: [
                    'location_id' => $lockedOrder->location_id,
                    'order_number' => $lockedOrder->order_number,
                ],
            );

            return $createdTickets;
        });

        if ($shouldNotify) {
            DB::afterCommit(function () use ($tickets): void {
                foreach ($tickets as $ticket) {
                    $this->notifyQueued($ticket);
                }
            });
        }

        return $tickets;
    }

    public function assertOrderItemChangesAllowed(
        Order $order,
        array $incomingItems
    ): void {
        if (! $order->isRestaurantOrder()) {
            return;
        }

        if (! $order->kitchenTickets()->exists()) {
            return;
        }

        $order->loadMissing('items');

        $incomingById = collect($incomingItems)
            ->filter(fn ($row) => isset($row['id']))
            ->keyBy(fn ($row) => (int) $row['id']);

        foreach ($order->items as $item) {
            $incoming = $incomingById->get((int) $item->id);

            if (! $incoming) {
                continue;
            }

            $quantityChanged =
                abs(
                    (float) ($incoming['quantity'] ?? $item->quantity)
                    - (float) $item->quantity
                ) > 0.000001;

            $notesChanged =
                array_key_exists('kitchen_notes', $incoming)
                && trim((string) ($incoming['kitchen_notes'] ?? ''))
                    !== trim((string) ($item->kitchen_notes ?? ''));

            // An explicitly cancelled *queued* kitchen line can be reduced to
            // zero through the approved POS cancellation transaction. All other
            // KDS changes remain blocked to avoid silent ticket divergence.
            $cancelledToZero = $quantityChanged && ! $notesChanged
                && abs((float) ($incoming['quantity'] ?? -1)) < 0.000001
                && $item->kitchenTicketItems()->exists()
                && ! $item->kitchenTicketItems()
                    ->where('status', '!=', KitchenTicketStatus::CANCELLED->value)->exists();
            if ($cancelledToZero) {
                continue;
            }

            if ($quantityChanged || $notesChanged) {
                throw ValidationException::withMessages([
                    'items' =>
                        'لا يمكن تغيير كميات أو ملاحظات بنود طلب المطعم '
                        . 'بعد إرساله إلى المطبخ. ألغِ الطلب وأنشئ طلبًا جديدًا '
                        . 'حتى تبقى تذاكر KDS والمخزون متطابقة.',
                ]);
            }
        }
    }

    public function assertOrderCanComplete(Order $order): void
    {
        if (! $order->isRestaurantOrder()) {
            return;
        }

        if (! (bool) SystemSetting::get(
            'kitchen_require_served_before_complete',
            true
        )) {
            return;
        }

        if (! $order->kitchenTickets()->exists()) {
            return;
        }

        $blocking = $order->kitchenTickets()
            ->whereIn('status', [
                KitchenTicketStatus::QUEUED->value,
                KitchenTicketStatus::PREPARING->value,
                KitchenTicketStatus::READY->value,
            ])
            ->count();

        if ($blocking > 0) {
            throw ValidationException::withMessages([
                'order' =>
                    'لا يمكن إكمال الطلب قبل إنهاء جميع تذاكر المطبخ وتسليمها.',
            ]);
        }
    }

    public function start(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        return $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::QUEUED,
            next: KitchenTicketStatus::PREPARING,
            timestampColumn: 'started_at',
            actorColumn: 'started_by',
            action: 'kitchen.ticket_started'
        );
    }

    public function markReady(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        $ticket = $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::PREPARING,
            next: KitchenTicketStatus::READY,
            timestampColumn: 'ready_at',
            actorColumn: 'ready_by',
            action: 'kitchen.ticket_ready'
        );

        DB::afterCommit(
            fn () => $this->notifyReady($ticket)
        );

        return $ticket;
    }

    public function serve(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        return $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::READY,
            next: KitchenTicketStatus::SERVED,
            timestampColumn: 'served_at',
            actorColumn: 'served_by',
            action: 'kitchen.ticket_served'
        );
    }

    public function setUrgent(
        KitchenTicket $ticket,
        bool $urgent,
        User $actor
    ): KitchenTicket {
        $ticket = DB::transaction(function () use (
            $ticket,
            $urgent,
            $actor
        ): KitchenTicket {
            $locked = KitchenTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                in_array(
                    $locked->statusValue(),
                    [
                        KitchenTicketStatus::SERVED->value,
                        KitchenTicketStatus::CANCELLED->value,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'لا يمكن تغيير أولوية تذكرة منتهية.',
                ]);
            }

            $before = (int) $locked->priority;
            $after = $urgent ? 10 : 0;

            $locked->update([
                'priority' => $after,
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'kitchen.ticket_priority_changed',
                module: 'kitchen',
                recordType: 'kitchen_tickets',
                recordId: $locked->id,
                oldValues: [
                    'priority' => $before,
                ],
                newValues: [
                    'priority' => $after,
                ],
                metadata: [
                    'order_id' => $locked->order_id,
                    'location_id' => $locked->location_id,
                ],
            );

            return $locked->fresh([
                'station',
                'items',
                'order.restaurantTable.area',
            ]);
        });

        DB::afterCommit(
            fn () => $this->notifyKitchen(
                $ticket,
                'priority'
            )
        );

        return $ticket;
    }

    /**
     * Cancel one not-yet-started KDS line with an unpaid invoice. Changes to
     * KDS, frozen inventory, invoice/ledger and audit are one DB transaction.
     * Paid or preparing orders require the separate financial/kitchen workflow.
     */
    public function cancelQueuedLine(
        Order $order,
        int $orderItemId,
        string $reason,
        User $actor,
        OrderService $orders
    ): Order {
        return DB::transaction(function () use (
            $order, $orderItemId, $reason, $actor, $orders
        ): Order {
            $locked = Order::query()->whereKey($order->id)
                ->lockForUpdate()->firstOrFail();
            if (! $locked->isRestaurantOrder() || $locked->statusValue() !== 'confirmed') {
                throw ValidationException::withMessages([
                    'order' => 'يمكن إلغاء عنصر المطبخ فقط من طلب مؤكد لم يُغلق.',
                ]);
            }
            if ($locked->payments()->exists()
                || (float) ($locked->invoice?->paid_amount ?? 0) > 0.004) {
                throw ValidationException::withMessages([
                    'order' => 'يوجد تحصيل مرتبط بالطلب. يجب تسوية المبلغ أولًا قبل إلغاء العنصر.',
                ]);
            }

            $items = $locked->items()->orderBy('id')->lockForUpdate()->get();
            $line = $items->firstWhere('id', $orderItemId);
            if (! $line || (float) $line->quantity <= 0) {
                throw ValidationException::withMessages(['order_item_id' => 'العنصر غير متاح للإلغاء.']);
            }
            if ($items->filter(fn ($item) => (float) $item->quantity > 0)->count() <= 1) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'لا يمكن إلغاء آخر عنصر بهذه العملية؛ استخدم إلغاء الطلب كاملًا.',
                ]);
            }

            $lines = KitchenTicketItem::query()
                ->where('order_item_id', $orderItemId)
                ->orderBy('id')->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'هذا العنصر لم يُرسل إلى محطة مطبخ.',
                ]);
            }
            $tickets = KitchenTicket::query()
                ->where('order_id', $locked->id)
                ->whereIn('id', $lines->pluck('kitchen_ticket_id'))
                ->orderBy('id')->lockForUpdate()->get();
            if ($tickets->count() !== $lines->pluck('kitchen_ticket_id')->unique()->count()
                || $tickets->contains(fn ($ticket) => $ticket->statusValue() !== KitchenTicketStatus::QUEUED->value)
                || $lines->contains(fn ($item) => ($item->status instanceof \BackedEnum
                    ? $item->status->value : (string) $item->status) !== KitchenTicketStatus::QUEUED->value)) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'المطبخ بدأ التعامل مع هذا العنصر؛ يحتاج الأمر إلى مراجعة المسؤول.',
                ]);
            }

            $now = now();
            KitchenTicketItem::query()->whereIn('id', $lines->pluck('id'))->update([
                'status' => KitchenTicketStatus::CANCELLED->value,
                'cancelled_at' => $now,
            ]);
            foreach ($tickets as $ticket) {
                $remaining = $ticket->items()->whereNotIn('status', [
                    KitchenTicketStatus::CANCELLED->value,
                    KitchenTicketStatus::SERVED->value,
                ])->exists();
                if (! $remaining) {
                    $ticket->update([
                        'status' => KitchenTicketStatus::CANCELLED->value,
                        'cancelled_at' => $now,
                        'cancelled_by' => $actor->id,
                    ]);
                }
            }

            $updated = $orders->updateOrder($locked, [
                'customer_id' => $locked->customer_id,
                'payment_arrangement' => $locked->payment_arrangement instanceof \BackedEnum
                    ? $locked->payment_arrangement->value : $locked->payment_arrangement,
                'notes' => $locked->notes,
                'items' => $items->map(fn ($item): array => [
                    'id' => $item->id,
                    'quantity' => (int) $item->id === $orderItemId ? 0 : (float) $item->quantity,
                    'kitchen_notes' => $item->kitchen_notes,
                ])->all(),
            ], $actor);

            // This sensitive financial/KDS adjustment must fail atomically if
            // an audit record cannot be persisted.
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'restaurant.pos.kitchen_line_cancelled',
                'module' => 'restaurant',
                'record_type' => 'orders',
                'record_id' => $locked->id,
                'old_values' => ['order_item_id' => $line->id, 'quantity' => (float) $line->quantity],
                'new_values' => ['order_item_id' => $line->id, 'quantity' => 0],
                'metadata' => [
                    'location_id' => $locked->location_id,
                    'reason' => $reason,
                    'kitchen_ticket_ids' => $tickets->pluck('id')->all(),
                    'previous_total' => (float) $order->total_amount,
                    'new_total' => (float) $updated->total_amount,
                ],
                'created_at' => $now,
            ]);
            return $updated;
        }, 3);
    }

    public function cancelForOrder(
        Order $order,
        User $actor
    ): void {
        $tickets = $order->kitchenTickets()
            ->whereIn('status', [
                KitchenTicketStatus::QUEUED->value,
                KitchenTicketStatus::PREPARING->value,
                KitchenTicketStatus::READY->value,
            ])
            ->get();

        foreach ($tickets as $ticket) {
            DB::transaction(function () use (
                $ticket,
                $actor
            ): void {
                $locked = KitchenTicket::query()
                    ->whereKey($ticket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $old = $locked->statusValue();

                if (
                    ! in_array(
                        $old,
                        [
                            KitchenTicketStatus::QUEUED->value,
                            KitchenTicketStatus::PREPARING->value,
                            KitchenTicketStatus::READY->value,
                        ],
                        true
                    )
                ) {
                    return;
                }

                $locked->update([
                    'status' => KitchenTicketStatus::CANCELLED->value,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->id,
                ]);

                $cancelledAt = now();

                $locked->items()
                    ->whereNotIn('status', [
                        KitchenTicketStatus::SERVED->value,
                        KitchenTicketStatus::CANCELLED->value,
                    ])
                    ->update([
                        'status' => KitchenTicketStatus::CANCELLED->value,
                        'cancelled_at' => $cancelledAt,
                    ]);

                Order::query()
                    ->whereKey($locked->order_id)
                    ->update([
                        'updated_at' => $cancelledAt,
                    ]);

                $this->logTransition(
                    $locked,
                    $actor,
                    'kitchen.ticket_cancelled',
                    $old,
                    KitchenTicketStatus::CANCELLED->value
                );
            });
        }
    }

    private function transition(
        KitchenTicket $ticket,
        User $actor,
        KitchenTicketStatus $expected,
        KitchenTicketStatus $next,
        string $timestampColumn,
        string $actorColumn,
        string $action
    ): KitchenTicket {
        return DB::transaction(function () use (
            $ticket,
            $actor,
            $expected,
            $next,
            $timestampColumn,
            $actorColumn,
            $action
        ): KitchenTicket {
            $locked = KitchenTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $locked->statusValue();

            if ($current !== $expected->value) {
                throw ValidationException::withMessages([
                    'ticket' =>
                        'لا يمكن تنفيذ العملية لأن حالة التذكرة الحالية هي «'
                        . ($locked->status?->label() ?? $current)
                        . '».',
                ]);
            }

            $now = now();

            $locked->update([
                'status' => $next->value,
                $timestampColumn => $now,
                $actorColumn => $actor->id,
            ]);

            $itemUpdates = [
                'status' => $next->value,
            ];

            if ($next === KitchenTicketStatus::PREPARING) {
                $itemUpdates['started_at'] = $now;
            }

            if ($next === KitchenTicketStatus::READY) {
                $itemUpdates['ready_at'] = $now;
            }

            if ($next === KitchenTicketStatus::SERVED) {
                $itemUpdates['served_at'] = $now;
            }

            $locked->items()
                ->whereNotIn('status', [
                    KitchenTicketStatus::CANCELLED->value,
                ])
                ->update($itemUpdates);

            /*
             * Keep the parent order observable for the public customer tracker.
             *
             * Kitchen/KDS status changes happen on kitchen_tickets, while the
             * customer-facing status endpoint tracks the order plus its fresh
             * kitchen state. Touching orders.updated_at gives the client a
             * monotonic change marker on every:
             *
             * queued -> preparing -> ready -> served
             */
            Order::query()
                ->whereKey($locked->order_id)
                ->update([
                    'updated_at' => $now,
                ]);

            $this->logTransition(
                $locked,
                $actor,
                $action,
                $current,
                $next->value
            );

            return $locked->fresh([
                'station',
                'items',
                'order.restaurantTable.area',
                'order.waiter.employee',
            ]);
        });
    }

    private function kitchenLineNotes(\App\Models\OrderItem $item): ?string
    {
        $parts = [];

        $modifierNames = $item->modifiers
            ->pluck('modifier_name_snapshot')
            ->filter()
            ->values();

        if ($modifierNames->isNotEmpty()) {
            $parts[] = 'الإضافات: ' . $modifierNames->implode('، ');
        }

        if (filled($item->kitchen_notes)) {
            $parts[] = trim((string) $item->kitchen_notes);
        }

        return $parts === [] ? null : implode(' | ', $parts);
    }

    private function ticketNumber(
        int $orderId,
        int $stationId
    ): string {
        return sprintf(
            'KDS-%s-%d-%d',
            now()->format('ymd'),
            $orderId,
            $stationId
        );
    }

    private function notifyQueued(KitchenTicket $ticket): void
    {
        $this->notifyKitchen(
            $ticket,
            'queued'
        );
    }

    private function notifyReady(KitchenTicket $ticket): void
    {
        NotificationDispatcher::notifyLocationAndAdmins(
            locationId: (int) $ticket->location_id,
            notification: new KitchenTicketNotification(
                $ticket,
                'ready'
            ),
            permissions: [
                'kitchen.ticket.serve',
                'restaurant_pos.use',
                'restaurant_tables.view',
            ],
            globalPermissions: [
                'restaurant.view_all_locations',
            ],
        );
    }

    private function notifyKitchen(
        KitchenTicket $ticket,
        string $event
    ): void {
        NotificationDispatcher::notifyLocationAndAdmins(
            locationId: (int) $ticket->location_id,
            notification: new KitchenTicketNotification(
                $ticket,
                $event
            ),
            permissions: [
                'kitchen.ticket.start',
                'kitchen.ticket.ready',
                'kds.view',
            ],
            globalPermissions: [
                'kitchen.view_all_locations',
            ],
        );
    }

    private function logTransition(
        KitchenTicket $ticket,
        User $actor,
        string $action,
        string $oldStatus,
        string $newStatus
    ): void {
        ActivityLogger::log(
            userId: $actor->id,
            action: $action,
            module: 'kitchen',
            recordType: 'kitchen_tickets',
            recordId: $ticket->id,
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => $newStatus,
            ],
            metadata: [
                'order_id' => $ticket->order_id,
                'location_id' => $ticket->location_id,
                'kitchen_station_id' => $ticket->kitchen_station_id,
            ],
        );
    }
}

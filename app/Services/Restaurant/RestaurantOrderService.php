<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantServiceType;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly RestaurantTableService $tables
    ) {
    }

    public function createPosOrder(array $data, User $user): Order
    {
        $serviceType = RestaurantServiceType::from(
            (string) $data['service_type']
        );

        $openedSession = null;
        $locationId = (int) $data['location_id'];
        $token = (string) ($data['pos_request_token'] ?? '');
        $payloadHash = hash('sha256', json_encode(
            Arr::except($data, ['pos_request_token', 'payment_proof']),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ));
        $replay = function () use ($token, $payloadHash, $locationId, $user): ?Order {
            if ($token === '') return null;
            $existing = Order::query()->where('public_request_token', $token)->first();
            if (! $existing) return null;
            if ($existing->order_source !== 'restaurant_pos'
                || (int) $existing->location_id !== $locationId
                || (int) $existing->created_by !== (int) $user->id
                || data_get($existing->public_order_meta, 'pos_payload_hash') !== $payloadHash) {
                throw ValidationException::withMessages([
                    'pos_request_token' => 'رمز عملية الكاشير مستخدم لطلب مختلف. افتح طلبًا جديدًا.',
                ]);
            }
            return $existing->fresh(['items', 'invoice', 'restaurantTable.area', 'restaurantTableSession', 'waiter.employee']);
        };
        if ($existing = $replay()) return $existing;

        try {
        $order = DB::transaction(function () use (
            $data,
            $user,
            $serviceType,
            $locationId,
            $token,
            $payloadHash,
            &$openedSession
        ): Order {
            $payload = $data;

            $payload['location_id'] = $locationId;
            $payload['restaurant_service_type'] = $serviceType->value;
            $payload['waiter_id'] = $user->id;
            $payload['order_source'] = 'restaurant_pos';
            if ($token !== '') {
                $payload['public_request_token'] = $token;
                $payload['public_order_meta'] = ['pos_payload_hash' => $payloadHash];
            }

            $payload['guest_count'] =
                $serviceType === RestaurantServiceType::DineIn
                    ? max(1, (int) ($data['guest_count'] ?? 1))
                    : null;

            if ($serviceType === RestaurantServiceType::DineIn) {
                $table = RestaurantTable::query()
                    ->where('location_id', $locationId)
                    ->whereKey($data['restaurant_table_id'])
                    ->first();

                if (! $table) {
                    throw ValidationException::withMessages([
                        'restaurant_table_id' => 'الطاولة المحددة لا تتبع هذا الفرع.',
                    ]);
                }

                [$session, $wasOpened] = $this->tables->resolveForOrder(
                    $table,
                    $user,
                    (int) ($payload['guest_count'] ?? 1)
                );

                $payload['restaurant_table_id'] = $table->id;
                $payload['restaurant_table_session_id'] = $session->id;

                if ($wasOpened) {
                    $openedSession = $session;
                }
            } else {
                $payload['restaurant_table_id'] = null;
                $payload['restaurant_table_session_id'] = null;
            }

            $requiresVerification = $this->requiresPaymentVerification($payload);

            $splitPayments = $data['split_payments'] ?? [];
            if ($splitPayments) {
                // Create the confirmed order WITHOUT an initial single-method
                // collection. All two tender entries are in this same transaction.
                unset($payload['payment_method_id'], $payload['paid_amount']);
            }
            $order = $this->orders->createOrder(
                $payload,
                $user,
                ! $requiresVerification
            );

            if ($splitPayments) {
                $expectedCents = (int) round((float) $order->total_amount * 100);
                $actualCents = array_sum(array_map(
                    static fn ($part): int => (int) round((float) $part['amount'] * 100),
                    $splitPayments
                ));
                if ($expectedCents <= 0 || $actualCents !== $expectedCents) {
                    throw ValidationException::withMessages([
                        'split_payments' => sprintf(
                            'يجب أن يساوي مجموع الدفع المختلط إجمالي الطلب: ₪%.2f.',
                            $expectedCents / 100
                        ),
                    ]);
                }

                foreach ($splitPayments as $part) {
                    app(PaymentService::class)->recordPayment([
                        'order_type' => 'order',
                        'order_id' => $order->id,
                        'payment_method_id' => (int) $part['payment_method_id'],
                        'amount' => (int) round((float) $part['amount'] * 100) / 100,
                        'reference_number' => $part['reference_number'] ?? null,
                    ], $user);
                }
                $order->refresh();
            }

            ActivityLogger::log(
                userId: $user->id,
                action: 'restaurant.order.created',
                module: 'restaurant',
                recordType: 'orders',
                recordId: $order->id,
                newValues: [
                    'order_number' => $order->order_number,
                    'restaurant_service_type' => $serviceType->value,
                    'restaurant_table_id' => $order->restaurant_table_id,
                    'restaurant_table_session_id' => $order->restaurant_table_session_id,
                    'waiter_id' => $order->waiter_id,
                    'guest_count' => $order->guest_count,
                    'status' => $order->status?->value ?? $order->status,
                ],
                metadata: [
                    'location_id' => $locationId,
                    'source' => 'restaurant_pos',
                    'manual_discount_type' => $payload['discount_type'] ?? 'none',
                    'manual_discount_value' => $payload['discount_value'] ?? 0,
                    'manual_discount_reason' => $payload['discount_reason'] ?? null,
                    'split_tender_count' => count($splitPayments),
                ],
            );

            return $order;
        }, 3);
        } catch (QueryException $error) {
            // Unique request-token index wins any concurrent duplicate race.
            if ($token === '' || ! ($existing = $replay())) throw $error;
            return $existing;
        }

        if ($openedSession) {
            $this->tables->notifyOpened(
                $openedSession->fresh([
                    'table.area',
                    'location',
                    'openedBy.employee',
                ])
            );
        }

        return $order->fresh([
            'items',
            'invoice',
            'restaurantTable.area',
            'restaurantTableSession',
            'waiter.employee',
        ]);
    }

    private function requiresPaymentVerification(array $data): bool
    {
        if (($data['payment_arrangement'] ?? null) === 'pending_verification') {
            return true;
        }

        if (! empty($data['split_payments'])) {
            return false; // only fully confirmed non-verification tenders are supported
        }
        if (empty($data['payment_method_id'])) {
            return false;
        }

        $method = PaymentMethod::query()
            ->active()
            ->find($data['payment_method_id']);

        return (bool) $method?->requires_verification;
    }
}
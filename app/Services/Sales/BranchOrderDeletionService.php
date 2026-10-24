<?php

namespace App\Services\Sales;

use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only soft deletion with accounting and inventory safeguards.
 * Accepted/shipped/received/completed or linked orders must remain auditable.
 */
class BranchOrderDeletionService
{
    public function eligible(ShowroomCakeRequest|ShowroomSweetsRequest $order): bool
    {
        return $this->deletionBlockReason($order) === null;
    }

    /**
     * Give the operator a clear reason when a cancelled daily request
     * must remain available for shipment or inventory audit.
     */
    public function deletionBlockReason(ShowroomCakeRequest|ShowroomSweetsRequest $order): ?string
    {
        if ($order->status?->workflowValue() !== 'cancelled') {
            return 'الحذف متاح للطلبات الملغاة فقط.';
        }

        if ($order->branchShipment()->exists()) {
            return 'لا يمكن حذف هذا الطلب لوجود شحنة مرتبطة به. يجب الاحتفاظ بالسجل للتتبع.';
        }

        if ($order->rawMaterialStockRequest()->exists()) {
            return 'لا يمكن حذف هذا الطلب لوجود طلب مواد خام مرتبط به. يجب الاحتفاظ بالسجل للتتبع.';
        }

        return null;
    }

    /** @param class-string<ShowroomCakeRequest|ShowroomSweetsRequest> $type */
    public function deleteMany(string $type, array $ids, User $actor): int
    {
        abort_unless($actor->isAdmin(), 403);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($type, $ids, $actor): int {
            $orders = $type::query()->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get();

            if ($orders->count() !== count($ids)
                || $orders->contains(fn ($order) => ! $this->eligible($order))) {
                throw ValidationException::withMessages([
                    'ids' => 'تعذر الحذف: حدد فقط الطلبات الملغاة التي لا ترتبط بشحنات أو مواد خام أو حركات مخزون.',
                ]);
            }

            foreach ($orders as $order) {
                $this->softDelete($order, $actor);
            }

            return $orders->count();
        }, 3);
    }

    /** @param class-string<ShowroomCakeRequest|ShowroomSweetsRequest> $type */
    public function deleteAllEligible(string $type, User $actor): int
    {
        abort_unless($actor->isAdmin(), 403);

        return DB::transaction(function () use ($type, $actor): int {
            $query = $type::query()
                ->whereIn('status', ['cancelled', 'rejected'])
                ->whereDoesntHave('branchShipment')
                ->whereDoesntHave('rawMaterialStockRequest');

            // Prevent long-running mass edits. The admin may repeat after reviewing.
            $count = (clone $query)->count();
            if ($count > 500) {
                throw ValidationException::withMessages([
                    'mode' => 'عدد الطلبات المؤهلة أكبر من 500. استخدم تحديد الطلبات للحذف على دفعات حفاظاً على سلامة النظام.',
                ]);
            }

            $orders = $query->orderBy('id')->lockForUpdate()->get();

            foreach ($orders as $order) {
                if (! $this->eligible($order)) {
                    throw ValidationException::withMessages([
                        'mode' => 'تغيّرت حالة أحد الطلبات أثناء المراجعة. أعد المحاولة.',
                    ]);
                }
            }

            foreach ($orders as $order) {
                $this->softDelete($order, $actor);
            }

            return $orders->count();
        }, 3);
    }

    private function softDelete(Model $order, User $actor): void
    {
        $order->delete();
        ActivityLogger::log(
            userId: $actor->id,
            action: 'branch_order.soft_deleted',
            module: 'sales',
            recordType: $order->getTable(),
            recordId: $order->id,
            oldValues: [
                'request_number' => $order->request_number,
                'status' => $order->status?->value,
                'requesting_location_id' => $order->requesting_location_id,
            ],
            newValues: ['deleted_at' => $order->deleted_at?->toIso8601String()],
            metadata: ['deletion_kind' => 'admin_soft_delete'],
        );
    }
}

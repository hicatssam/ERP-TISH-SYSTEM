<?php

namespace App\Services\Sales;

use App\Enums\StockRequestStatus;
use App\Models\ShowroomCakeRequest;
use App\Models\StockRequest;
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

        $materials = $order->rawMaterialStockRequest()->first();
        if ($materials !== null) {
            if ($order instanceof ShowroomCakeRequest) {
                return 'لا يمكن حذف طلب كيك الفروع لوجود طلب مواد خام مرتبط به.';
            }

            $status = $materials->status?->value ?? $materials->status;
            if (! in_array($status, ['pending_factory_review', 'rejected'], true)) {
                return 'لا يمكن حذف الطلب لأن طلب المواد الخام المرتبط به تم اعتماده أو بدأ تنفيذه.';
            }

            if ($materials->transfers()->exists()
                || $materials->items()->where('approved_quantity', '>', 0)->exists()) {
                return 'لا يمكن حذف الطلب لوجود اعتماد كميات أو تحويلات مخزنية في طلب المواد الخام.';
            }
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
                ->whereDoesntHave('branchShipment');

            if ($type === ShowroomSweetsRequest::class) {
                // Exclude processed/approved stock requests while retaining
                // unprocessed pending/rejected requests in the eligible batch.
                $query->whereDoesntHave('rawMaterialStockRequest', function ($raw) {
                    $raw->whereNotIn('status', ['pending_factory_review', 'rejected'])
                        ->orWhereHas('transfers')
                        ->orWhereHas('items', fn ($item) => $item->where('approved_quantity', '>', 0));
                });
            } else {
                $query->whereDoesntHave('rawMaterialStockRequest');
            }

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
        if ($order instanceof ShowroomSweetsRequest) {
            // Lock the linked stock request so factory review and order
            // deletion cannot race. Keep the stock request and its items
            // for audit; only close it if it was still pending review.
            $materials = $order->rawMaterialStockRequest()->lockForUpdate()->first();

            if ($materials !== null) {
                $status = $materials->status?->value ?? $materials->status;
                if (! in_array($status, ['pending_factory_review', 'rejected'], true)
                    || $materials->transfers()->exists()
                    || $materials->items()->where('approved_quantity', '>', 0)->exists()) {
                    throw ValidationException::withMessages([
                        'ids' => 'لا يمكن الحذف: طلب المواد الخام مرتبط باعتماد أو حركة مخزون.',
                    ]);
                }

                if ($status === 'pending_factory_review') {
                    $materials->update([
                        'status' => StockRequestStatus::Rejected,
                        'rejection_reason' => 'أُلغي وأُرشف طلب حلويات الفرع المرتبط به: '.$order->request_number,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                    ]);

                    ActivityLogger::log(
                        userId: $actor->id,
                        action: 'stock_request.rejected_due_to_cancelled_source',
                        module: 'inventory',
                        recordType: $materials->getTable(),
                        recordId: $materials->id,
                        oldValues: ['status' => 'pending_factory_review'],
                        newValues: ['status' => 'rejected'],
                        metadata: [
                            'showroom_sweets_request_id' => $order->id,
                            'reason' => 'cancelled_daily_order_archived',
                        ],
                    );
                }
            }
        }

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

<?php
namespace App\Services\Inventory;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\StockRequest;
use App\Models\User;
use App\Notifications\StockRequestCreatedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
/** Optional material requisition linked to a daily order, created atomically with it. */
class DailyRawMaterialRequestService
{
    public function availableMaterials(): Collection
    {
        return Product::query()->active()->where('is_raw_material', true)
            ->with('unitDefinition')->orderBy('name_ar')->orderBy('name')->get();
    }
    public function validationRules(): array
    {
        return [
            'raw_materials' => ['sometimes', 'array', 'max:30'],
            'raw_materials.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('is_raw_material', true)->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'raw_materials.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'raw_materials.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
    public function validationMessages(): array
    {
        return [
            'raw_materials.array' => 'يجب إرسال المواد الخام في قائمة أصناف صحيحة.',
            'raw_materials.max' => 'يمكن إرفاق 30 مادة خام كحد أقصى.',
            'raw_materials.*.product_id.required' => 'اختر المادة الخام لكل سطر.',
            'raw_materials.*.product_id.integer' => 'اختيار المادة الخام غير صالح.',
            'raw_materials.*.product_id.distinct' => 'المادة الخام مكررة؛ اجمع كميتها في سطر واحد.',
            'raw_materials.*.product_id.exists' => 'المادة المختارة غير متاحة أو ليست مادة خام مفعّلة.',
            'raw_materials.*.quantity.required' => 'أدخل كمية المادة الخام.',
            'raw_materials.*.quantity.numeric' => 'كمية المادة الخام يجب أن تكون رقمًا.',
            'raw_materials.*.quantity.min' => 'أقل كمية مسموحة للمادة الخام هي 0.001.',
            'raw_materials.*.quantity.max' => 'أكبر كمية مسموحة للمادة الخام هي 999999.',
            'raw_materials.*.note.string' => 'ملاحظة المادة الخام يجب أن تكون نصًا.',
            'raw_materials.*.note.max' => 'ملاحظة المادة الخام لا تتجاوز 500 حرف.',
        ];
    }
    public function createForDailyRequest(
        ShowroomSweetsRequest|ShowroomCakeRequest $dailyRequest,
        array $lines,
        User $actor
    ): ?StockRequest {
        if ($lines === []) return null;
        abort_unless(
            Gate::forUser($actor)->allows('create', StockRequest::class),
            403,
            'لا تملك صلاحية طلب المواد الخام. اطلب تفعيل صلاحية إنشاء طلبات المخزون.'
        );
        $sourceKey = $dailyRequest instanceof ShowroomSweetsRequest
            ? 'showroom_sweets_request_id' : 'showroom_cake_request_id';
        $stockRequest = StockRequest::query()->create([
            'request_number' => StockRequestNumberService::generate(),
            'branch_location_id' => $dailyRequest->requesting_location_id,
            'factory_location_id' => $dailyRequest->factory_location_id,
            'status' => 'pending_factory_review',
            'notes' => 'مواد خام مرفقة بالطلب اليومي '.$dailyRequest->request_number,
            'created_by' => $actor->id,
            $sourceKey => $dailyRequest->id,
        ]);
        foreach ($lines as $line) {
            $stockRequest->items()->create([
                'product_id' => (int) $line['product_id'],
                'requested_quantity' => $line['quantity'],
                'note' => $line['note'] ?? null,
            ]);
        }
        return $stockRequest;
    }
    public function notifyFactory(StockRequest $stockRequest, User $actor): void
    {
        NotificationDispatcher::notifyByPermissions(
            new StockRequestCreatedNotification($stockRequest),
            ['stock_requests.view', 'stock_requests.review'],
            (int) $stockRequest->factory_location_id,
            ['inventory.view_all_locations'], $actor->id,
        );
    }
}


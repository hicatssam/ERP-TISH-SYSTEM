<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\User;
use App\Services\Notifications\ShowroomCakeRequestNotifier;
use Illuminate\Http\Request;
use App\Services\Inventory\DailyRawMaterialRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\Sales\BranchOrderDeletionService;

class ShowroomCakeRequestController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $canViewAll =
            $user->isAdmin()
            || $user->can(
                'showroom_cake_requests.view_all'
            );

        $query = ShowroomCakeRequest::query()
            ->with([
                'requestingLocation',
                'factoryLocation',
                'creator',
                'items',
                'branchShipment.loadedBy.employee',
                'branchShipment.receivedBy.employee',
                'branchShipment.receivingInvoice',
            ]);

        if (! $canViewAll) {
            $locationIds =
                $this->userLocationIds($user);

            if ($locationIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($query) use (
                        $locationIds
                    ): void {
                        $query
                            ->whereIn(
                                'requesting_location_id',
                                $locationIds
                            )
                            ->orWhereIn(
                                'factory_location_id',
                                $locationIds
                            );
                    }
                );
            }
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if (
            $request->filled('location_id')
            && $canViewAll
        ) {
            $query->where(
                'requesting_location_id',
                $request->integer('location_id')
            );
        }

        $requests = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $editableIds = $requests->getCollection()
            ->filter(fn (ShowroomCakeRequest $item) => $this->canEditRequest($user, $item))
            ->pluck('id')
            ->all();

        $cancelableIds = $requests->getCollection()
            ->filter(fn (ShowroomCakeRequest $item) => $this->canCancelRequest($user, $item))
            ->pluck('id')
            ->all();

        $locations = $canViewAll
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        $statusEnum =
            ShowroomCakeRequestStatus::workflowCases();

        return view(
            'sales.showroom-cake-requests.index',
            compact(
                'requests',
                'locations',
                'statusEnum',
                'canViewAll',
                'editableIds',
                'cancelableIds'
            )
        );
    }

    public function create()
    {
        /** @var User $user */
        $user = Auth::user();

        $canChooseBranch =
            $user->isAdmin();

        $branch =
            $user->primaryLocation();

        if (! $canChooseBranch) {
            abort_unless(
                $branch
                && $branch->isBranch()
                && $branch->is_active,
                403,
                'لا يوجد فرع فعال مرتبط بحسابك.'
            );
        }

        $branches = $canChooseBranch
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect([$branch]);

        $factories = Location::query()
            ->where('type', 'factory')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rawMaterials = app(DailyRawMaterialRequestService::class)->availableMaterials();

        // Display cakes first, followed by cake moulds; preserve category boundaries.
        $cakeProducts = Product::query()
            ->active()
            ->where('is_raw_material', false)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category', 'activeVariants.size'])
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Product $product) =>
                ($product->branchCakeCatalogGroup() === 'الكيك' ? '0|' : '1|')
                . mb_strtolower($product->name_ar ?: $product->name))
            ->values();
        // Use actual catalogue categories, not the two presentation groups.
        $cakeProductGroups = $cakeProducts->groupBy('category_id');

        return view(
            'sales.showroom-cake-requests.create',
            compact(
                'branch',
                'branches',
                'factories',
                'canChooseBranch',
                'rawMaterials',
                'cakeProducts',
                'cakeProductGroups'
            )
        );
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $canChooseBranch =
            $user->isAdmin();

        $validated = $request->validate([
            ...app(DailyRawMaterialRequestService::class)->validationRules(),
            'requesting_location_id' => [
                Rule::requiredIf(
                    $canChooseBranch
                ),
                'nullable',
                Rule::exists(
                    'locations',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where('type', 'branch')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                ),
            ],
            'factory_location_id' => [
                'required',
                Rule::exists(
                    'locations',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where('type', 'factory')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                ),
            ],
            'needed_by' => [
                'required',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.cake_type' => [
                'required',
                'string',
                'max:100',
            ],
            'items.*.cake_size' => [
                'nullable',
                'string',
                'max:50',
                'not_in:standard',
            ],
            'items.*.layer_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.flavor' => [
                'nullable',
                'string',
                'max:100',
            ],
            'items.*.shape' => [
                'nullable',
                'string',
                'max:50',
                'not_in:standard_tiers',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'items.*.reserved_quantity' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'items.*.reservation_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ], array_merge(app(DailyRawMaterialRequestService::class)->validationMessages(), [
            'needed_by.required' => 'تاريخ الحاجة مطلوب لإرسال طلب كيك الفرع.',
        ]));

        foreach ($validated['items'] as $index => $item) {
            if (! empty($item['product_id'])) {
                $product = Product::query()->active()
                    ->where('is_raw_material', false)
                    ->whereHas('category', fn ($query) => $query->where('is_active', true))
                    ->find($item['product_id']);
                $variant = null;
                if ($product && ! empty($item['product_variant_id'])) {
                    $variant = $product->activeVariants()->with('size')
                        ->find($item['product_variant_id']);
                }
                if (! $product || (! empty($item['product_variant_id']) && ! $variant)
                    || (empty($item['product_variant_id']) && $product->activeVariants()->exists())) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.product_id" => 'المنتج أو الحجم المختار غير متاح في كتالوج الكيك.',
                    ]);
                }
                $validated['items'][$index]['cake_type'] = $product->name_ar ?: $product->name;
                $validated['items'][$index]['cake_size'] = $variant
                    ? ($variant->size?->displayName() ?: $variant->displayName())
                    : null;
            }
            $reserved =
                (int) ($item['reserved_quantity'] ?? 0);

            $quantity =
                (int) $item['quantity'];

            if ($reserved > $quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.{$index}.reserved_quantity" =>
                        'الكمية المحجوزة للعملاء لا يمكن أن تتجاوز الكمية المطلوبة.',
                ]);
            }
        }

        $branch = $canChooseBranch
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->findOrFail(
                    (int)
                    $validated[
                        'requesting_location_id'
                    ]
                )
            : $user->primaryLocation();

        abort_unless(
            $branch
            && $branch->isBranch()
            && $branch->is_active,
            403,
            'لا يمكن إنشاء الطلب بدون فرع صحيح.'
        );

        $factory = Location::query()
            ->where('type', 'factory')
            ->where('is_active', true)
            ->findOrFail(
                (int)
                $validated[
                    'factory_location_id'
                ]
            );

        $showroomRequest =
            DB::transaction(
                function () use (
                    $validated,
                    $user,
                    $branch,
                    $factory
                ) {
                    $showroomRequest =
                        ShowroomCakeRequest::query()
                            ->create([
                                'request_number' =>
                                    ShowroomCakeRequest::generateNumber(),
                                'requesting_location_id' =>
                                    $branch->id,
                                'factory_location_id' =>
                                    $factory->id,
                                'status' =>
                                    ShowroomCakeRequestStatus::InProgress,
                                'needed_by' =>
                                    $validated['needed_by']
                                    ?? now()->addDay()->toDateString(),
                                'notes' =>
                                    $validated['notes']
                                    ?? null,
                                'created_by' =>
                                    $user->id,
                                'submitted_at' =>
                                    now(),
                            ]);

                    foreach (
                        $validated['items']
                        as $item
                    ) {
                        ShowroomCakeRequestItem::query()
                            ->create([
                                'showroom_cake_request_id' =>
                                    $showroomRequest->id,
                                'cake_type' =>
                                    $item['cake_type'],
                                'cake_size' =>
                                    $item['cake_size']
                                    ?? null,
                    'layer_count' => $item['layer_count'] ?? null,
                                'flavor' =>
                                    $item['flavor']
                                    ?? null,
                                'shape' =>
                                    $item['shape']
                                    ?? null,
                                'quantity' =>
                                    $item['quantity'],
                                'reserved_quantity' =>
                                    (int) (
                                        $item['reserved_quantity']
                                        ?? 0
                                    ),
                                'reservation_notes' =>
                                    $item['reservation_notes']
                                    ?? null,
                                'notes' =>
                                    $item['notes']
                                    ?? null,
                            ]);
                    }

                    app(DailyRawMaterialRequestService::class)->createForDailyRequest(
                        $showroomRequest,
                        $validated['raw_materials'] ?? [],
                        $user
                    );

                    return $showroomRequest;
                }
            );

        // Order and items are committed above. An optional notification failure
        // must not turn a successful submission into HTTP 500 or invite a retry.
        try {
            if ($rawStockRequest = $showroomRequest->rawMaterialStockRequest()->first()) {
                app(DailyRawMaterialRequestService::class)->notifyFactory($rawStockRequest, $user);
            }
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Showroom cake raw-material notification failed after save', [
                'showroom_cake_request_id' => $showroomRequest->id,
                'exception' => $exception,
            ]);
        }

        try {
            ShowroomCakeRequestNotifier::requestCreated(
                $showroomRequest->fresh([
                    'requestingLocation',
                    'factoryLocation',
                    'items',
                ]),
                $user
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Showroom cake notification failed after save', [
                'showroom_cake_request_id' => $showroomRequest->id,
                'exception' => $exception,
            ]);
        }

        return redirect()
            ->route(
                'showroom-cake-requests.show',
                $showroomRequest
            )
            ->with(
                'success',
                'تم إنشاء طلب كيك الفرع واعتماده تلقائيًا من النظام.'
            );
    }

    public function edit(ShowroomCakeRequest $showroomCakeRequest)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess($user, $showroomCakeRequest);
        abort_unless(
            $this->canEditRequest($user, $showroomCakeRequest),
            403,
            'لا يمكن تعديل الطلب بعد بدء تنفيذه.'
        );

        $showroomCakeRequest->load('items');

        $canChooseBranch = $user->isAdmin();
        $branch = $showroomCakeRequest->requestingLocation;

        $branches = $canChooseBranch
            ? Location::query()->where('type', 'branch')->where('is_active', true)->orderBy('name')->get()
            : collect([$branch]);

        $factories = Location::query()
            ->where('type', 'factory')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'sales.showroom-cake-requests.edit',
            compact(
                'showroomCakeRequest',
                'branch',
                'branches',
                'factories',
                'canChooseBranch'
            )
        );
    }

    public function update(
        Request $request,
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess($user, $showroomCakeRequest);
        abort_unless(
            $this->canEditRequest($user, $showroomCakeRequest),
            403,
            'لا يمكن تعديل الطلب بعد بدء تنفيذه.'
        );

        $canChooseBranch = $user->isAdmin();

        $validated = $request->validate([
            'requesting_location_id' => [
                Rule::requiredIf($canChooseBranch),
                'nullable',
                Rule::exists('locations', 'id')->where(
                    fn ($query) => $query
                        ->where('type', 'branch')
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                ),
            ],
            'factory_location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(
                    fn ($query) => $query
                        ->where('type', 'factory')
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                ),
            ],
            'needed_by' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cake_type' => ['required', 'string', 'max:100'],
            'items.*.cake_size' => ['nullable', 'string', 'max:50', 'not_in:standard'],
            'items.*.layer_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.flavor' => ['nullable', 'string', 'max:100'],
            'items.*.shape' => ['nullable', 'string', 'max:50', 'not_in:standard_tiers'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.reserved_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.reservation_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'needed_by.required' => 'تاريخ الحاجة مطلوب لإرسال طلب كيك الفرع.',
        ]);

        foreach ($validated['items'] as $index => $item) {
            if ((int) ($item['reserved_quantity'] ?? 0) > (int) $item['quantity']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.{$index}.reserved_quantity" =>
                        'الكمية المحجوزة للعملاء لا يمكن أن تتجاوز الكمية المطلوبة.',
                ]);
            }
        }

        $branchId = $canChooseBranch
            ? (int) $validated['requesting_location_id']
            : (int) $showroomCakeRequest->requesting_location_id;

        DB::transaction(function () use (
            $showroomCakeRequest,
            $validated,
            $branchId
        ): void {
            $showroomCakeRequest->update([
                'requesting_location_id' => $branchId,
                'factory_location_id' => (int) $validated['factory_location_id'],
                'needed_by' => $validated['needed_by'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $showroomCakeRequest->items()->delete();

            foreach ($validated['items'] as $item) {
                $showroomCakeRequest->items()->create([
                    'cake_type' => $item['cake_type'],
                    'cake_size' => $item['cake_size'] ?? null,
                    'layer_count' => $item['layer_count'] ?? null,
                    'flavor' => $item['flavor'] ?? null,
                    'shape' => $item['shape'] ?? null,
                    'quantity' => $item['quantity'],
                    'reserved_quantity' => (int) ($item['reserved_quantity'] ?? 0),
                    'reservation_notes' => $item['reservation_notes'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('showroom-cake-requests.show', $showroomCakeRequest)
            ->with('success', 'تم تحديث طلب كيك الفرع.');
    }

    public function show(
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomCakeRequest
        );

        $showroomCakeRequest->load([
            'requestingLocation',
            'factoryLocation',
            'creator',
            'handledBy',
            'items',
            'branchShipment.driver.employee',
            'branchShipment.receivingInvoice',
            'rawMaterialStockRequest.items.product.unitDefinition',
        ]);

        $allowedTransitions =
            $this->allowedTransitionsForUser(
                $user,
                $showroomCakeRequest
            );

        $canEditRequest = $this->canEditRequest(
            $user,
            $showroomCakeRequest
        );

        $canCancelRequest = $this->canCancelRequest(
            $user,
            $showroomCakeRequest
        );

        $canManageReservations =
            $user->isAdmin()
            || (
                $user->can(
                    'showroom_cake_requests.reservations'
                )
                && in_array(
                    (int) $showroomCakeRequest
                        ->requesting_location_id,
                    $this->userLocationIds($user),
                    true
                )
            );

        return view(
            'sales.showroom-cake-requests.show',
            compact(
                'showroomCakeRequest',
                'allowedTransitions',
                'canEditRequest',
                'canCancelRequest',
                'canManageReservations'
            )
        );
    }

    public function updateItemReservation(
        Request $request,
        ShowroomCakeRequest $showroomCakeRequest,
        ShowroomCakeRequestItem $item
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomCakeRequest
        );

        abort_unless(
            (int) $item->showroom_cake_request_id
                === (int) $showroomCakeRequest->id,
            404
        );

        $canManageReservation =
            $user->isAdmin()
            || (
                $user->can(
                    'showroom_cake_requests.reservations'
                )
                && in_array(
                    (int) $showroomCakeRequest
                        ->requesting_location_id,
                    $this->userLocationIds($user),
                    true
                )
            );

        abort_unless(
            $canManageReservation,
            403,
            'تعديل حجوزات العملاء متاح للفرع الطالب فقط.'
        );

        if ($showroomCakeRequest->status->isTerminal()) {
            return back()->with(
                'error',
                'لا يمكن تعديل حجوزات العملاء بعد انتهاء الطلب.'
            );
        }

        $validated = $request->validate([
            'reserved_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'reservation_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if (
            (int) $validated['reserved_quantity']
            > (int) $item->quantity
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reserved_quantity' =>
                    'الكمية المحجوزة للعملاء لا يمكن أن تتجاوز الكمية المطلوبة.',
            ]);
        }

        $item->update([
            'reserved_quantity' =>
                (int) $validated[
                    'reserved_quantity'
                ],
            'reservation_notes' =>
                $validated[
                    'reservation_notes'
                ]
                ?? null,
        ]);

        return back()->with(
            'success',
            'تم تحديث كمية الحجز للعملاء.'
        );
    }

    public function updateStatus(
        Request $request,
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomCakeRequest
        );

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::enum(
                    ShowroomCakeRequestStatus::class
                ),
            ],
            'factory_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'cancellation_reason' => [
                Rule::requiredIf($request->input('status') === 'cancelled'),
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ]);

        $newStatus =
            ShowroomCakeRequestStatus::from(
                $validated['status']
            );

        $stagePermission = match ($newStatus) {
            ShowroomCakeRequestStatus::InProgress => 'showroom_cake_requests.accept',
            ShowroomCakeRequestStatus::Ready => 'showroom_cake_requests.ready',
            ShowroomCakeRequestStatus::Cancelled => 'showroom_cake_requests.cancel_factory',
            default => 'showroom_cake_requests.update_status',
        };
        abort_unless($user->isAdmin() || $user->can($stagePermission), 403,
            'لا تملك صلاحية إجراء هذه المرحلة من طلب كيك الفرع.');

        if (
            ! $showroomCakeRequest
                ->canTransitionTo(
                    $newStatus
                )
        ) {
            return back()->with(
                'error',
                'لا يمكن الانتقال إلى هذه الحالة.'
            );
        }

        if (
            $newStatus ===
            ShowroomCakeRequestStatus::Completed
        ) {
            return back()->with(
                'error',
                'إكمال الطلب يتم فقط بعد استلام الفرع وإصدار فاتورة الاستلام.'
            );
        }

        abort_unless(
            $this->transitionLocationAllowed(
                $user,
                $showroomCakeRequest,
                $newStatus
            ),
            403,
            'هذه المرحلة تخص موقعًا آخر.'
        );

        if ($newStatus === ShowroomCakeRequestStatus::Cancelled) {
            if (! $this->canBeCancelled($showroomCakeRequest)) {
                return back()->with('error', 'لا يمكن إلغاء الطلب بعد تجهيزه للشحن أو إكماله.');
            }

            $this->cancelWithReason(
                $showroomCakeRequest,
                $user,
                $validated['cancellation_reason']
            );

            return back()->with('success', 'تم إلغاء طلب كيك الفرع وتسجيل السبب.');
        }

        $oldStatus =
            $showroomCakeRequest
                ->status
                ->workflowValue();

        $data = [
            'status' => $newStatus,
        ];

        if (
            array_key_exists(
                'factory_notes',
                $validated
            )
        ) {
            $data['factory_notes'] =
                $validated['factory_notes'];
        }

        if (
            $newStatus ===
            ShowroomCakeRequestStatus::InProgress
        ) {
            $data['handled_by'] =
                $user->id;
        }

        if (
            $newStatus ===
            ShowroomCakeRequestStatus::Completed
        ) {
            $data['fulfilled_at'] =
                now();

            $data['handled_by'] =
                $user->id;
        }

        $showroomCakeRequest->update(
            $data
        );

        $showroomCakeRequest->refresh();

        ShowroomCakeRequestNotifier::statusChanged(
            $showroomCakeRequest,
            $oldStatus,
            $newStatus->value,
            $user
        );

        return back()->with(
            'success',
            'تم تحديث حالة طلب كيك الفرع.'
        );
    }

    /**
     * Branch cancellation uses a separate permission from factory production
     * transitions. A branch user may act only on requests for their branch.
     */
    public function cancel(
        Request $request,
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess($user, $showroomCakeRequest);

        abort_unless(
            $this->canCancelRequest($user, $showroomCakeRequest),
            403,
            'لا تملك صلاحية إلغاء هذا الطلب.'
        );

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->cancelWithReason(
            $showroomCakeRequest,
            $user,
            $validated['cancellation_reason']
        );

        return redirect()
            ->route('showroom-cake-requests.show', $showroomCakeRequest)
            ->with('success', 'تم إلغاء طلب كيك الفرع وتسجيل السبب.');
    }

    private function cancelWithReason(
        ShowroomCakeRequest $request,
        User $actor,
        string $reason
    ): void {
        $oldStatus = DB::transaction(function () use ($request, $actor, $reason): string {
            $locked = ShowroomCakeRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->id);

            abort_unless(
                $this->canBeCancelled($locked),
                409,
                'تغيّرت حالة الطلب ولا يمكن إلغاؤه.'
            );

            $fromStatus = $locked->status->workflowValue();

            $locked->update([
                'status' => ShowroomCakeRequestStatus::Cancelled,
                'cancellation_reason' => trim($reason),
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return $fromStatus;
        });

        $request->refresh();

        ShowroomCakeRequestNotifier::statusChanged(
            $request,
            $oldStatus,
            ShowroomCakeRequestStatus::Cancelled->value,
            $actor
        );
    }

    public function destroy(ShowroomCakeRequest $showroomCakeRequest)
    {
        /** @var User $actor */
        $actor = Auth::user();
        abort_unless($actor->isAdmin(), 403);

        app(BranchOrderDeletionService::class)->deleteMany(
            ShowroomCakeRequest::class, [$showroomCakeRequest->id], $actor
        );

        return redirect()->route('showroom-cake-requests.index')
            ->with('success', 'تمت أرشفة الطلب الملغى دون المساس بالسجلات المالية والمخزنية.');
    }

    /**
     * @return array<int, ShowroomCakeRequestStatus>
     */
    private function allowedTransitionsForUser(
        User $user,
        ShowroomCakeRequest $request
    ): array {
        return collect(
            $request
                ->status
                ->allowedTransitions()
        )
            ->filter(
                fn (
                    ShowroomCakeRequestStatus $status
                ): bool =>
                    $status !== ShowroomCakeRequestStatus::Completed
                    && (
                        $status !== ShowroomCakeRequestStatus::Cancelled
                        || $this->canBeCancelled($request)
                    )
                    && (
                        $user->isAdmin()
                        || $user->can(match ($status) {
                            ShowroomCakeRequestStatus::InProgress => 'showroom_cake_requests.accept',
                            ShowroomCakeRequestStatus::Ready => 'showroom_cake_requests.ready',
                            ShowroomCakeRequestStatus::Cancelled => 'showroom_cake_requests.cancel_factory',
                            default => 'showroom_cake_requests.update_status',
                        })
                    )
                    && $this->transitionLocationAllowed(
                        $user,
                        $request,
                        $status
                    )
            )
            ->values()
            ->all();
    }

    private function transitionLocationAllowed(
        User $user,
        ShowroomCakeRequest $request,
        ShowroomCakeRequestStatus $status
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        if (
            $status ===
            ShowroomCakeRequestStatus::Completed
        ) {
            return in_array(
                (int) $request
                    ->requesting_location_id,
                $locationIds,
                true
            );
        }

        if (
            $status ===
            ShowroomCakeRequestStatus::Cancelled
        ) {
            return in_array(
                (int) $request
                    ->requesting_location_id,
                $locationIds,
                true
            )
            || in_array(
                (int) $request
                    ->factory_location_id,
                $locationIds,
                true
            );
        }

        return in_array(
            (int) $request
                ->factory_location_id,
            $locationIds,
            true
        );
    }

    private function ensureCanAccess(
        User $user,
        ShowroomCakeRequest $request
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        abort_unless(
            in_array(
                (int) $request
                    ->requesting_location_id,
                $locationIds,
                true
            )
            || in_array(
                (int) $request
                    ->factory_location_id,
                $locationIds,
                true
            ),
            403,
            'لا تملك صلاحية الوصول إلى هذا الطلب.'
        );
    }

    /**
     * @return array<int, int>
     */
    private function userLocationIds(
        User $user
    ): array {
        return $user
            ->employee
            ?->locations()
            ->pluck('locations.id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all()
            ?? [];
    }
    private function canBeCancelled(ShowroomCakeRequest $request): bool
    {
        return in_array(
            $request->status->workflowValue(),
            ['pending', 'in_progress'],
            true
        ) && ! $request->branchShipment()->exists();
    }

    private function canCancelRequest(User $user, ShowroomCakeRequest $request): bool
    {
        if (! $this->canBeCancelled($request)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->can('showroom_cake_requests.cancel')
            && in_array(
                (int) $request->requesting_location_id,
                $this->userLocationIds($user),
                true
            );
    }

    private function canEditRequest(
        User $user,
        ShowroomCakeRequest $request
    ): bool {
        if (! in_array(
            $request->status->workflowValue(),
            ['pending', 'in_progress'],
            true
        )) {
            return false;
        }

        if ($request->branchShipment()->exists()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->can('showroom_cake_requests.edit')
            && in_array(
                (int) $request->requesting_location_id,
                $this->userLocationIds($user),
                true
            );
    }

}


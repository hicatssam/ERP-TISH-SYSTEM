<?php

namespace App\Http\Controllers\Sales;

use App\Enums\CakeOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CakeOrderComment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\CustomerCreatedNotification;
use App\Notifications\SpecialCakeOrderActivityNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\SpecialCakes\SpecialCakeOrderService;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\ActivityLogger;

class SpecialCakeOrderController extends Controller
{
    public function __construct(
        private SpecialCakeOrderService $cakeOrderService,
        private SpecialCakeStatusTransitionService $transitionService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $baseQuery = SpecialCakeOrder::query()
            ->with([
                'customer',
                'originBranch',
                'factory',
                'creator',
                'attachments' => fn ($query) => $query->latest('id'),
                'branchShipment.loadedBy.employee',
                'branchShipment.receivedBy.employee',
                'branchShipment.receivingInvoice',
            ]);

        if (! $user->isAdmin() && ! $user->can('cake_orders.view_all')) {
            $locationIds = $user->employee?->locations()
                ->pluck('locations.id')
                ->map(fn ($id) => (int) $id)
                ->all() ?? [];

            $baseQuery->where(function ($query) use ($locationIds): void {
                $query->whereIn('origin_branch_id', $locationIds)
                    ->orWhereIn('factory_location_id', $locationIds);
            });
        }

        $activeStatuses = collect(
            CakeOrderStatus::workflowCases()
        )
            ->reject(
                fn (CakeOrderStatus $status) =>
                    $status->isTerminal()
            )
            ->map(
                fn (CakeOrderStatus $status) =>
                    $status->value
            )
            ->values()
            ->all();

        /*
         * Daily operations overview. These figures intentionally ignore the
         * search/status filters below so the team always sees the real workload.
         */
        $summary = [
            'total' => (clone $baseQuery)->count(),

            'overdue' => (clone $baseQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate('required_date', '<', today())
                ->count(),

            'due_today' => (clone $baseQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate('required_date', today())
                ->count(),

            'due_tomorrow' => (clone $baseQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate(
                    'required_date',
                    today()->addDay()
                )
                ->count(),

            'due_soon' => (clone $baseQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate(
                    'required_date',
                    '>=',
                    today()->addDay()
                )
                ->whereDate(
                    'required_date',
                    '<=',
                    today()->addDays(3)
                )
                ->count(),
        ];

        /*
         * Priority board for TODAY only. Terminal orders are intentionally
         * excluded. Orders sharing the same required_time are grouped together
         * in the view so they appear next to each other.
         */
        $todayPriorityOrders = (clone $baseQuery)
            ->whereIn('status', $activeStatuses)
            ->whereDate('required_date', today())
            ->orderByRaw('required_time IS NULL')
            ->orderBy('required_time')
            ->orderBy('id')
            ->get();

        $priorityGroups = $todayPriorityOrders
            ->groupBy(function (SpecialCakeOrder $order): string {
                if (! $order->required_time) {
                    return 'unscheduled';
                }

                return substr(
                    (string) $order->required_time,
                    0,
                    5
                );
            });

        /*
         * Seven-day planning strip. Counts use active orders only so the cards
         * reflect work still requiring operational attention.
         */
        $dailyCountRows = (clone $baseQuery)
            ->whereIn('status', $activeStatuses)
            ->whereDate(
                'required_date',
                '>=',
                today()
            )
            ->whereDate(
                'required_date',
                '<=',
                today()->addDays(6)
            )
            ->selectRaw(
                'DATE(required_date) as due_date, COUNT(*) as total'
            )
            ->groupBy('due_date')
            ->pluck(
                'total',
                'due_date'
            );

        $dailyPlan = collect(
            range(0, 6)
        )->map(function (int $offset) use ($dailyCountRows): array {
            $date = today()->addDays($offset);
            $key = $date->toDateString();

            return [
                'date' => $key,
                'day_name' => $offset === 0
                    ? 'اليوم'
                    : (
                        $offset === 1
                            ? 'بكرة'
                            : $date->translatedFormat('l')
                    ),
                'date_label' => $date->format('d/m'),
                'count' => (int) ($dailyCountRows[$key] ?? 0),
                'is_today' => $offset === 0,
                'is_tomorrow' => $offset === 1,
            ];
        });

        $query = clone $baseQuery;

        if ($request->filled('q')) {
            $search = trim(
                $request
                    ->string('q')
                    ->toString()
            );

            $query->where(function ($query) use ($search): void {
                $query->where(
                    'order_number',
                    'like',
                    "%{$search}%"
                )
                    ->orWhereHas(
                        'customer',
                        function ($customerQuery) use ($search): void {
                            $customerQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request
                    ->string('status')
                    ->toString()
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'required_date',
                '>=',
                $request->date('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'required_date',
                '<=',
                $request->date('date_to')
            );
        }

        /*
         * Operational statuses always come before completed/cancelled records.
         * Inside the active group the nearest delivery date/time wins.
         */
        $orders = $query
            ->orderByRaw(
                "CASE WHEN status IN ('pending','in_progress','ready') THEN 0 ELSE 1 END"
            )
            ->orderByRaw('required_date IS NULL')
            ->orderBy('required_date')
            ->orderByRaw('required_time IS NULL')
            ->orderBy('required_time')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view(
            'sales.cake-orders.index',
            compact(
                'orders',
                'summary',
                'priorityGroups',
                'dailyPlan'
            )
        );
    }

    public function create(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $canChooseBranch = $user->isAdmin();

        $branches = $canChooseBranch
            ? Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get()
            : collect();

        if ($canChooseBranch) {
            $selectedBranchId = $request->integer(
                'branch_id'
            );

            $branch = $selectedBranchId > 0
                ? $branches->firstWhere(
                    'id',
                    $selectedBranchId
                )
                : null;
        } else {
            $branch = $user->primaryLocation();

            abort_unless(
                $branch
                && $branch->isBranch()
                && $branch->is_active,
                403,
                'يجب ربط المستخدم بفرع رئيسي فعال قبل إنشاء طلب الكيك.'
            );
        }

        $customers = $branch
            ? Customer::query()
                ->where(
                    'location_id',
                    $branch->id
                )
                ->orderBy('name')
                ->get()
            : collect();

        $paymentMethods = collect();
        $paymentAccounts = collect();

        if ($branch) {
            // Branch payment methods can be configured through the legacy pivot
            // or by adding an active payment account for the branch.
            $paymentMethods = $this->availablePaymentMethodsForBranch($branch)
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get();

            $paymentAccounts =
                LocationPaymentAccount::query()
                    ->where(
                        'location_id',
                        $branch->id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereIn(
                        'payment_method_id',
                        $paymentMethods->pluck('id')
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->groupBy(
                        'payment_method_id'
                    );
        }

        return view(
            'sales.cake-orders.create',
            compact(
                'customers',
                'branch',
                'branches',
                'canChooseBranch',
                'paymentMethods',
                'paymentAccounts'
            )
        );
    }

    public function quickStoreCustomer(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $branch = $this->resolveCreationBranch(
            $user,
            $request->input(
                'origin_branch_id'
            )
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('location_id', $branch->id))
                    ->withoutTrashed(),
            ],
        ]);

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'allow_credit' => false,
            'credit_limit' => null,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        NotificationDispatcher::notifyByPermissions(
            new CustomerCreatedNotification($customer),
            ['customers.view', 'customers.update', 'orders.create'],
            (int) $branch->id,
            ['customers.view_all', 'financial.global.view'],
            (int) $user->id,
        );

        return response()->json([
            'ok' => true,
            'message' => 'تمت إضافة العميل واختياره للطلب.',
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) $customer->phone,
            ],
        ], 201);
    }

    public function store(Request $request)
    {
        /*
         * Normalize cash before validation as a server-side safeguard. The UI
         * also selects pay-on-pickup automatically, but the backend must remain
         * correct when JavaScript is unavailable or the request is tampered with.
         */
        $submittedMethod = PaymentMethod::query()
            ->whereKey((int) $request->input('payment_method_id'))
            ->where('is_active', true)
            ->first();

        if (
            $submittedMethod
            && (
                strtolower((string) $submittedMethod->type) === 'cash'
                || strtolower((string) $submittedMethod->code) === 'cash'
            )
        ) {
            $request->merge([
                'payment_arrangement' => 'pay_on_pickup',
                'location_payment_account_id' => null,
                'paid_amount' => null,
                'reference_number' => null,
            ]);
        }

      $validated = $request->validate([
    'origin_branch_id' => [
        Rule::requiredIf(
            fn () => $request->user()->isAdmin()
        ),
        'nullable',
        'integer',
        Rule::exists('locations', 'id')
            ->where(
                fn ($query) =>
                    $query
                        ->where('type', 'branch')
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
            ),
    ],

    'customer_id' => [
        'required',
        'exists:customers,id',
    ],

    'required_date' => [
        'required',
        'date',
    ],

    'required_time' => [
        'nullable',
        'date_format:H:i',
    ],

    'is_urgent' => [
        'nullable',
        'boolean',
    ],

    'urgent_reason' => [
        Rule::requiredIf(
            fn () => $request->boolean('is_urgent')
        ),
        'nullable',
        'string',
        'max:500',
    ],

    'cake_type' => [
        'nullable',
        'string',
        'max:100',
    ],

    'cake_size' => [
        'nullable',
        'string',
        'max:50',
    ],
    'cake_sizes_submitted' => ['sometimes', 'boolean'],
    'cake_sizes' => [
        Rule::requiredIf(
            fn () => $request->boolean('cake_sizes_submitted')
                && in_array($request->input('shape'), ['round', 'slab', 'standard_tiers'], true)
        ),
        'nullable',
        'array',
        'max:8',
    ],
    'cake_sizes.*' => [
        'string',
        'distinct',
        Rule::in(['small', 'medium', 'large', '30x30', '40x30', '60x40', '120x30', '120x80', 'standard']),
    ],

    'total_price' => [
        'required',
        'numeric',
        'min:0',
    ],

    'payment_arrangement' => [
        'required',
        'in:pay_now,deposit,partial_payment,pay_on_pickup,pending_verification',
    ],

    'payment_method_id' => [
        'required',
        'integer',
        'exists:payment_methods,id',
    ],

    'location_payment_account_id' => [
        'nullable',
        'integer',
        'exists:location_payment_accounts,id',
    ],

    'paid_amount' => [
        'required_if:payment_arrangement,pay_now,deposit,partial_payment',
        'nullable',
        'numeric',
        'min:0',
        'lte:total_price',
    ],

    'reference_number' => [
        'required_if:payment_arrangement,pending_verification',
        'nullable',
        'string',
        'max:100',
    ],

    'payment_proof' => [
        'required_if:payment_arrangement,pending_verification',
        'nullable',
        'file',
        'mimes:jpg,jpeg,png,webp',
        'max:10240',
    ],

    'image_cover_type' => [
        'nullable',
        'string',
        'in:none,edible_sugar,removable_cardboard',
    ],

    'discount_type' => [
        'nullable',
        'string',
        'in:none,percentage,fixed',
    ],

    'discount_value' => [
        'nullable',
        'numeric',
        'min:0',
    ],

    'reference_image' => [
    'nullable',
    'image',
    'mimes:jpg,jpeg,png,webp',
    'max:10240',
],

'print_image' => [
    'required_if:image_cover_type,edible_sugar',
    'nullable',
    'image',
    'mimes:jpg,jpeg,png,webp',
    'max:10240',
],
]);

        $validated = $this->enforceDeliveryTiming(
            $request,
            $validated
        );
        $validated = $this->applySelectedCakeSizes($request, $validated);


        /** @var User $user */
        $user = $request->user();

        $branch = $this->resolveCreationBranch(
            $user,
            $validated['origin_branch_id']
                ?? null
        );

        $customerIsAvailable =
            Customer::query()
                ->where(
                    'location_id',
                    $branch->id
                )
                ->whereKey(
                    (int) $validated['customer_id']
                )
                ->exists();

        if (! $customerIsAvailable) {
            return back()
                ->withErrors([
                    'customer_id' =>
                        'العميل المحدد لا يتبع الفرع صاحب الطلب.',
                ])
                ->withInput();
        }

        $paymentMethod = $this->availablePaymentMethodsForBranch($branch)
            ->whereKey((int) $request->payment_method_id)
            ->first();

        if (! $paymentMethod) {
            return back()
                ->withErrors(['payment_method_id' => 'طريقة الدفع المحددة غير مفعّلة في هذا الفرع.'])
                ->withInput();
        }

        $isCash = strtolower((string) $paymentMethod->type) === 'cash'
            || strtolower((string) $paymentMethod->code) === 'cash';

        if ($isCash) {
            /*
             * Cash is collected when the customer receives the cake. Do not
             * create a paid transaction or request proof/account information.
             */
            $request->merge([
                'payment_arrangement' => 'pay_on_pickup',
                'location_payment_account_id' => null,
                'paid_amount' => null,
                'reference_number' => null,
            ]);
        } elseif ($request->payment_arrangement !== 'pay_on_pickup') {
            $activeAccounts = LocationPaymentAccount::query()
                ->where('location_id', $branch->id)
                ->where('payment_method_id', $paymentMethod->id)
                ->where('is_active', true);

            if ($activeAccounts->exists()) {
                $paymentAccount = (clone $activeAccounts)
                    ->find((int) $request->location_payment_account_id);

                if (! $paymentAccount) {
                    return back()
                        ->withErrors(['location_payment_account_id' => 'اختر حساب الدفع الصحيح لهذا الفرع.'])
                        ->withInput();
                }
            } elseif ($request->filled('location_payment_account_id')) {
                return back()
                    ->withErrors(['location_payment_account_id' => 'حساب الدفع المحدد لا يتبع طريقة الدفع المختارة.'])
                    ->withInput();
            }
        }

        // Calculate discount
        $totalPrice    = (float) $request->total_price;
        $discountType  = $request->input('discount_type', 'none');
        $discountValue = (float) $request->input('discount_value', 0);
        $discountAmount = 0;

        if ($discountType === 'percentage') {
            $discountAmount = $totalPrice * $discountValue / 100;
        } elseif ($discountType === 'fixed') {
            $discountAmount = min($discountValue, $totalPrice);
        }

        $netPrice = max(0, $totalPrice - $discountAmount);

        $order = $this->cakeOrderService->createOrder(
    array_merge($request->all(), $validated, [
        'origin_branch_id' => $branch->id,
        'discount_type'   => $discountType,
        'discount_value'  => $discountValue,
        'discount_amount' => $discountAmount,
        'net_price'       => $netPrice,
        'payment_proof'   => $request->file('payment_proof'),
    ]),
    Auth::user()
);

        // Handle image attachments
     // Store the optional cake reference image
if ($request->hasFile('reference_image')) {
    $referenceImage = $request->file('reference_image');

    $referencePath = $referenceImage->store(
        'cake-attachments/reference-images',
        'public'
    );

    $order->attachments()->create([
        'attachment_type' => 'reference_image',
        'file_path'       => $referencePath,
        'original_name'   => $referenceImage->getClientOriginalName(),
        'uploaded_by'     => Auth::id(),
    ]);
}

// Store the sugar print image
if (
    $request->image_cover_type === 'edible_sugar' &&
    $request->hasFile('print_image')
) {
    $printImage = $request->file('print_image');

    $printImagePath = $printImage->store(
        'cake-attachments/print-images',
        'public'
    );

    $order->attachments()->create([
        'attachment_type' => 'customer_design',
        'file_path'       => $printImagePath,
        'original_name'   => $printImage->getClientOriginalName(),
        'uploaded_by'     => Auth::id(),
    ]);
}

        /*
         * The approval mode is controlled from Branding Settings.
         * Auto mode records a single draft -> in_progress transition.
         * Manual mode records draft -> pending and waits for review.
         */
        $this->transitionService->submitCreatedOrder(
            $order,
            Auth::user()
        );

        $order->refresh();

        $autoApproved =
            $order->status === CakeOrderStatus::InProgress;

        return redirect()
            ->route('cake-orders.show', $order)
            ->with(
                'success',
                $autoApproved
                    ? 'تم إنشاء طلب الكيك واعتماده تلقائيًا وبدأ التنفيذ.'
                    : 'تم إنشاء طلب الكيك ووضعه قيد المراجعة.'
            );
    }

    public function show(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $cakeOrder->load([
            'customer', 'originBranch', 'factory', 'creator',
            'attachments', 'comments.user', 'statusHistories.changedBy',
            'receivingIssues', 'invoice', 'payments.paymentMethod', 'payments.receivedBy',
            'branchShipment.driver.employee', 'branchShipment.receivingInvoice',
        ]);

        $allowedTransitions = collect(
            $this->transitionService
                ->allowedTransitionsForUser($cakeOrder, Auth::user())
        )
            ->reject(
                fn (string $status): bool =>
                    $status === CakeOrderStatus::Completed->value
            )
            ->values()
            ->all();

        return view('sales.cake-orders.show', compact(
            'cakeOrder',
            'allowedTransitions'
        ));
    }

    public function edit(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('update', $cakeOrder);
        if (! in_array(
            $cakeOrder->status,
            [
                CakeOrderStatus::Draft,
                CakeOrderStatus::Pending,
                CakeOrderStatus::InProgress,
                CakeOrderStatus::Ready,
            ],
            true
        )) {
            return back()->with(
                'error',
                'يمكن تعديل الطلب ما دام قيد المراجعة فقط.'
            );
        }
        return view('sales.cake-orders.edit', compact('cakeOrder'));
    }

    public function update(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('update', $cakeOrder);
        if (! in_array(
            $cakeOrder->status,
            [
                CakeOrderStatus::Draft,
                CakeOrderStatus::Pending,
                CakeOrderStatus::InProgress,
                CakeOrderStatus::Ready,
            ],
            true
        )) {
            return back()->with(
                'error',
                'يمكن تعديل الطلب ما دام قيد المراجعة فقط.'
            );
        }

        $validated = $request->validate([
            'edit_type' => ['required', Rule::in(['normal', 'exceptional'])],
            'edit_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'required_date' => ['required', 'date'],
            'required_time' => ['nullable', 'date_format:H:i'],
            'is_urgent' => ['nullable', 'boolean'],
            'urgent_reason' => [
                Rule::requiredIf(
                    fn () =>
                        $request->boolean('is_urgent')
                ),
                'nullable',
                'string',
                'max:500',
            ],
            'cake_type' => ['nullable', 'string', 'max:100'],
            'cake_size' => ['nullable', 'string', 'max:50'],
            'cake_sizes_submitted' => ['sometimes', 'boolean'],
            'cake_sizes' => [
                Rule::requiredIf(
                    fn () => $request->boolean('cake_sizes_submitted')
                        && in_array($request->input('shape'), ['round', 'slab', 'standard_tiers'], true)
                ),
                'nullable',
                'array',
                'max:8',
            ],
            'cake_sizes.*' => [
                'string',
                'distinct',
                Rule::in(['small', 'medium', 'large', '30x30', '40x30', '60x40', '120x30', '120x80', 'standard']),
            ],
            'cake_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'persons_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'flavor' => ['nullable', 'string', 'max:255'],
            'filling' => ['nullable', 'string', 'max:255'],
            'shape' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'cake_text' => ['nullable', 'string', 'max:500'],
            'theme' => ['nullable', 'string', 'max:255'],
            'special_instructions' => ['nullable', 'string', 'max:5000'],
            'total_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $validated = $this->enforceDeliveryTiming(
            $request,
            $validated
        );
        $validated = $this->applySelectedCakeSizes($request, $validated);

        // Cross-field rules cannot be delegated to the browser: it can be bypassed.
        if ($request->boolean('cake_sizes_submitted') || $request->exists('cake_sizes')) {
            $shape = $validated['shape'] ?? null;
            $allowed = match ($shape) {
                'round', 'standard_tiers' => ['small', 'medium', 'large'],
                'slab' => ['30x30', '40x30', '60x40', '120x30', '120x80'],
                'wedding_tiers' => ['standard'],
                default => null,
            };
            if ($allowed !== null && array_diff($validated['cake_sizes'] ?? [], $allowed)) {
                throw ValidationException::withMessages([
                    'cake_sizes' => 'الأحجام المختارة لا تتوافق مع شكل الكيك.',
                ]);
            }
        }

        $editType = $validated['edit_type'];
        $editReason = trim($validated['edit_reason']);
        unset($validated['edit_type'], $validated['edit_reason']);

        // Price, discount and net amount are linked. Do not silently leave an old
        // net price after changing the total. Paid/invoiced orders are guarded below.
        DB::transaction(function () use ($cakeOrder, $validated, $request, $editType, $editReason): void {
            $locked = SpecialCakeOrder::query()->lockForUpdate()->findOrFail($cakeOrder->id);
            $this->authorize('update', $locked);

            if (! in_array($locked->status, [CakeOrderStatus::Draft, CakeOrderStatus::Pending, CakeOrderStatus::InProgress, CakeOrderStatus::Ready], true)) {
                throw ValidationException::withMessages([
                    'status' => 'تغيرت حالة الطلب أثناء التعديل؛ لا يمكن الحفظ بعد بدء الإنتاج.',
                ]);
            }

            if (in_array($locked->status, [CakeOrderStatus::InProgress, CakeOrderStatus::Ready], true)
                && $editType !== 'exceptional') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'edit_type' => 'بعد بدء التنفيذ يجب اختيار تعديل استثنائي وذكر سببه.',
                ]);
            }

            $old = $locked->only(array_unique([...array_keys($validated), 'discount_amount', 'net_price']));
            $priceChanged = (int) round((float) $locked->total_price * 100) !== (int) round((float) $validated['total_price'] * 100);

            // Price corrections after payment/invoicing require the accounting adjustment flow.
            if ($priceChanged && ($locked->payments()->exists() || $locked->invoice()->exists())) {
                throw ValidationException::withMessages([
                    'total_price' => 'لا يمكن تغيير السعر بعد تسجيل دفعة أو إصدار فاتورة. استخدم إجراء التسوية المالية.',
                ]);
            }

            if ($priceChanged) {
                $discountType = $locked->discount_type instanceof \BackedEnum
                    ? $locked->discount_type->value : (string) $locked->discount_type;
                $discountValue = (float) ($locked->discount_value ?? 0);
                $total = (float) $validated['total_price'];

                $discount = match ($discountType) {
                    'percentage', 'percent' => round($total * $discountValue / 100, 2),
                    'fixed', 'amount' => $discountValue,
                    default => 0,
                };
                if ($discount < 0 || $discount > $total) {
                    throw ValidationException::withMessages([
                        'total_price' => 'السعر الجديد لا يغطي الخصم المسجل. راجع الحسابات قبل الحفظ.',
                    ]);
                }
                $validated['discount_amount'] = $discount;
                $validated['net_price'] = round($total - $discount, 2);
            }

            $locked->update($validated);
            $changes = $locked->getChanges();
            unset($changes['updated_at']);
            if ($changes) {
                ActivityLogger::log(
                    userId: $request->user()->id,
                    action: 'cake_order.updated',
                    module: 'special_cake_orders',
                    recordType: 'special_cake_orders',
                    recordId: $locked->id,
                    oldValues: array_intersect_key($old, $changes),
                    newValues: array_intersect_key($locked->only(array_unique([...array_keys($validated), 'discount_amount', 'net_price'])), $changes),
                    metadata: ['order_number' => $locked->order_number, 'edit_type' => $editType, 'edit_reason' => $editReason],
                );
            }
        });

        return redirect()->route('cake-orders.show', $cakeOrder)->with('success', 'تم تحديث الطلب.');
    }

    /**
     * New UI sends an array of sizes; the legacy singular column remains
     * populated with the first selected size for older consumers.
     * Requests using only cake_size continue to work unchanged.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function applySelectedCakeSizes(Request $request, array $validated): array
    {
        if (! $request->boolean('cake_sizes_submitted') && ! $request->exists('cake_sizes')) {
            return $validated;
        }

        $sizes = array_values(array_unique($validated['cake_sizes'] ?? []));

        // Wedding tiers have a fixed legacy "standard" value without a size picker.
        if (($validated['shape'] ?? $request->input('shape')) === 'wedding_tiers') {
            $sizes = ['standard'];
        }

        $validated['cake_sizes'] = $sizes ?: null;
        $validated['cake_size'] = $sizes[0] ?? null;

        return $validated;
    }

    public function destroy(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('delete', $cakeOrder);

        if (! in_array($cakeOrder->status, [CakeOrderStatus::Draft, CakeOrderStatus::Cancelled], true)) {
            return back()->with('error', 'يمكن حذف الطلبات الملغاة أو المسودات فقط.');
        }

        $deleted = DB::transaction(function () use ($cakeOrder): bool {
            $locked = SpecialCakeOrder::query()->lockForUpdate()->findOrFail($cakeOrder->id);
            $this->authorize('delete', $locked);

            if (! in_array($locked->status, [CakeOrderStatus::Draft, CakeOrderStatus::Cancelled], true)) {
                return false;
            }

            if ($locked->payments()->exists() || $locked->invoice()->exists()
                || $locked->branchShipment()->exists() || $locked->receivingIssues()->exists()) {
                return false;
            }

            $locked->delete();
            return true;
        });

        if (! $deleted) {
            return back()->with('error', 'لا يمكن حذف طلب مرتبط بمدفوعات أو فاتورة أو شحنة أو محضر استلام.');
        }

        return redirect()
            ->route('cake-orders.index')
            ->with('success', 'تم حذف الطلب.');
    }

    public function transition(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('transition', $cakeOrder);

        $validated = $request->validate([
            'to_status' => [
                'required',
                Rule::enum(CakeOrderStatus::class),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (
            $validated['to_status'] ===
            CakeOrderStatus::Completed->value
        ) {
            return back()->with(
                'error',
                'إكمال الطلب يتم فقط بعد استلام الفرع وإصدار فاتورة الاستلام.'
            );
        }

        $this->transitionService->transition(
            $cakeOrder,
            $validated['to_status'],
            Auth::user(),
            $validated['note'] ?? null
        );

        return back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
    }

    public function addComment(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        CakeOrderComment::create([
            'special_cake_order_id' => $cakeOrder->id,
            'user_id'               => Auth::id(),
            'comment'               => $validated['comment'],
            'is_internal'           => true,
        ]);

        $actor = $request->user();

        NotificationDispatcher::notifyByPermissions(
            new SpecialCakeOrderActivityNotification(
                $cakeOrder->fresh(),
                'comment',
                $actor->display_name,
                \Illuminate\Support\Str::limit($validated['comment'], 180)
            ),
            ['cake_orders.view', 'cake_orders.manage'],
            array_filter([
                $cakeOrder->origin_branch_id,
                $cakeOrder->factory_location_id,
            ]),
            ['cake_orders.view_all'],
            $actor->isAdmin()
                ? null
                : (int) $actor->id,
        );

        return back()->with('success', 'تم إضافة الملاحظة.');
    }

    /**
     * Normal cake bookings start tomorrow at the earliest.
     * Same-day delivery is only allowed when explicitly marked urgent,
     * requires a reason and a future delivery time.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function enforceDeliveryTiming(
        Request $request,
        array $validated
    ): array {
        $isUrgent =
            $request->boolean('is_urgent');

        $deliveryDate =
            \Carbon\Carbon::parse(
                $validated['required_date']
            )->startOfDay();

        if ($deliveryDate->lt(today())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'required_date' =>
                    'لا يمكن اختيار تاريخ تسليم سابق لليوم.',
            ]);
        }

        if (
            ! $isUrgent
            && $deliveryDate->isSameDay(today())
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'required_date' =>
                    'الطلب العادي يجب أن يكون موعد تسليمه من الغد فما بعد. لتسليم اليوم فعّل خيار طلب طارئ.',
            ]);
        }

        if (
            $isUrgent
            && $deliveryDate->isSameDay(today())
        ) {
            if (empty($validated['required_time'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'required_time' =>
                        'الطلب الطارئ لنفس اليوم يحتاج وقت تسليم محدد.',
                ]);
            }

            $dueAt =
                \Carbon\Carbon::parse(
                    $deliveryDate->toDateString()
                    . ' '
                    . $validated['required_time']
                );

            if ($dueAt->lte(now())) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'required_time' =>
                        'وقت تسليم الطلب الطارئ يجب أن يكون لاحقًا للوقت الحالي.',
                ]);
            }
        }

        $validated['is_urgent'] =
            $isUrgent;

        $validated['urgent_reason'] =
            $isUrgent
                ? trim(
                    (string) (
                        $validated['urgent_reason']
                        ?? ''
                    )
                )
                : null;

        $request->merge([
            'is_urgent' =>
                $isUrgent ? 1 : 0,
            'urgent_reason' =>
                $validated['urgent_reason'],
        ]);

        return $validated;
    }

    public function addAttachment(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $validated = $request->validate([
            'attachment_type' => ['required', 'in:reference_image,customer_design,final_cake_image,other'],
            'file'            => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('cake-attachments', 'public');

        $cakeOrder->attachments()->create([
            'attachment_type' => $validated['attachment_type'],
            'file_path'       => $path,
            'original_name'   => $uploadedFile->getClientOriginalName(),
            'uploaded_by'     => Auth::id(),
        ]);

        $actor = $request->user();

        NotificationDispatcher::notifyByPermissions(
            new SpecialCakeOrderActivityNotification(
                $cakeOrder->fresh(),
                'attachment',
                $actor->display_name,
                'اسم الملف: ' . $uploadedFile->getClientOriginalName()
            ),
            ['cake_orders.view', 'cake_orders.manage'],
            array_filter([
                $cakeOrder->origin_branch_id,
                $cakeOrder->factory_location_id,
            ]),
            ['cake_orders.view_all'],
            $actor->isAdmin()
                ? null
                : (int) $actor->id,
        );

        return back()->with('success', 'تم رفع المرفق بنجاح.');
    }
    /**
     * Read active branch payment options from both supported configurations.
     * An active branch payment account must be usable even if no legacy
     * location_payment_methods row was created for it.
     */
    private function availablePaymentMethodsForBranch(Location $branch): \Illuminate\Database\Eloquent\Builder
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->where(function ($query) use ($branch): void {
                $query->whereHas('locationPaymentMethods', function ($association) use ($branch): void {
                    $association->where('location_id', $branch->id)
                        ->where('is_active', true);
                })->orWhereIn('payment_methods.id', LocationPaymentAccount::query()
                    ->select('payment_method_id')
                    ->where('location_id', $branch->id)
                    ->where('is_active', true));
            });
    }

    private function resolveCreationBranch(
        User $user,
        mixed $requestedBranchId = null
    ): Location {
        if ($user->isAdmin()) {
            $branch = Location::query()
                ->branches()
                ->active()
                ->find(
                    (int) $requestedBranchId
                );

            if (! $branch) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'origin_branch_id' =>
                        'اختر الفرع صاحب طلب الكيك قبل المتابعة.',
                ]);
            }

            return $branch;
        }

        $branch = $user->primaryLocation();

        if (
            ! $branch
            || ! $branch->isBranch()
            || ! $branch->is_active
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'origin_branch_id' =>
                    'يجب ربط المستخدم بفرع رئيسي فعال قبل إنشاء طلب الكيك.',
            ]);
        }

        return $branch;
    }

}

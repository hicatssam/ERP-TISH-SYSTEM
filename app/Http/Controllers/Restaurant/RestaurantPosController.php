<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Requests\Restaurant\StoreRestaurantPosOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Restaurant\RestaurantPosCatalogService;
use App\Services\Restaurant\RestaurantTableAccountService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\ModuleService;
use App\Services\Payments\PaymentCheckoutSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestaurantPosController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly RestaurantOrderService $restaurantOrders,
        private readonly RestaurantPosCatalogService $catalog,
    ) {
    }

    public function diningGroupAccount(Request $request, \App\Models\RestaurantDiningGroup $restaurantDiningGroup, \App\Services\Restaurant\RestaurantDiningGroupService $groups)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );
        abort_unless((int) $restaurantDiningGroup->location_id === (int) $location->id, 403);

        return view('restaurant.tables.group-account', [
            'location' => $location,
            'group' => $restaurantDiningGroup,
            'account' => $groups->statement($restaurantDiningGroup),
        ]);
    }

    public function tableAccount(Request $request, \App\Models\RestaurantTable $restaurantTable, RestaurantTableAccountService $accounts)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );
        abort_unless((int) $restaurantTable->location_id === (int) $location->id, 403);
        $session = $restaurantTable->activeSession()->first();
        abort_unless($session, 404, 'لا توجد جلسة مفتوحة للطاولة.');

        return view('restaurant.tables.account', [
            'location' => $location,
            'table' => $restaurantTable,
            'session' => $session,
            'account' => $accounts->summary($session),
        ]);
    }

    public function printInvoice(Request $request, Order $order, InvoiceController $invoices)
    {
        $this->authorizePosPrint($request, $order);

        return $invoices->printForAuthorizedPos($order->invoice()->firstOrFail());
    }

    public function printKitchen(Request $request, Order $order)
    {
        $this->authorizePosPrint($request, $order);
        $order->load(['location', 'restaurantTable', 'kitchenTickets.station', 'kitchenTickets.items']);
        abort_unless($order->kitchenTickets->isNotEmpty(), 404);

        return view('restaurant.pos.kitchen-print', compact('order'));
    }

    private function authorizePosPrint(Request $request, Order $order): void
    {
        $location = $this->context->resolveLocation($request->user(), (int) $order->location_id);
        abort_unless($order->isRestaurantOrder()
            && (int) $order->location_id === (int) $location->id
            && ((int) $order->created_by === (int) $request->user()->id
                || $request->user()->can('invoices.view')), 403);
    }

    /**
     * Explicit cancellation with a documented reason and the existing
     * accounting/inventory/kitchen compensation service.
     */
    public function cancelDraft(
        Request $request, Order $order, \App\Services\Orders\OrderService $orders
    ): \Illuminate\Http\JsonResponse {
        $location = $this->context->resolveLocation($request->user(), (int) $order->location_id);
        abort_unless($order->isRestaurantOrder()
            && (int) $order->location_id === (int) $location->id, 403);
        $this->authorize('cancel', $order);
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        if ($order->statusValue() !== 'draft') {
            throw ValidationException::withMessages([
                'order' => 'إلغاء الطلبات المرسلة للمطبخ يحتاج إجراءات الإلغاء المخصصة لها.',
            ]);
        }
        $cancelled = $orders->cancelOrder($order, $data['cancellation_reason'], $request->user());

        return response()->json([
            'ok' => true,
            'order_id' => $cancelled->id,
            'status' => $cancelled->statusValue(),
        ]);
    }

    /**
     * POS stage B: update only unpaid, unsent drafts. Keep the row lock
     * throughout the permission/status check and nested order update.
     */
    public function updateDraftItems(
        Request $request, Order $order, \App\Services\Orders\OrderService $orders
    ): \Illuminate\Http\JsonResponse {
        $location = $this->context->resolveLocation($request->user(), (int) $order->location_id);
        abort_unless($order->isRestaurantOrder()
            && (int) $order->location_id === (int) $location->id
            && ((int) $order->created_by === (int) $request->user()->id
                || $request->user()->can('orders.update')), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct',
                \Illuminate\Validation\Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'items.*.kitchen_notes' => ['nullable', 'string', 'max:500'],
            'remove_item_ids' => ['nullable', 'array', 'max:100'],
            'remove_item_ids.*' => ['required', 'integer', 'distinct',
                \Illuminate\Validation\Rule::exists('order_items', 'id')->where('order_id', $order->id)],
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $orders, $data, $request): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->statusValue() !== 'draft'
                || $locked->payments()->exists()
                || $locked->kitchenTickets()->exists()
                || $locked->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'الطلب مرتبط بدفع أو مطبخ أو فاتورة، ولا يمكن تعديله كمسودة.',
                ]);
            }
            $existing = $locked->items()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $submitted = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $removed = collect($data['remove_item_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (array_intersect($submitted, $removed)
                || $existing !== collect(array_merge($submitted, $removed))->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'items' => 'عناصر التعديل أو الإلغاء لا تتطابق مع المسودة المحفوظة.',
                ]);
            }

            if ($removed) {
                $lines = $locked->items()->whereIn('id', $removed)->get();
                foreach ($lines as $line) {
                    $line->modifiers()->delete();
                    $line->delete();
                }
                \App\Services\ActivityLogger::log(
                    userId: $request->user()->id,
                    action: 'restaurant.pos.draft.items_cancelled',
                    module: 'restaurant',
                    recordType: 'orders',
                    recordId: $locked->id,
                    metadata: ['location_id' => $locked->location_id, 'removed_item_ids' => $removed],
                );
            }

            return $orders->updateOrder($locked, [
                'customer_id' => $locked->customer_id,
                'payment_arrangement' => $locked->payment_arrangement?->value ?? $locked->payment_arrangement,
                'notes' => $locked->notes,
                'items' => $data['items'],
            ], $request->user());
        }, 3);

        return response()->json([
            'ok' => true,
            'order_id' => $updated->id,
            'total' => (float) $updated->total_amount,
            'message' => 'تم تحديث المسودة دون إنشاء بيع أو دفعة جديدة.',
        ]);
    }

    /**
     * Draft previews for the current POS branch, without leaving the cashier.
     * This is read-only; a persisted draft must not be treated as a fresh cart
     * or posted again with a new request token.
     */
    public function draftOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->where('status', 'draft')
            ->with(['customer:id,name', 'items:id,order_id,product_name,quantity,line_total,kitchen_notes', 'payments:id,order_id', 'kitchenTickets:id,order_id', 'invoice:id,order_id']);

        if (! empty($data['q'])) {
            $search = trim($data['q']);
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->latest()->paginate(20);

        return response()->json([
            'orders' => $orders->getCollection()->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->order_number,
                'customer' => $order->customer?->name ?? 'عميل نقدي',
                'total' => (float) $order->total_amount,
                'status' => 'draft',
                'cancelable' => ($request->user()->can('cancel', $order) && $order->statusValue() === 'draft'),
                'editable' => ($order->payments->isEmpty()
                    && $order->kitchenTickets->isEmpty()
                    && $order->invoice === null
                    && ((int) $order->created_by === (int) $request->user()->id
                        || $request->user()->can('orders.update'))),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'line_total' => (float) $item->line_total,
                    'notes' => $item->kitchen_notes ?? '',
                ])->all(),
                'url' => $request->user()->can('orders.view')
                    ? route('orders.show', $order)
                    : null,
            ])->all(),
            'has_more' => $orders->hasMorePages(),
        ]);
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        $firstPage = $this->catalog->page($location->id);
        $facets = $this->catalog->facets($location->id);

        $customers = Customer::query()
            ->availableAt($location->id)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'phone',
            ]);

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->where(function ($query) use ($location): void {
                $query->whereDoesntHave('locationPaymentMethods')
                    ->orWhereHas('locationPaymentMethods', fn ($assignment) => $assignment
                        ->where('location_id', $location->id)
                        ->where('is_active', true));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $salesChannels = SalesChannel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tables = RestaurantTable::query()
            ->forLocation($location->id)
            ->active()
            ->with([
                'area:id,name',
                'activeSession.diningGroup',
            ])
            ->orderBy('area_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        $recentOrders = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->with([
                'restaurantTable.area',
                'customer',
                'invoice',
            ])
            ->latest()
            ->limit(8)
            ->get();

        return view(
            'restaurant.pos.index',
            [
                'location' => $location,
                'locations' => $locations,
                'productPayload' => $this->catalog->payload($firstPage->getCollection()),
                'catalogTotal' => $firstPage->total(),
                'catalogHasMore' => $firstPage->hasMorePages(),
                'catalogFacets' => $facets,
                'customers' => $customers,
                'paymentMethods' => $paymentMethods,
                'salesChannels' => $salesChannels,
                'channelDiscountRules' => $salesChannels->mapWithKeys(fn ($channel) => [
                    $channel->id => [
                        'discount_type' => $channel->discount_type?->value,
                        'discount_value' => (float) $channel->discount_value,
                    ],
                ]),
                'tables' => $tables,
                'recentOrders' => $recentOrders,
                'serviceTypes' => RestaurantServiceType::cases(),
                'paymentArrangements' => app(PaymentCheckoutSettings::class)->posOptions(),
            ]
        );
    }

    /**
     * Create a walk-in customer while keeping the active POS draft intact.
     * The POS module and customers.create permission are enforced by the route.
     */
    public function quickStoreCustomer(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $location = $this->context->resolveLocation(
            $user,
            $request->integer('location_id') ?: null
        );
        $canManageCentral = $user->isAdmin()
            || $user->can('customers.view_all')
            || $user->can('financial.global.view');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('location_id', $location->id)),
            ],
            'customer_type' => ['sometimes', \Illuminate\Validation\Rule::in([
                Customer::TYPE_INDIVIDUAL,
                Customer::TYPE_INSTITUTION,
                Customer::TYPE_COMPANY,
                Customer::TYPE_GOVERNMENT,
            ])],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'scope' => ['sometimes', \Illuminate\Validation\Rule::in([
                Customer::SCOPE_BRANCH, Customer::SCOPE_SELECTED, Customer::SCOPE_GLOBAL,
            ])],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => [
                'integer', 'distinct',
                \Illuminate\Validation\Rule::exists('locations', 'id')->where('is_active', true),
            ],
            'allow_credit' => ['nullable', 'boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['sometimes', \Illuminate\Validation\Rule::in(['immediate', 'weekly', 'monthly'])],
            'payment_terms_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ]);

        $type = $validated['customer_type'] ?? Customer::TYPE_INDIVIDUAL;
        $scope = $validated['scope'] ?? Customer::SCOPE_BRANCH;
        $creditRequested = (bool) ($validated['allow_credit'] ?? false);

        // POS cashiers can create complete branch customer profiles but may
        // not grant cross-branch access or credit without central permissions.
        if (! $canManageCentral && ($scope !== Customer::SCOPE_BRANCH
            || $creditRequested || ! empty($validated['location_ids'])
            || array_key_exists('credit_limit', $validated)
            || (($validated['billing_cycle'] ?? 'immediate') !== 'immediate')
            || ((int) ($validated['payment_terms_days'] ?? 0) !== 0))) {
            throw ValidationException::withMessages([
                'scope' => 'إتاحة العميل لفروع أخرى أو تفعيل البيع الآجل تتطلب صلاحية إدارة العملاء المركزية.',
            ]);
        }

        if ($scope !== Customer::SCOPE_BRANCH && $type === Customer::TYPE_INDIVIDUAL) {
            throw ValidationException::withMessages([
                'customer_type' => 'العميل المتاح لأكثر من فرع يجب أن يكون مؤسسة أو شركة أو جهة.',
            ]);
        }
        if ($scope === Customer::SCOPE_SELECTED && empty($validated['location_ids'])) {
            throw ValidationException::withMessages([
                'location_ids' => 'اختر فرعًا واحدًا على الأقل للعميل متعدد الفروع.',
            ]);
        }

        $customer = \Illuminate\Support\Facades\DB::transaction(function () use (
            $validated, $location, $type, $scope, $creditRequested, $canManageCentral
        ): Customer {
            $credit = $canManageCentral && $creditRequested;
            $customer = Customer::query()->create([
                'location_id' => $location->id,
                'customer_type' => $type,
                'scope' => $scope,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'secondary_phone' => $validated['secondary_phone'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'allow_credit' => $credit,
                'credit_limit' => $credit ? ($validated['credit_limit'] ?? null) : null,
                'billing_cycle' => $credit ? ($validated['billing_cycle'] ?? 'immediate') : 'immediate',
                'payment_terms_days' => $credit ? (int) ($validated['payment_terms_days'] ?? 0) : 0,
            ]);

            if ($scope === Customer::SCOPE_SELECTED) {
                $ids = array_unique(array_merge(
                    [$location->id], array_map('intval', $validated['location_ids'])
                ));
                $availableIds = \App\Models\Location::query()->branches()->active()
                    ->whereIn('id', $ids)->pluck('id')->all();
                if (count($availableIds) !== count($ids)) {
                    throw ValidationException::withMessages([
                        'location_ids' => 'أحد الفروع المحددة غير متاح.',
                    ]);
                }
                $customer->locations()->sync(collect($availableIds)->mapWithKeys(
                    fn ($id) => [(int) $id => ['is_active' => true]]
                )->all());
            }

            return $customer;
        });

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'customer_type' => $customer->customer_type,
                'scope' => $customer->scope,
            ],
        ], 201);
    }

    /**
     * Search older saved receipts without needing access to the orders page.
     * The print endpoint independently enforces these branch/user rules.
     */
    public function receiptSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $location = $this->context->resolveLocation(
            $request->user(), $request->integer('location_id') ?: null
        );
        $query = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->whereHas('invoice');
        if (! $request->user()->can('invoices.view')) {
            $query->where('created_by', $request->user()->id);
        }
        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $query->where(function ($query) use ($term): void {
                $query->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice
                        ->where('invoice_number', 'like', "%{$term}%"));
            });
        }
        $orders = $query->with('invoice:id,order_id,invoice_number')
            ->latest('id')->limit(20)->get();

        return response()->json([
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->order_number,
                'invoice_number' => $order->invoice?->invoice_number,
                'print_url' => route('restaurant.pos.orders.invoice-print', $order),
            ])->all(),
        ]);
    }

    /**
     * Read-only current menu availability check for items already in the cart.
     * A client-side result does not replace final transactional stock checks.
     */
    public function cartAvailability(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );
        $ids = array_map('intval', $data['ids']);
        $available = $this->catalog->byIds($location->id, $ids)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'available_ids' => $available,
            'unavailable_ids' => array_values(array_diff($ids, $available)),
        ]);
    }

    public function catalog(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        if (isset($data['ids'])) {
            return response()->json([
                'products' => $this->catalog->payload(
                    $this->catalog->byIds($location->id, $data['ids'])
                ),
            ]);
        }

        $page = $this->catalog->page(
            $location->id,
            (int) ($data['page'] ?? 1),
            $data['q'] ?? null,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['brand_id']) ? (int) $data['brand_id'] : null,
        );

        return response()->json([
            'products' => $this->catalog->payload($page->getCollection()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function qrOrders(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $location = $this->context->resolveLocation(
            $request->user(), $request->integer('location_id') ?: null
        );
        $orders = Order::query()
            ->where('location_id', $location->id)
            ->where('order_source', 'customer_menu')
            ->with(['customer', 'restaurantTable', 'items'])
            ->when(trim($data['q'] ?? '') !== '', fn ($query) => $query
                ->where(fn ($search) => $search
                    ->where('order_number', 'like', '%'.trim($data['q']).'%')
                    ->orWhere('guest_name', 'like', '%'.trim($data['q']).'%')
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', '%'.trim($data['q']).'%'))))
            ->latest()
            ->paginate(20, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'orders' => $orders->getCollection()->map(fn (Order $order) => [
                'number' => $order->order_number,
                'status' => $order->status?->label() ?? $order->statusValue(),
                'service' => $order->restaurant_service_type?->label() ?? 'طلب QR',
                'customer' => $order->customer?->name ?: $order->guest_name ?: 'عميل نقدي',
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                ])->values(),
                'url' => $request->user()->can('orders.view')
                    ? route('orders.show', $order)
                    : null,
            ])->values(),
            'has_more' => $orders->hasMorePages(),
        ]);
    }

    public function store(
        StoreRestaurantPosOrderRequest $request
    ) {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validated();

        $action = $data['pos_action'] ?? 'bill_payment';
        if ($action === 'kot_print') {
            if (! app(ModuleService::class)->isEnabled('kitchen')) {
                throw ValidationException::withMessages([
                    'kitchen' => 'وحدة المطبخ غير مفعلة. فعّلها قبل إرسال الطلب.',
                ]);
            }
            $data['payment_arrangement'] = 'pay_on_pickup';
            unset($data['payment_method_id'], $data['paid_amount'], $data['reference_number'],
                $data['payment_proof'], $data['payment_received_confirmed']);
        }

        // Kitchen-only printing is an internal unpaid hand-off, not a payment selection.
        if ($action !== 'kot_print'
            && ! app(PaymentCheckoutSettings::class)->permitsPos($data['payment_arrangement'])) {
            throw ValidationException::withMessages([
                'payment_arrangement' => 'ترتيب الدفع المحدد معطل من إعدادات الدفع والطلبات.',
            ]);
        }

        if ($action === 'bill_print' && (
            $data['payment_arrangement'] === 'pending_verification'
            || (! empty($data['payment_method_id'])
                && PaymentMethod::query()->whereKey($data['payment_method_id'])
                    ->where('requires_verification', true)->exists())
        )) {
            throw ValidationException::withMessages([
                'payment_arrangement' => 'لا يمكن طباعة الفاتورة قبل التحقق من الدفع. استخدم «الفاتورة والدفع» لتسجيل الطلب بانتظار التحقق.',
            ]);
        }

        $data['location_id'] = $location->id;
        $data['payment_proof'] = $request->file(
            'payment_proof'
        );

        if (! in_array($data['payment_arrangement'], ['pay_on_pickup', 'on_account'], true)
            && ! empty($data['payment_method_id'])) {
            $method = PaymentMethod::query()->active()->find($data['payment_method_id']);
            if (! $method || ($method->locationPaymentMethods()->exists()
                && ! $method->locationPaymentMethods()
                    ->where('location_id', $location->id)
                    ->where('is_active', true)->exists())) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'طريقة الدفع غير مفعلة في هذا الفرع.',
                ]);
            }
        }

        // Split-tender methods must each be enabled for this branch. A method
        // from a different branch must never bypass the standard POS guard.
        foreach (($data['split_payments'] ?? []) as $index => $part) {
            $method = PaymentMethod::query()->active()
                ->find($part['payment_method_id'] ?? null);
            if (! $method || $method->requires_verification
                || ($method->locationPaymentMethods()->exists()
                    && ! $method->locationPaymentMethods()
                        ->where('location_id', $location->id)
                        ->where('is_active', true)->exists())) {
                throw ValidationException::withMessages([
                    "split_payments.$index.payment_method_id" =>
                        'طريقة الدفع غير متاحة لهذا الفرع أو تحتاج تحققًا منفصلًا.',
                ]);
            }
        }

        if (! empty($data['customer_id'])) {
            $customerAllowed = Customer::query()
                ->availableAt($location->id)
                ->whereKey($data['customer_id'])
                ->exists();

            abort_unless(
                $customerAllowed,
                422,
                'العميل غير متاح في هذا الفرع.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Server-side POS menu protection
        |--------------------------------------------------------------------------
        */
        $requestedProductIds = collect(
            $data['items'] ?? []
        )
            ->pluck('product_id')
            ->map(
                fn ($id) => (int) $id
            )
            ->filter()
            ->unique()
            ->values();

        $allowedProductIds = Product::query()
            ->active()
            ->whereIn(
                'id',
                $requestedProductIds
            )
            ->whereHas(
                'restaurantMenuItems',
                fn ($menuItem) => $menuItem
                    ->where(
                        'location_id',
                        $location->id
                    )
                    ->where('is_active', true)
                    ->where('show_in_pos', true)
            )
            ->whereHas(
                'locationProducts',
                fn ($locationProducts) =>
                    $locationProducts
                        ->where(
                            'location_id',
                            $location->id
                        )
                        ->where(
                            'is_available',
                            true
                        )
            )
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            );

        $invalidProductIds =
            $requestedProductIds
                ->diff($allowedProductIds);

        if ($invalidProductIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' =>
                    'أحد أصناف الطلب غير متاح للبيع في منيو هذا الفرع. حدّث شاشة نقطة البيع وحاول مرة أخرى.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create order once
        |--------------------------------------------------------------------------
        |
        | The browser now uses fetch() for POS actions. Do not redirect that AJAX
        | request to orders.show, because fetch would follow the whole HTML redirect
        | before the cashier receives a response.
        |
        */
        $order = $this->restaurantOrders
            ->createPosOrder(
                $data,
                $request->user()
            );

        $order->loadMissing(['invoice', 'kitchenTickets']);

        $status =
            $order->status instanceof \BackedEnum
                ? $order->status->value
                : (string) $order->status;

        $message =
            $status === 'draft'
                ? 'تم إنشاء طلب المطعم وهو بانتظار تحقق الدفع.'
                : 'تم إنشاء طلب المطعم وتأكيده بنجاح.';

        /*
        |--------------------------------------------------------------------------
        | AJAX / POS response
        |--------------------------------------------------------------------------
        */
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,

                'message' => $message,

                'order_id' =>
                    (int) $order->id,

                'order_number' =>
                    (string)
                    $order->order_number,

                'status' => $status,

                'kitchen_dispatched' => $order->kitchenTickets->isNotEmpty(),

                'kitchen_print_url' => $order->kitchenTickets->isNotEmpty()
                    ? route('restaurant.pos.orders.kitchen-print', $order)
                    : null,

                'redirect_url' =>
                    $request->user()->can('orders.view')
                        ? route('orders.show', $order)
                        : route('restaurant.pos.index', ['order_created' => $order->order_number]),

                'invoice_print_url' =>
                    $order->invoice
                        ? route('restaurant.pos.orders.invoice-print', $order)
                        : null,
            ], 201);
        }

        /*
        |--------------------------------------------------------------------------
        | Normal HTML fallback
        |--------------------------------------------------------------------------
        */
        return redirect()
            ->route(
                'orders.show',
                $order
            )
            ->with(
                'success',
                $message
            );
    }
}

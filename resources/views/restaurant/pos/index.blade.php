@extends('layouts.app')

@section('title', 'نقطة البيع')
@section('page-title', 'نقطة البيع')

@section('content')
@php
    $selectedTableId = (int) old('restaurant_table_id', request('table_id', 0));

    $cashierName =
        auth()->user()?->employee?->full_name
        ?? auth()->user()?->display_name
        ?? 'المستخدم';
    $cashierCanApproveDiscount = (bool) (auth()->user()?->isAdmin()
        || auth()->user()?->hasRole('Branch Manager')
        || auth()->user()?->can('restaurant_pos.discount.approve'));

    $oldServiceType = old('service_type', $selectedTableId ? 'dine_in' : 'takeaway');
    $allowedArrangementCodes = collect($paymentArrangements)->map(fn ($arrangement) => $arrangement->value)->all();
    $preferredArrangement = $selectedTableId ? 'pay_on_pickup' : 'pay_now';
    $defaultArrangement = in_array($preferredArrangement, $allowedArrangementCodes, true)
        ? $preferredArrangement
        : ($allowedArrangementCodes[0] ?? '');
    $oldPaymentArrangement = old('payment_arrangement', $defaultArrangement);
    if (! in_array($oldPaymentArrangement, $allowedArrangementCodes, true)) {
        $oldPaymentArrangement = $defaultArrangement;
    }

    $defaultSalesChannelId = old('sales_channel_id', $salesChannels->first()?->id);
    $activeSessionAccountUrls = $tables->filter(fn ($table) => $table->activeSession !== null)->mapWithKeys(
        fn ($table) => [(string) $table->id => route('restaurant.pos.tables.account', [
            'restaurantTable' => $table,
            'location_id' => $location->id,
        ])]
    );
@endphp

<div class="rb-pos" id="rbPosRoot">
    <main class="rb-pos-workspace">
    {{-- =====================================================
         PAGE HEADER — same composition as the reference UI
    ====================================================== --}}
    <div class="rb-pos-header">
        <div class="rb-pos-heading">
            <h1>نقطة البيع</h1>
            <div class="rb-pos-breadcrumb">
                <span>واجهة الكاشير</span>
                <span>•</span>
                <span>{{ $location->name }}</span>
                <span>•</span>
                <span>{{ $cashierName }}</span>
            </div>
        </div>

        <div class="rb-pos-header-actions">
            <a
                href="{{ route('restaurant.dashboard', ['location_id' => $location->id]) }}"
                class="rb-btn rb-btn-light rb-back-system"
                title="العودة إلى النظام"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
                العودة إلى النظام
            </a>
            <button type="button" class="rb-btn rb-btn-primary" id="rbNewOrderBtn">
                <span class="rb-btn-icon">+</span>
                طلب جديد
            </button>

            <button type="button" class="rb-btn rb-btn-light" id="rbQrOrdersBtn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    <path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 19h2M19 14h2"></path>
                </svg>
                طلبات QR
            </button>

            <button type="button" class="rb-btn rb-btn-light" id="rbDraftListBtn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 3h11l3 3v15H5z"></path>
                    <path d="M8 8h8M8 12h8M8 16h5"></path>
                </svg>
                المسودات
            </button>
            <button type="button" class="rb-btn rb-btn-light" id="rbReceiptsBtn">فواتير سابقة</button>

            

            <button
                type="button"
                class="rb-btn rb-btn-light"
                id="rbTableOrderBtn"
                @disabled($tables->isEmpty())
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 9h16M7 9V6h10v3M6 9l-1 11M18 9l1 11"></path>
                </svg>
                طلب طاولة
            </button>
        </div>
    </div>

    @if(request()->filled('order_created'))
        <div class="rb-pos-alert" role="status">تم حفظ الطلب {{ request('order_created') }}. يمكنك فتحه من الطلبات الأخيرة حسب صلاحياتك.</div>
    @endif

    @if($errors->any())
        <div class="rb-pos-alert" role="alert">
            <strong>تعذر تنفيذ الطلب.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('restaurant.pos.orders.store') }}"
        enctype="multipart/form-data"
        id="restaurantPosForm"
        novalidate
    >
        @csrf
        <input type="hidden" name="location_id" value="{{ $location->id }}">
        <input type="hidden" name="pos_action" id="rbPosAction" value="bill_payment">
        <input type="hidden" name="pos_request_token" id="rbPosRequestToken">

        <div class="rb-pos-shell">
            {{-- =====================================================
                 PRODUCTS PANEL
            ====================================================== --}}
            <section class="rb-products-panel">
                <div class="rb-menu-panel-head">
                    <div>
                        <span class="rb-menu-kicker">منيو الكاشير</span>
                        <h2>اختر المنتجات</h2>
                        <p>اضغط على أي صنف لإضافته مباشرة إلى الطلب الحالي.</p>
                    </div>

                    <div class="rb-menu-location-pill">
                        <span class="rb-menu-location-dot"></span>
                        {{ $location->name }}
                    </div>
                </div>

                <div class="rb-products-toolbar">
                    <label class="rb-search rb-search-products">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.4-3.4"></path>
                        </svg>
                        <input
                            type="search"
                            id="rbProductSearch"
                            placeholder="ابحث بالاسم أو الرمز أو الباركود — F2"
                            aria-label="البحث في منتجات المنيو"
                            maxlength="150"
                            autocomplete="off"
                        >
                    </label>

                    <select class="rb-control rb-toolbar-select" id="rbCategorySelect" aria-label="اختيار الفئة">
                        <option value="__all__">كل الفئات</option>
                    </select>

                    <select class="rb-control rb-toolbar-select" id="rbBrandSelect" aria-label="اختيار العلامة">
                        <option value="__all__">كل العلامات</option>
                    </select>
                </div>

                <div class="rb-category-tabs" id="rbCategoryTabs"></div>

                <div class="rb-product-grid" id="rbProductGrid" aria-live="polite"></div>

                <button type="button" class="rb-load-more" id="rbLoadMore" hidden>عرض المزيد من المنتجات</button>

                <div class="rb-products-footer">
                    <span><b id="rbVisibleProductCount">0</b> من <b id="rbMatchingProductCount">0</b> منتج مطابق</span>
                    <span>{{ $location->name }}</span>
                    <button type="button" id="rbRefreshCatalog" class="rb-menu-management-link" title="تحديث المنتجات والأسعار والتوفر من السيرفر">تحديث المنتجات</button>
                    @can('restaurant_menu.view')
                        <a href="{{ route('restaurant.menu.index', ['location_id' => $location->id]) }}"
                           class="rb-menu-management-link" title="راجع إتاحة الأصناف للفرع، الصور والأسعار">
                            إدارة منتجات المنيو
                        </a>
                    @endcan
                </div>
            </section>

            {{-- =====================================================
                 CURRENT ORDER PANEL
            ====================================================== --}}
            <aside class="rb-order-panel">
                <div class="rb-order-top-controls">
                    <label class="rb-search rb-existing-search">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.4-3.4"></path>
                        </svg>
                        <input
                            type="search"
                            id="rbExistingOrderSearch"
                            placeholder="ابحث في الطلبات الحالية"
                            autocomplete="off"
                        >
                    </label>

                    <div class="rb-order-selects">
                        <select
                            class="rb-control"
                            name="service_type"
                            id="rbServiceType"
                            required
                        >
                            @foreach($serviceTypes as $type)
                                <option
                                    value="{{ $type->value }}"
                                    @selected($oldServiceType === $type->value)
                                >
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>

                        <select
                            class="rb-control"
                            name="restaurant_table_id"
                            id="rbTableSelect"
                        >
                            <option value="">اختر الطاولة</option>
                            @foreach($tables as $table)
                                <option
                                    value="{{ $table->id }}"
                                    data-occupied="{{ $table->activeSession ? '1' : '0' }}"
                                    @selected($selectedTableId === (int) $table->id)
                                >
                                    {{ $table->area?->name ? $table->area->name . ' — ' : '' }}
                                    {{ $table->displayName() }}
                                    {{ $table->activeSession ? ' — مشغولة' : '' }}
                                    {{ $table->activeSession?->diningGroup ? ' — مجموعة ' . $table->activeSession->diningGroup->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <a id="rbTableSessionAccountLink" class="rb-btn rb-btn-light" href="#" target="_blank" rel="noopener" hidden>كشف حساب الجلسة الحالية</a>
                </div>

                <div class="rb-order-card">
                    <div class="rb-order-title-row">
                        <div class="rb-order-title">
                            <span class="rb-order-bag">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M5 8h14l-1 13H6z"></path>
                                    <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                                </svg>
                            </span>
                            <div class="rb-order-title-copy">
                                <small>السلة الحالية</small>
                                <strong>الطلب الحالي</strong>
                            </div>
                        </div>

                        <div class="rb-order-status-pill">
                            <span id="rbCartCount">0</span> عناصر
                        </div>
                    </div>

                    <div class="rb-cart-scroll" id="rbCartScroll">
                        <div class="rb-cart-empty" id="rbCartEmpty">
                            <div class="rb-cart-empty-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 6h15l-1.5 8h-11z"></path>
                                    <path d="M6 6 5 3H2"></path>
                                    <circle cx="9" cy="19" r="1.4"></circle>
                                    <circle cx="18" cy="19" r="1.4"></circle>
                                </svg>
                            </div>
                            <strong>لم تضف منتجات بعد</strong>
                            <span>اضغط على أي منتج لإضافته للطلب.</span>
                        </div>

                        <div id="rbCartItems"></div>
                    </div>

                    <div id="rbHiddenItems"></div>

                    <button type="button" class="rb-more-details" id="rbOpenCheckoutBtn">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            العميل، الخصم، قناة البيع والدفع
                        </span>
                        <span>›</span>
                    </button>

                    <div class="rb-summary">
                        <div class="rb-summary-row">
                            <span>الإجمالي الفرعي:</span>
                            <strong id="rbSubtotal">₪0.00</strong>
                        </div>
                        <div class="rb-summary-row">
                            <span>خصم الطلب:</span>
                            <strong id="rbDiscountAmount">₪0.00</strong>
                        </div>
                        <div class="rb-summary-row">
                            <span>خصم قناة البيع:</span>
                            <strong id="rbChannelDiscount">₪0.00</strong>
                        </div>
                        <div class="rb-summary-total">
                            <span>الإجمالي:</span>
                            <strong id="rbGrandTotal">₪0.00</strong>
                        </div>
                    </div>

                    <div class="rb-order-actions">
                        <button type="button" class="rb-action rb-action-kot" id="rbKotPrintBtn"
                                title="تأكيد الطلب وإرساله للمطبخ وطباعة تذكرة التحضير دون تحصيل المبلغ">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11V7l8-4 8 4v4M5 11v10h14V11M8 15h8"/></svg>
                            <span class="rb-action-title">إرسال للمطبخ</span>
                            <small class="rb-action-subtitle">بدون دفع</small>
                        </button>

                        <button type="button" class="rb-action rb-action-draft" id="rbSaveDraftBtn"
                            title="حفظ المنتجات مؤقتًا على هذا الجهاز دون إنشاء طلب أو خصم مخزون">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h13l3 3v15H4zM8 3v7h8V3M8 21v-8h8v8"/></svg>
                            <span class="rb-action-title">حفظ مسودة</span>
                            <small class="rb-action-subtitle">استكمال لاحقًا</small>
                        </button>

                        <button type="button" class="rb-action rb-action-payment" id="rbBillPaymentBtn"
                            title="فتح شاشة التحصيل نقدًا أو بطاقة أو دفع مختلط؛ مع دعم الدفع اللاحق حسب إعدادات الفرع">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18M16 15h3"/></svg>
                            <span class="rb-action-title">تحصيل الدفع</span>
                            <small class="rb-action-subtitle">نقدي / بطاقة / مختلط</small>
                        </button>

                        <button type="button" class="rb-action rb-action-print" id="rbBillPrintBtn"
                            title="تحصيل المبلغ كاملًا ثم إصدار وطباعة فاتورة العميل">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8V3h12v5M6 17H4V9h16v8h-2M6 14h12v7H6zM16 11h2"/></svg>
                            <span class="rb-action-title">إتمام وطباعة</span>
                            <small class="rb-action-subtitle">بعد التحقق من الدفع</small>
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        {{-- On small touchscreens, show cart total and checkout without
             scrolling through the entire product catalogue. --}}
        <div class="rb-mobile-cart-bar" id="rbMobileCartBar" aria-label="ملخص السلة السريع">
            <button type="button" id="rbMobileCartOpen" class="rb-mobile-cart-open">
                <span><strong id="rbMobileCartCount">0</strong> عناصر في السلة</span>
                <strong id="rbMobileCartTotal">₪0.00</strong>
                <small>عرض السلة</small>
            </button>
            <button type="button" id="rbMobileCheckoutBtn" class="rb-mobile-checkout">تحصيل الدفع</button>
        </div>

        {{-- =====================================================
             CHECKOUT / ORDER DETAILS MODAL
        ====================================================== --}}
        <div class="rb-modal" id="rbCheckoutModal" hidden aria-hidden="true">
            <div class="rb-modal-backdrop" data-close-checkout></div>

            <div class="rb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rbCheckoutTitle">
                <div class="rb-modal-head">
                    <div>
                        <h2 id="rbCheckoutTitle">تفاصيل الطلب والدفع</h2>
                        <p id="rbCheckoutHint">كل الحقول هنا مرتبطة بطلب الكاشير الحالي.</p>
                        <button type="button" id="rbQuickCashBtn" class="rb-quick-cash-btn">دفع نقدي سريع</button>
                        <button type="button" id="rbQuickCashExpand" class="rb-quick-cash-expand" hidden>عرض بقية التفاصيل</button>
                    </div>
                    <button type="button" class="rb-modal-close" data-close-checkout>×</button>
                </div>

                <div class="rb-modal-body">
                    <div class="rb-modal-grid">
                        <div class="rb-field">
                            <div class="rb-customer-field-head">
                                <label for="rbCustomerSelect">العميل</label>
                                @can('customers.create')
                                    <button type="button" id="rbQuickCustomerOpen" class="rb-btn rb-btn-light rb-quick-customer-button">+ عميل جديد</button>
                                @endcan
                            </div>
                            <select id="rbCustomerSelect" name="customer_id" class="rb-control">
                                <option value="">عميل نقدي</option>
                                @foreach($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        @selected((string) old('customer_id') === (string) $customer->id)
                                    >
                                        {{ $customer->name }}{{ $customer->phone ? ' — ' . $customer->phone : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field" id="rbGuestCountGroup">
                            <label>عدد الضيوف</label>
                            <input
                                type="number"
                                name="guest_count"
                                min="1"
                                max="100"
                                value="{{ old('guest_count', 2) }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field">
                            <label>قناة البيع *</label>
                            <select name="sales_channel_id" class="rb-control" required>
                                <option value="">اختر القناة</option>
                                @foreach($salesChannels as $channel)
                                    <option
                                        value="{{ $channel->id }}"
                                        @selected((string) $defaultSalesChannelId === (string) $channel->id)
                                    >
                                        {{ $channel->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field">
                            <label>ترتيب الدفع *</label>
                            <select
                                name="payment_arrangement"
                                id="rbPaymentArrangement"
                                class="rb-control"
                                required
                            >
                                @foreach($paymentArrangements as $arrangement)
                                    <option
                                        value="{{ $arrangement->value }}"
                                        @selected($oldPaymentArrangement === $arrangement->value)
                                    >
                                        {{ $arrangement->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rb-field" id="rbPaymentMethodGroup">
                            <label>نوع طريقة الدفع</label>
                            <select
                                name="payment_method_id"
                                id="rbPaymentMethod"
                                class="rb-control"
                            >
                                <option value="">اختر طريقة الدفع</option>
                                @foreach($paymentMethods as $method)
                                    <option
                                        value="{{ $method->id }}"
                                        data-type="{{ $method->type }}"
                                        data-reference="{{ $method->requires_reference ? '1' : '0' }}"
                                        data-verification="{{ $method->requires_verification ? '1' : '0' }}"
                                        @selected((string) old('payment_method_id') === (string) $method->id)
                                    >
                                        {{ $method->name_ar ?: $method->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if($paymentMethods->isEmpty())
                            <div class="rb-field rb-field-wide rb-payment-method-warning" role="alert">
                                لا توجد طرق دفع نشطة ومتاحة لفرع {{ $location->name }}. يجب تفعيل طريقة دفع وربطها بالفرع قبل التحصيل.
                                @can('payment_methods.view')
                                    <a href="{{ route('payment-methods.index') }}" target="_blank" rel="noopener">إدارة طرق الدفع</a>
                                @endcan
                            </div>
                        @endif

                        {{-- Two-tender immediate checkout: both amounts are posted atomically. --}}
                        <div class="rb-field rb-field-wide" id="rbSplitTenderOption">
                            <label class="rb-split-toggle" for="rbSplitTenderToggle">
                                <input type="checkbox" id="rbSplitTenderToggle" @disabled($paymentMethods->where('requires_verification', false)->count() < 2)>
                                <span>تقسيم الدفع بين نقد وبطاقة / طريقتين</span>
                            </label>
                        </div>
                        <div class="rb-field rb-field-wide rb-split-tenders" id="rbSplitTenderPanel" hidden>
                            <strong>الدفع المختلط — مجموع الدفعتين يجب أن يساوي إجمالي الطلب</strong>
                            @php
                                $eligibleSplitMethods = $paymentMethods->where('requires_verification', false);
                            @endphp
                            @if($eligibleSplitMethods->count() < 2)
                                <p class="rb-payment-method-warning" role="alert">
                                    الدفع المختلط غير متاح: يلزم تفعيل طريقتين مختلفتين على الأقل لا تتطلبان تحققًا لاحقًا في هذا الفرع.
                                    @can('payment_methods.view')
                                        <a href="{{ route('payment-methods.index') }}" target="_blank" rel="noopener">مراجعة طرق الدفع</a>
                                    @endcan
                                </p>
                            @endif
                            <div class="rb-split-grid">
                                @for($i=0;$i<2;$i++)
                                    <fieldset class="rb-split-part">
                                        <legend>الدفعة {{ $i + 1 }}</legend>
                                        <label for="rbSplitMethod{{ $i }}">طريقة الدفع</label>
                                        <select id="rbSplitMethod{{ $i }}" data-split-method
                                                name="split_payments[{{ $i }}][payment_method_id]" class="rb-control" disabled>
                                            <option value="">اختر الطريقة</option>
                                            @foreach($eligibleSplitMethods as $method)
                                                @if(!$method->requires_verification)
                                                    <option value="{{ $method->id }}"
                                                            data-type="{{ $method->type }}"
                                                            data-reference="{{ $method->requires_reference ? '1':'0' }}">
                                                        {{ $method->name_ar ?: $method->name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <label for="rbSplitAmount{{ $i }}">المبلغ (₪)</label>
                                        <input id="rbSplitAmount{{ $i }}" data-split-amount type="number" min="0.01" step="0.01"
                                               name="split_payments[{{ $i }}][amount]" class="rb-control" disabled>
                                        <label for="rbSplitRef{{ $i }}">رقم العملية (لغير النقد)</label>
                                        <input id="rbSplitRef{{ $i }}" data-split-reference type="text" maxlength="100"
                                               name="split_payments[{{ $i }}][reference_number]" class="rb-control" disabled>
                                        <label class="rb-split-confirm"><input data-split-confirm type="checkbox" value="1"
                                               name="split_payments[{{ $i }}][payment_received_confirmed]" disabled>
                                            تم التأكد من تحصيل الدفعة غير النقدية
                                        </label>
                                    </fieldset>
                                @endfor
                            </div>
                            <strong id="rbSplitTenderBalance" aria-live="polite"></strong>
                        </div>

                        <div class="rb-field" id="rbPaidAmountGroup" hidden>
                            <label>المبلغ المدفوع</label>
                            <input
                                type="number"
                                name="paid_amount"
                                step="0.01"
                                min="0"
                                value="{{ old('paid_amount') }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field" data-quick-cash-optional>
                            <label>نوع الخصم</label>
                            <select name="discount_type" id="rbDiscountType" class="rb-control">
                                <option value="none">بدون خصم</option>
                                <option value="percentage" @selected(old('discount_type') === 'percentage')>نسبة %</option>
                                <option value="fixed" @selected(old('discount_type') === 'fixed')>قيمة ثابتة</option>
                            </select>
                        </div>

                        <div class="rb-field" data-quick-cash-optional>
                            <label>قيمة الخصم</label>
                            <input
                                type="number"
                                name="discount_value"
                                id="rbDiscountValue"
                                step="0.01"
                                min="0"
                                value="{{ old('discount_value', 0) }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field rb-field-wide" id="rbDiscountReasonGroup" hidden>
                            <label for="rbDiscountReason">سبب الخصم اليدوي *</label>
                            <textarea name="discount_reason" id="rbDiscountReason" class="rb-control"
                                      maxlength="300" minlength="8" rows="2"
                                      placeholder="مثال: تعويض الزبون عن تأخر تجهيز الطلب">{{ old('discount_reason') }}</textarea>
                            <small>يُسجل السبب باسم الموظف ورقم الطلب ضمن سجل العمليات.</small>
                            <small id="rbSensitiveDiscountNotice" hidden role="alert"
                                   style="color:#a51f17;font-weight:850">
                                هذا الخصم يحتاج تنفيذ الطلب من حساب مدير مخوّل.
                                الحد الحالي {{ (float) config('tish_pos.manager_discount_percentage', 10) }}% أو
                                ₪{{ (float) config('tish_pos.manager_discount_fixed', 20) }} للخصم الثابت.
                            </small>
                        </div>

                        <div class="rb-field rb-field-wide" id="rbReferenceGroup" hidden>
                            <label>رقم العملية / المرجع</label>
                            <input
                                type="text"
                                name="reference_number"
                                value="{{ old('reference_number') }}"
                                class="rb-control"
                            >
                        </div>

                        <div class="rb-field" id="rbSenderNameGroup" hidden>
                            <label>اسم المحوّل / صاحب العملية (اختياري)</label>
                            <input
                                type="text"
                                name="sender_name"
                                value="{{ old('sender_name') }}"
                                maxlength="150"
                                class="rb-control"
                                placeholder="اسم الشخص الذي قام بالدفع"
                            >
                        </div>

                        <div class="rb-field" id="rbSenderPhoneGroup" hidden>
                            <label>رقم جوال المحوّل (اختياري)</label>
                            <input
                                type="text"
                                name="sender_phone"
                                value="{{ old('sender_phone') }}"
                                maxlength="50"
                                class="rb-control"
                                dir="ltr"
                                placeholder="مثال: 059xxxxxxx"
                            >
                        </div>

                        <div class="rb-field rb-field-wide" id="rbSenderAccountGroup" hidden>
                            <label>رقم الحساب / المحفظة (اختياري)</label>
                            <input
                                type="text"
                                name="sender_account_number"
                                value="{{ old('sender_account_number') }}"
                                maxlength="120"
                                class="rb-control"
                                dir="ltr"
                                placeholder="رقم الحساب أو رقم المحفظة — يكفي هذا أو رقم الجوال"
                            >
                            <small class="rb-field-hint">
                                يمكن إضافة بيانات المرسل عندما يقدمها العميل؛ رقم العملية يوثق التحصيل.
                            </small>
                        </div>

                        <div class="rb-field rb-field-wide" id="rbProofGroup" hidden>
                            <label>إثبات الدفع</label>
                            <input
                                type="file"
                                name="payment_proof"
                                accept="image/*"
                                class="rb-control rb-file-control"
                            >
                        </div>

                        <div class="rb-field rb-field-wide" id="rbManualReceiptGroup" hidden>
                            <label style="display:flex;gap:.6rem;align-items:flex-start">
                                <input type="checkbox" name="payment_received_confirmed" value="1" id="rbManualReceipt">
                                <span>تحققت بنفسي من وصول المبلغ إلى حساب المحل باستخدام رقم العملية أعلاه.</span>
                            </label>
                            <small class="rb-field-hint">يُسجَّل التحصيل باسم مستخدم الكاشير. عند الحاجة إلى مراجعة مستقلة فعّل طلب إثبات الدفع لهذه الطريقة من الإعدادات.</small>
                        </div>

                        <div class="rb-field rb-field-wide" data-quick-cash-optional>
                            <label>ملاحظات عامة على الطلب</label>
                            <textarea
                                name="notes"
                                rows="3"
                                class="rb-control rb-textarea"
                                placeholder="مثال: تجهيز سريع، العميل ينتظر..."
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="rb-modal-foot">
                    <div class="rb-modal-total">
                        <span>الإجمالي</span>
                        <strong id="rbCheckoutTotal">₪0.00</strong>
                    </div>
                    <button type="button" class="rb-btn rb-btn-light" data-close-checkout>إلغاء</button>
                    <button type="button" class="rb-btn rb-btn-primary" id="rbCheckoutContinueBtn">متابعة</button>
                </div>
            </div>
        </div>
    </form>


    @can('customers.create')
    <div class="rb-modal" id="rbQuickCustomerModal" hidden aria-hidden="true">
        <div class="rb-modal-backdrop" data-close-quick-customer></div>
        <div class="rb-modal-dialog rb-quick-customer-dialog" role="dialog" aria-modal="true" aria-labelledby="rbQuickCustomerTitle">
            <div class="rb-modal-head">
                <div>
                    <h2 id="rbQuickCustomerTitle">إضافة عميل جديد</h2>
                    <p>النوع الافتراضي فرد. يتم حفظ البيانات واختيار العميل تلقائيًا في الطلب.</p>
                </div>
                <button type="button" class="rb-modal-close" data-close-quick-customer aria-label="إغلاق">×</button>
            </div>
            <div class="rb-modal-body">
                <div class="rb-quick-customer-fields">
                    <div class="rb-field">
                        <label for="rbQuickCustomerType">نوع العميل *</label>
                        <select id="rbQuickCustomerType" class="rb-control">
                            <option value="individual" selected>فرد</option>
                            <option value="institution">مؤسسة</option>
                            <option value="company">شركة</option>
                            <option value="government">جهة / قطاع</option>
                        </select>
                    </div>
                    <div class="rb-field">
                        <label for="rbQuickCustomerName">اسم العميل / الجهة *</label>
                        <input id="rbQuickCustomerName" class="rb-control" maxlength="150" autocomplete="name" required>
                    </div>
                    <div class="rb-field">
                        <label for="rbQuickCustomerPhone">رقم الجوال *</label>
                        <input id="rbQuickCustomerPhone" class="rb-control" type="tel" maxlength="20" autocomplete="tel" required>
                    </div>
                    <div class="rb-field">
                        <label for="rbQuickCustomerSecondaryPhone">هاتف بديل</label>
                        <input id="rbQuickCustomerSecondaryPhone" class="rb-control" type="tel" maxlength="20">
                    </div>
                    <div class="rb-field" data-quick-institution hidden>
                        <label for="rbQuickCustomerContactPerson">الشخص المسؤول</label>
                        <input id="rbQuickCustomerContactPerson" class="rb-control" maxlength="150">
                    </div>
                    <div class="rb-field" data-quick-institution hidden>
                        <label for="rbQuickCustomerTaxNumber">الرقم الضريبي / التعريفي</label>
                        <input id="rbQuickCustomerTaxNumber" class="rb-control" maxlength="100">
                    </div>
                    <div class="rb-field rb-quick-wide">
                        <label for="rbQuickCustomerAddress">العنوان</label>
                        <input id="rbQuickCustomerAddress" class="rb-control" maxlength="500">
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->can('customers.view_all') || auth()->user()->can('financial.global.view'))
                    <div class="rb-field" data-quick-institution hidden>
                        <label for="rbQuickCustomerScope">نطاق العميل</label>
                        <select id="rbQuickCustomerScope" class="rb-control">
                            <option value="branch">الفرع الحالي فقط</option>
                            <option value="selected">فروع محددة</option>
                            <option value="global">جميع الفروع</option>
                        </select>
                    </div>
                    <div class="rb-field rb-quick-wide" id="rbQuickCustomerLocations" hidden>
                        <label>الفروع المسموح بالسحب منها</label>
                        <div class="rb-quick-branch-options">
                            @foreach($locations as $quickLocation)
                            <label><input type="checkbox" value="{{ $quickLocation->id }}" data-quick-location> {{ $quickLocation->name }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="rb-field rb-quick-wide" data-quick-institution hidden>
                        <label><input type="checkbox" id="rbQuickCustomerAllowCredit"> السماح بالبيع على الحساب</label>
                        <small>تفعيل المديونية يحتاج صلاحية إدارية؛ لا يتم احتساب هذا الطلب كمدفوع تلقائيًا.</small>
                    </div>
                    <div class="rb-quick-credit-fields rb-quick-wide" id="rbQuickCustomerCreditFields" hidden>
                        <div class="rb-field"><label for="rbQuickCustomerCreditLimit">الحد الائتماني (₪)</label><input id="rbQuickCustomerCreditLimit" type="number" min="0" step="0.01" class="rb-control"></div>
                        <div class="rb-field"><label for="rbQuickCustomerBillingCycle">دورة الفوترة</label><select id="rbQuickCustomerBillingCycle" class="rb-control"><option value="immediate">فوري</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option></select></div>
                        <div class="rb-field"><label for="rbQuickCustomerPaymentTerms">مهلة السداد بالأيام</label><input id="rbQuickCustomerPaymentTerms" type="number" min="0" max="365" value="0" class="rb-control"></div>
                    </div>
                    @endif
                    <div class="rb-field rb-quick-wide">
                        <label for="rbQuickCustomerNotes">ملاحظات</label>
                        <textarea id="rbQuickCustomerNotes" class="rb-control" rows="2"></textarea>
                    </div>
                </div>
                <p class="rb-quick-customer-error" id="rbQuickCustomerError" role="alert" hidden></p>
            </div>
            <div class="rb-modal-foot">
                <button type="button" class="rb-btn rb-btn-light" data-close-quick-customer>إلغاء</button>
                <button type="button" class="rb-btn rb-btn-primary" id="rbQuickCustomerSave">حفظ العميل</button>
            </div>
        </div>
    </div>
    @endcan

    <div class="rb-modal" id="rbProductOptionsModal" hidden aria-hidden="true">
        <div class="rb-modal-backdrop" data-close-product-options></div>
        <div class="rb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rbProductOptionsTitle">
            <div class="rb-modal-head">
                <div>
                    <h2 id="rbProductOptionsTitle">خيارات المنتج</h2>
                    <p>اختر الحجم والإضافات قبل إضافة المنتج للطلب.</p>
                </div>
                <button type="button" class="rb-modal-close" data-close-product-options aria-label="إغلاق">×</button>
            </div>
            <div class="rb-modal-body" id="rbProductOptionsBody"></div>
            <div class="rb-modal-foot">
                <div class="rb-modal-total">
                    <span>سعر القطعة</span>
                    <strong id="rbProductOptionsPrice">₪0.00</strong>
                </div>
                <button type="button" class="rb-btn rb-btn-light" data-close-product-options>إلغاء</button>
                <button type="button" class="rb-btn rb-btn-primary" id="rbAddConfiguredProduct">إضافة للطلب</button>
            </div>
        </div>
    </div>

    {{-- Separate POS confirmation: the fullscreen cashier UI sits above the
         ERP shell, so the shell's global confirm overlay cannot be reused. --}}
    <div class="rb-modal" id="rbNewOrderConfirm" hidden aria-hidden="true">
        <div class="rb-modal-backdrop" data-cancel-new-order></div>
        <div class="rb-modal-dialog rb-confirm-dialog" role="dialog" aria-modal="true"
             aria-labelledby="rbNewOrderConfirmTitle" aria-describedby="rbNewOrderConfirmText">
            <div class="rb-modal-head">
                <h2 id="rbNewOrderConfirmTitle">بدء طلب جديد</h2>
                <button type="button" class="rb-modal-close" data-cancel-new-order aria-label="إغلاق">×</button>
            </div>
            <div class="rb-modal-body">
                <p id="rbNewOrderConfirmText">الطلب الحالي يحتوي على منتجات. بدء طلب جديد سيمسح المنتجات غير المحفوظة. هل تريد المتابعة؟</p>
            </div>
            <div class="rb-modal-foot">
                <button type="button" class="rb-btn rb-btn-light" data-cancel-new-order>رجوع للطلب</button>
                <button type="button" class="rb-btn rb-btn-primary" id="rbConfirmNewOrderButton">بدء طلب جديد</button>
            </div>
        </div>
    </div>
    </main>
<div class="rb-modal" id="rbQueuedCancelModal" hidden aria-hidden="true">
    <div class="rb-modal-backdrop" data-close-queued-cancel></div>
    <div class="rb-modal-dialog rb-draft-preview-dialog" role="dialog" aria-modal="true"
         aria-labelledby="rbQueuedCancelTitle">
        <div class="rb-modal-head">
            <h2 id="rbQueuedCancelTitle">إلغاء عنصر قبل التحضير</h2>
            <button type="button" class="rb-modal-close" data-close-queued-cancel aria-label="إغلاق">×</button>
        </div>
        <div class="rb-modal-body">
            <p>هذه العملية متاحة للمدير المخوّل فقط، قبل بدء تحضير العنصر ومن دون تحصيل سابق.
               تُحدّث فاتورة الطلب وتذكرة المطبخ والمخزون ضمن عملية واحدة.</p>
            <label for="rbQueuedCancelItem">اختر العنصر</label>
            <select id="rbQueuedCancelItem" class="rb-control"></select>
            <label for="rbQueuedCancelReason">سبب الإلغاء (إلزامي)</label>
            <textarea id="rbQueuedCancelReason" class="rb-control" rows="2"
                      minlength="8" maxlength="500" placeholder="مثل: الزبون ألغى هذا العنصر قبل تحضيره"></textarea>
            <p id="rbQueuedCancelError" role="alert" hidden></p>
        </div>
        <div class="rb-modal-foot">
            <button type="button" class="rb-btn rb-btn-light" data-close-queued-cancel>رجوع</button>
            <button type="button" class="rb-btn rb-btn-primary" id="rbQueuedCancelConfirm">إلغاء العنصر</button>
        </div>
    </div>
</div>
<div class="rb-modal" id="rbDraftPreviewModal" hidden aria-hidden="true">
    <div class="rb-modal-backdrop" data-close-draft-preview></div>
    <div class="rb-modal-dialog rb-draft-preview-dialog" role="dialog" aria-modal="true"
         aria-labelledby="rbDraftPreviewTitle">
        <div class="rb-modal-head">
            <h2 id="rbDraftPreviewTitle">تفاصيل المسودة</h2>
            <button type="button" class="rb-modal-close" data-close-draft-preview aria-label="إغلاق">×</button>
        </div>
        <div class="rb-modal-body" id="rbDraftPreviewBody"></div>
        <p id="rbDraftEditError" role="alert" hidden style="color:#b42318;font-weight:750;padding:0 15px"></p>
        <div id="rbDraftCancelReasonGroup" hidden style="padding:8px 16px">
            <label for="rbDraftCancelReason">سبب إلغاء المسودة (إلزامي)</label>
            <textarea id="rbDraftCancelReason" class="rb-control" rows="2" maxlength="500" placeholder="اكتب سبب الإلغاء بوضوح"></textarea>
        </div>
        <div class="rb-modal-foot">
            <button type="button" class="rb-btn rb-btn-light" data-close-draft-preview>إغلاق</button>
            <button type="button" class="rb-btn rb-btn-primary" id="rbDraftPreviewRestore" hidden>استكمال المسودة</button>
            <button type="button" class="rb-btn rb-btn-primary" id="rbDraftPreviewSave" hidden>حفظ تعديلات المسودة</button>
            <button type="button" class="rb-btn rb-btn-light" id="rbDraftPreviewCancel" hidden>إلغاء الطلب</button>
            <a class="rb-btn rb-btn-primary" id="rbDraftPreviewOpen" href="#" hidden>فتح الطلب المسجل</a>
        </div>
    </div>
</div>

</div>

{{-- =========================================================
     ORDERS / DRAFTS DRAWER
========================================================= --}}
<div class="rb-drawer-backdrop" id="rbDrawerBackdrop" hidden></div>
<aside class="rb-drawer" id="rbOrdersDrawer" aria-hidden="true">
    <div class="rb-drawer-head">
        <div>
            <h3 id="rbDrawerTitle">الطلبات</h3>
            <p id="rbDrawerSubtitle">{{ $location->name }}</p>
        </div>
        <button type="button" id="rbCloseDrawer">×</button>
    </div>

    <div class="rb-drawer-search">
        <label class="rb-search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.4-3.4"></path>
            </svg>
            <input type="search" id="rbDrawerSearchInput" placeholder="ابحث برقم الطلب أو العميل...">
        </label>
    </div>

    <div class="rb-drawer-section" id="rbLocalDraftSection" hidden>
        <div class="rb-drawer-section-title">المسودة المحلية</div>
        <div id="rbLocalDraftContainer"></div>
    </div>

    <div class="rb-drawer-section">
        <div class="rb-drawer-section-title" id="rbServerOrdersTitle">آخر الطلبات المسجلة</div>
        <div id="rbServerOrdersList">
            @forelse($recentOrders as $order)
                @php
                    $statusValue = $order->status instanceof \BackedEnum
                        ? $order->status->value
                        : (string) $order->status;

                    $statusLabel = method_exists($order->status, 'label')
                        ? $order->status->label()
                        : $statusValue;

                    $channel = $salesChannels->firstWhere('id', $order->sales_channel_id);
                    $channelText = strtolower(trim(
                        ($channel?->code ?? '') . ' ' . ($channel?->name ?? '')
                    ));
                @endphp

                <div class="rb-server-order-wrapper">
                <a
                    href="{{ route('orders.show', $order) }}"
                    class="rb-server-order-card"
                    data-server-order
                    data-status="{{ $statusValue }}"
                    data-channel="{{ $channelText }}"
                    data-search="{{ strtolower($order->order_number . ' ' . ($order->customer?->name ?? '') . ' ' . ($order->restaurantTable?->displayName() ?? '')) }}"
                >
                    <div class="rb-server-order-top">
                        <strong>{{ $order->order_number }}</strong>
                        <span>{{ $statusLabel }}</span>
                    </div>
                    <div class="rb-server-order-meta">
                        <span>{{ $order->restaurant_service_type?->label() ?? '—' }}</span>
                        <span>{{ $order->restaurantTable?->displayName() ?? 'بدون طاولة' }}</span>
                        <span>{{ $order->customer?->name ?? 'عميل نقدي' }}</span>
                    </div>
                </a>
                @if($order->invoice && ((int) $order->created_by === (int) auth()->id() || auth()->user()?->can('invoices.view')))
                    <a class="rb-reprint-link" href="{{ route('restaurant.pos.orders.invoice-print', $order) }}"
                       target="_blank" rel="noopener" aria-label="إعادة طباعة فاتورة الطلب {{ $order->order_number }}">
                        <span aria-hidden="true">🖨</span> إعادة طباعة الفاتورة
                    </a>
                @endif
                @if($statusValue === 'confirmed' && $order->kitchenTickets->isNotEmpty())
                    @can('cancel', $order)
                        <button type="button" class="rb-queued-cancel-trigger"
                                data-queued-cancel-order="{{ $order->id }}">إلغاء عنصر قبل التحضير</button>
                    @endcan
                @endif
                </div>
            @empty
                <div class="rb-drawer-empty">لا توجد طلبات حديثة.</div>
            @endforelse
        </div>
    </div>
</aside>


<style>
.rb-draft-preview-dialog{width:min(660px,96vw)}
.rb-draft-preview-items{list-style:none;padding:0;margin:12px 0;display:grid;gap:8px}
.rb-draft-preview-items li{padding:10px;border:1px solid var(--rb-border);border-radius:8px}
.rb-draft-preview-dialog .rb-modal-body{max-height:65dvh;overflow:auto}
.rb-draft-preview-dialog [hidden]{display:none!important}
.rb-pos .rb-payment-method-warning{padding:10px 12px;border:1px solid #e0ad87;border-radius:8px;background:#fff6e9;color:#793916;font-size:12px;line-height:1.7}
.rb-pos .rb-payment-method-warning a{font-weight:800;text-decoration:underline}
.rb-drawer .rb-reprint-link{display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;margin:5px 0 10px;padding:8px 12px;border:1px solid var(--rb-border);border-radius:8px;background:var(--rb-soft);color:var(--rb-text);font-weight:800;text-decoration:none}
.rb-drawer .rb-reprint-link:hover{color:var(--rb-orange);border-color:var(--rb-orange)}
.rb-drawer .rb-queued-cancel-trigger{display:block;width:100%;min-height:44px;margin:5px 0 10px;padding:9px 12px;border:1px solid var(--rb-border);border-radius:8px;background:var(--rb-soft);color:var(--rb-text);font-weight:800;text-align:center;cursor:pointer}
.rb-drawer .rb-queued-cancel-trigger:hover{border-color:var(--rb-orange);color:var(--rb-orange)}
.rb-pos #rbQueuedCancelModal .rb-modal-body{display:grid;gap:10px}
.rb-pos #rbQueuedCancelModal #rbQueuedCancelError{color:var(--rb-danger);font-weight:800}
.rb-pos #rbQueuedCancelModal #rbQueuedCancelError[hidden]{display:none!important}
/* POS split-payment controls: readable and touch-friendly at checkout. */
.rb-pos .rb-split-toggle,.rb-pos .rb-split-confirm{display:flex;align-items:center;gap:10px;line-height:1.5;cursor:pointer}
.rb-pos .rb-split-toggle input,.rb-pos .rb-split-confirm input{width:19px;height:19px;accent-color:#ad201c;flex-shrink:0}
.rb-pos .rb-split-tenders{padding:14px;border:1px solid #d1b7a2;background:#fff8f0;border-radius:12px;display:block}
.rb-pos .rb-split-tenders[hidden]{display:none!important}
.rb-pos .rb-split-tenders>strong{display:block;margin:0 0 12px;color:#67251c}
.rb-pos .rb-split-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.rb-pos .rb-split-part{min-width:0;border:1px solid #e2cec0;background:white;border-radius:10px;padding:12px;display:grid;gap:7px}
.rb-pos .rb-split-part legend{font-weight:900;color:#8d261c;padding:0 5px}
.rb-pos .rb-split-part label{font-weight:750;font-size:12px}
.rb-pos #rbSplitTenderBalance{font-weight:900;margin-top:12px}
@media(max-width:680px){.rb-pos .rb-split-grid{grid-template-columns:minmax(0,1fr)}}
@media(prefers-reduced-motion:reduce){.rb-pos .rb-split-part{transition:none}}

/* =========================================================
   RestroBit-inspired Restaurant POS
   Scope is fully isolated under .rb-pos to avoid breaking ERP UI.
========================================================= */
.rb-pos,
.rb-pos * {
    box-sizing: border-box;
}

html.rb-pos-mode,
body.rb-pos-mode {
    overflow: hidden !important;
}

.rb-pos {
    /* POS inherits the live system theme */
    --rb-orange: var(--theme-accent, var(--gold, #C98516));
    --rb-orange-hover: color-mix(in srgb, var(--rb-orange) 86%, #000);
    --rb-orange-soft: color-mix(in srgb, var(--rb-orange) 12%, var(--theme-surface, #fff));
    --rb-green: var(--theme-success, var(--success, #197438));
    --rb-navy: var(--theme-primary, var(--navy, #0A2948));
    --rb-text: var(--theme-text, var(--text-main, #172435));
    --rb-muted: var(--theme-text-muted, var(--text-muted, #687482));
    --rb-border: var(--theme-border, var(--border, #DDE2E7));
    --rb-border-2: color-mix(in srgb, var(--rb-border) 80%, var(--rb-text) 20%);
    --rb-bg: var(--theme-bg, var(--bg, #F5F6F8));
    --rb-white: var(--theme-surface, var(--card-bg, #FFFFFF));
    --rb-soft: color-mix(in srgb, var(--rb-white) 88%, var(--rb-bg) 12%);
    --rb-soft-2: color-mix(in srgb, var(--rb-white) 94%, var(--rb-bg) 6%);
    --rb-sidebar-bg: var(--theme-sidebar-bg, var(--rb-white));
    --rb-sidebar-text: var(--theme-sidebar-text, var(--rb-text));
    --rb-sidebar-active: var(--theme-sidebar-active, var(--rb-orange));
    --rb-danger: var(--theme-danger, var(--error, #E22929));
    --rb-radius: var(--theme-radius, 10px);
    --rb-shadow: 0 2px 10px color-mix(in srgb, var(--rb-text) 6%, transparent);

    position: fixed;
    inset: 0;
    z-index: 2147482000;
    width: 100vw;
    height: 100dvh;
    min-width: 0;
    display: grid;
    grid-template-columns: 1fr;
    overflow: hidden;
    direction: ltr;
    color: var(--rb-text);
    background: var(--rb-bg);
    font-family: inherit;
}

.rb-pos-workspace {
    min-width: 0;
    height: 100dvh;
    overflow: auto;
    padding: 25px 22px 20px;
    background: var(--rb-bg);
    scrollbar-width: thin;
}

.rb-pos-sidebar {
    position: relative;
    z-index: 2;
    min-width: 0;
    height: 100dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--rb-sidebar-bg);
    color: var(--rb-sidebar-text);
    border-right: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 12%, transparent);
    box-shadow: 4px 0 18px rgba(16, 24, 39, .025);
}

.rb-side-brand {
    height: 72px;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 0 14px;
    border-bottom: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 10%, transparent);
    color: var(--rb-sidebar-text);
}

.rb-side-brand-mark {
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--rb-sidebar-active);
    border-radius: 7px;
}

.rb-side-brand-mark svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-side-brand strong {
    font-size: 12px;
    font-weight: 900;
    letter-spacing: -.2px;
}

.rb-side-user {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr);
    gap: 7px;
    align-items: center;
    padding: 13px 11px 12px;
}

.rb-side-avatar {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: color-mix(in srgb, var(--rb-sidebar-text) 18%, var(--rb-sidebar-bg));
    font-size: 10px;
    font-weight: 900;
}

.rb-side-user strong,
.rb-side-user span {
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-side-user strong {
    color: var(--rb-sidebar-text);
    font-size: 9px;
    font-weight: 900;
}

.rb-side-user span {
    margin-top: 2px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 58%, transparent);
    font-size: 7.5px;
}

.rb-side-nav {
    min-height: 0;
    flex: 1;
    overflow: auto;
    padding: 4px 9px 10px;
    scrollbar-width: none;
}

.rb-side-nav::-webkit-scrollbar {
    display: none;
}

.rb-side-link {
    min-height: 38px;
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 2px 0;
    padding: 0 9px;
    border-radius: 5px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 78%, transparent);
    font-size: 8.5px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    transition: .14s ease;
}

.rb-side-link:hover {
    color: var(--rb-sidebar-text);
    background: color-mix(in srgb, var(--rb-sidebar-text) 8%, transparent);
}

.rb-side-link.active {
    color: var(--rb-sidebar-active);
    background: color-mix(in srgb, var(--rb-sidebar-active) 12%, transparent);
    box-shadow: inset 2px 0 0 var(--rb-sidebar-active);
}

.rb-side-link svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.65;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-side-section {
    padding: 12px 9px 4px;
    color: color-mix(in srgb, var(--rb-sidebar-text) 48%, transparent);
    font-size: 7px;
    font-weight: 700;
    text-transform: uppercase;
}

.rb-side-footer {
    padding: 8px 9px 11px;
    border-top: 1px solid color-mix(in srgb, var(--rb-sidebar-text) 10%, transparent);
}

.rb-pos button,
.rb-pos input,
.rb-pos select,
.rb-pos textarea {
    font: inherit;
}

.rb-pos-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
}

.rb-pos-heading {
    direction: rtl;
    text-align: left;
}

.rb-pos-heading h1 {
    margin: 0;
    color: var(--rb-text);
    font-size: 17px;
    font-weight: 900;
    line-height: 1.35;
}

.rb-pos-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    color: var(--rb-muted);
    font-size: 10px;
    font-weight: 600;
}

.rb-pos-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.rb-back-system {
    margin-inline-end: 5px;
    color: var(--rb-text);
    background: var(--rb-white);
}

.rb-back-system svg {
    transform: scaleX(-1);
}

.rb-btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 13px;
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    background: var(--rb-white);
    color: var(--rb-text);
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: .15s ease;
    direction: rtl;
}

.rb-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--rb-orange) 30%, var(--rb-border));
    box-shadow: 0 4px 10px rgba(16, 24, 39, .055);
}

.rb-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.rb-btn svg {
    width: 14px;
    height: 14px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-btn-primary {
    color: #fff;
    background: var(--rb-orange);
    border-color: var(--rb-orange);
}

.rb-btn-primary:hover:not(:disabled) {
    background: var(--rb-orange-hover);
    border-color: var(--rb-orange-hover);
}

.rb-btn-icon {
    font-size: 16px;
    font-weight: 400;
    line-height: 1;
}

.rb-pos-alert {
    margin-bottom: 12px;
    padding: 10px 14px;
    direction: rtl;
    text-align: right;
    color: var(--rb-danger);
    background: color-mix(in srgb, var(--rb-danger) 8%, var(--rb-white));
    border: 1px solid color-mix(in srgb, var(--rb-danger) 28%, var(--rb-border));
    border-radius: 8px;
    font-size: 11px;
}

.rb-pos-alert ul {
    margin: 5px 0 0;
    padding-right: 18px;
}

.rb-pos-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 392px;
    gap: 15px;
    align-items: start;
}

.rb-products-panel,
.rb-order-panel {
    min-width: 0;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: var(--rb-radius);
    box-shadow: var(--rb-shadow);
}

.rb-products-panel {
    min-height: calc(100dvh - 118px);
    padding: 14px;
}

.rb-products-toolbar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 150px 150px;
    gap: 9px;
    align-items: center;
    margin-bottom: 11px;
}

.rb-search {
    height: 37px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    transition: .15s ease;
}

.rb-search:focus-within {
    border-color: color-mix(in srgb, var(--rb-orange) 55%, var(--rb-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rb-orange) 10%, transparent);
}

.rb-search svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    fill: none;
    stroke: var(--rb-muted);
    stroke-width: 1.7;
    stroke-linecap: round;
}

.rb-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--rb-text);
    font-size: 12px;
    direction: rtl;
}

.rb-search input::placeholder {
    color: var(--rb-muted);
}

.rb-control {
    width: 100%;
    height: 37px;
    min-width: 0;
    padding: 0 10px;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 6px;
    outline: none;
    font-size: 11px;
    font-weight: 650;
    direction: rtl;
    transition: .15s ease;
}

.rb-control:focus {
    border-color: color-mix(in srgb, var(--rb-orange) 55%, var(--rb-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rb-orange) 10%, transparent);
}

.rb-category-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    overflow-x: auto;
    padding: 1px 0 10px;
    scrollbar-width: thin;
}

.rb-category-tab {
    flex: 0 0 auto;
    min-height: 25px;
    padding: 0 9px;
    color: var(--rb-muted);
    background: transparent;
    border: 0;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-category-tab:hover {
    color: var(--rb-orange);
    background: var(--rb-orange-soft);
}

.rb-category-tab.active {
    color: #fff;
    background: var(--rb-orange);
}

.rb-product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.rb-product-card {
    position: relative;
    min-width: 0;
    min-height: 236px;
    overflow: hidden;
    padding: 0;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 12px;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--rb-text) 4%, transparent);
    text-align: right;
    cursor: pointer;
    transition: .14s ease;
}

.rb-product-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
    box-shadow: 0 7px 16px color-mix(in srgb, var(--rb-text) 8%, transparent);
}

.rb-product-card:focus-visible,
.rb-load-more:focus-visible {
    outline: 3px solid var(--rb-orange);
    outline-offset: 2px;
}

.rb-product-image {
    height: 145px;
    display: grid;
    place-items: center;
    overflow: hidden;
    padding: 12px;
    background: color-mix(in srgb, var(--rb-orange) 5%, var(--rb-white));
    border-bottom: 1px solid color-mix(in srgb, var(--rb-orange) 9%, var(--rb-border));
}

.rb-product-image img {
    width: 100%;
    height: 100%;
    max-width: 100%;
    margin: auto;
    object-fit: contain;
    object-position: center;
    display: block;
    background: transparent;
    filter: drop-shadow(0 7px 8px rgba(15, 23, 42, .10));
}

.rb-product-placeholder {
    display: flex;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    color: var(--rb-muted);
    font-size: 10px;
}

.rb-product-placeholder[hidden] { display: none; }

.rb-product-placeholder svg {
    width: 36px;
    height: 36px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.4;
}

.rb-product-info {
    padding: 10px 12px 12px;
    direction: rtl;
    text-align: right;
}

.rb-product-meta-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
    min-width: 0;
    color: var(--rb-muted);
    font-size: 10px;
}

.rb-product-sku {
    overflow: hidden;
    max-width: 48%;
    direction: ltr;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-product-name {
    display: block;
    overflow: hidden;
    color: var(--rb-text);
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
    min-height: 36px;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.rb-product-description {
    display: block;
    overflow: hidden;
    color: var(--rb-muted);
    font-size: 10px;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-product-options-label {
    color: var(--rb-orange);
    font-size: 10px;
    font-weight: 800;
}

.rb-product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-top: 9px;
    padding-top: 8px;
    border-top: 1px solid var(--rb-border);
    direction: rtl;
}

.rb-product-price {
    color: var(--rb-orange);
    font-size: 14px;
    font-weight: 900;
    direction: ltr;
}

.rb-product-plus {
    width: 18px;
    height: 18px;
    display: grid;
    place-items: center;
    flex: 0 0 18px;
    color: #fff;
    background: var(--rb-orange);
    border-radius: 50%;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
}

.rb-product-qty-badge {
    position: absolute;
    top: 7px;
    right: 7px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--rb-orange);
    border: 2px solid #fff;
    border-radius: 999px;
    box-shadow: 0 2px 7px rgba(0, 0, 0, .12);
    font-size: 9px;
    font-weight: 900;
}

.rb-product-qty-badge[hidden] {
    display: none;
}

.rb-products-empty {
    grid-column: 1 / -1;
    min-height: 260px;
    display: grid;
    place-items: center;
    color: var(--rb-muted);
    font-size: 13px;
    direction: rtl;
    text-align: center;
    line-height: 1.8;
}

.rb-products-empty button {
    display: block;
    margin: 10px auto 0;
    padding: 7px 14px;
    color: var(--rb-orange);
    background: var(--rb-orange-soft);
    border: 1px solid var(--rb-orange);
    border-radius: 6px;
    cursor: pointer;
}

.rb-load-more {
    display: block;
    margin: 14px auto 0;
    padding: 9px 18px;
    color: var(--rb-orange);
    background: var(--rb-orange-soft);
    border: 1px solid var(--rb-orange);
    border-radius: 7px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
}

.rb-load-more[hidden] { display: none; }

.rb-option-group { margin-bottom: 18px; }
.rb-option-group h3 { margin: 0 0 8px; font-size: 12px; }
.rb-option-group p { margin: 0 0 8px; color: var(--rb-muted); font-size: 10px; }
.rb-option-choice {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 10px;
    border: 1px solid var(--rb-border);
    border-radius: 7px;
    margin-bottom: 5px;
    cursor: pointer;
    font-size: 12px;
}
.rb-option-choice:has(input:checked) { border-color: var(--rb-orange); background: var(--rb-orange-soft); }
.rb-option-choice span { flex: 1; }
.rb-option-choice small { color: var(--rb-orange); font-size: 11px; }
.rb-option-choice select { width: 65px; height: 29px; }
.rb-options-error { color: var(--rb-danger); font-size: 11px; font-weight: 700; margin-bottom: 10px; }

.rb-products-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
    padding-top: 10px;
    color: var(--rb-muted);
    border-top: 1px solid var(--rb-border);
    font-size: 9px;
    direction: rtl;
}

.rb-products-footer b {
    color: var(--rb-text);
}

.rb-order-panel {
    position: sticky;
    top: 76px;
    overflow: hidden;
}

.rb-order-top-controls {
    padding: 12px 12px 10px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-existing-search {
    margin-bottom: 8px;
}

.rb-order-selects {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
}

.rb-order-card {
    background: var(--rb-white);
}

.rb-order-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 12px 8px;
}

.rb-order-title {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--rb-text);
    font-size: 10px;
    direction: rtl;
}

.rb-order-bag {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    color: var(--rb-text);
}

.rb-order-bag svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-order-status-pill {
    padding: 3px 7px;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border-radius: 999px;
    font-size: 8.5px;
    font-weight: 750;
    direction: rtl;
}

.rb-cart-scroll {
    min-height: 240px;
    max-height: 380px;
    overflow-y: auto;
    padding: 0 12px;
    scrollbar-width: thin;
}

.rb-cart-empty {
    min-height: 230px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--rb-muted);
    text-align: center;
    direction: rtl;
}

/* Important: author CSS can override the browser [hidden] rule. */
.rb-cart-empty[hidden] {
    display: none !important;
}

.rb-cart-empty-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    margin-bottom: 7px;
    color: color-mix(in srgb, var(--rb-muted) 60%, transparent);
    background: var(--rb-soft);
    border-radius: 50%;
}

.rb-cart-empty-icon svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
}

.rb-cart-empty strong {
    color: var(--rb-text);
    font-size: 10px;
}

.rb-cart-empty span {
    margin-top: 3px;
    font-size: 8.5px;
}

.rb-cart-item {
    padding: 9px 0;
    border-top: 1px solid var(--rb-border);
}

.rb-cart-item:first-child {
    border-top: 0;
}

.rb-cart-item-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: start;
}

.rb-cart-item-name {
    min-width: 0;
    direction: rtl;
    text-align: left;
}

.rb-cart-item-name strong {
    display: block;
    overflow: hidden;
    color: var(--rb-text);
    font-size: 9.5px;
    font-weight: 850;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.rb-cart-options {
    display: block;
    margin-top: 3px;
    color: var(--rb-muted);
    font-size: 10px;
}

.rb-cart-item-price-line {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 5px;
    color: var(--rb-orange);
    font-size: 8.5px;
    font-weight: 800;
    direction: ltr;
}

.rb-cart-remove {
    width: 23px;
    height: 23px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-danger);
    background: var(--rb-white);
    border: 1px solid color-mix(in srgb, var(--rb-danger) 25%, var(--rb-border));
    border-radius: 50%;
    cursor: pointer;
}

.rb-cart-remove svg {
    width: 11px;
    height: 11px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
}

.rb-cart-item-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 7px;
}

.rb-qty-control {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    direction: ltr;
}

.rb-qty-btn {
    width: 19px;
    height: 19px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
}

.rb-qty-btn:hover {
    color: var(--rb-orange);
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
}

.rb-qty-value {
    min-width: 19px;
    text-align: center;
    color: var(--rb-text);
    font-size: 9px;
    font-weight: 850;
}

.rb-add-note {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    color: var(--rb-muted);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 4px;
    font-size: 7.8px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-add-note svg {
    width: 10px;
    height: 10px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.rb-item-note-wrap {
    margin-top: 7px;
}

.rb-item-note-wrap[hidden] {
    display: none;
}

.rb-item-note {
    width: 100%;
    height: 32px;
    padding: 0 8px;
    color: var(--rb-text);
    background: var(--rb-soft-2);
    border: 1px solid var(--rb-border);
    border-radius: 5px;
    outline: none;
    font-size: 8.5px;
    direction: rtl;
}

.rb-more-details {
    width: calc(100% - 24px);
    min-height: 34px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 12px 0;
    padding: 0 9px;
    color: var(--rb-muted);
    background: var(--rb-soft-2);
    border: 1px solid var(--rb-border);
    border-radius: 5px;
    font-size: 8.7px;
    font-weight: 750;
    cursor: pointer;
    direction: rtl;
}

.rb-more-details > span:first-child {
    display: flex;
    align-items: center;
    gap: 6px;
}

.rb-more-details svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.rb-summary {
    padding: 11px 12px 9px;
    border-top: 1px solid var(--rb-border);
    margin-top: 10px;
}

.rb-summary-row,
.rb-summary-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    direction: rtl;
}

.rb-summary-row {
    margin-bottom: 6px;
    color: var(--rb-muted);
    font-size: 8.7px;
}

.rb-summary-row strong {
    color: var(--rb-text);
    font-weight: 800;
    direction: ltr;
}

.rb-summary-total {
    padding-top: 7px;
    color: var(--rb-text);
    border-top: 1px dashed var(--rb-border);
    font-size: 10px;
    font-weight: 900;
}

.rb-summary-total strong {
    font-size: 12px;
    direction: ltr;
}

.rb-order-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
    padding: 10px 12px 12px;
    border-top: 1px solid var(--rb-border);
}

.rb-action {
    min-height: 72px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 9px 7px;
    border: 1px solid transparent;
    border-radius: 10px;
    font-size: 11px;
    line-height: 1.35;
    text-align: center;
    font-weight: 850;
    cursor: pointer;
    transition: .14s ease;
}

.rb-action:hover {
    filter: brightness(.97);
    transform: translateY(-1px);
}

.rb-action svg {
    width: 19px;
    height: 19px;
    flex: 0 0 19px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rb-action-title {font-weight:900;font-size:12px}
.rb-action-subtitle {font-size:10px;font-weight:650;opacity:.85}
.rb-action:focus-visible {outline:2px solid var(--rb-orange);outline-offset:2px}

.rb-action-kot {
    color: var(--rb-text);
    background: var(--rb-white);
    border-color: var(--rb-border);
}

.rb-action-draft {
    color: var(--rb-text);
    background: var(--rb-white);
    border-color: var(--rb-border);
}

.rb-action-payment {
    color: #fff;
    background: var(--rb-orange);
    border-color: var(--rb-orange);
}

.rb-action-print {
    color: #fff;
    background: var(--rb-green);
    border-color: var(--rb-green);
}

/* POS customer quick-add, scoped to the existing checkout modal */
.rb-customer-field-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px}
.rb-customer-field-head label{margin:0}
.rb-quick-customer-button{font-size:.8rem;min-height:36px;padding:6px 12px;flex:0 0 auto}
.rb-modal.rb-quick-customer-visible{z-index:10060}
.rb-quick-customer-dialog{width:min(760px,96vw);max-height:90dvh;overflow-y:auto}.rb-quick-customer-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.rb-quick-customer-dialog [hidden]{display:none!important}.rb-quick-wide{grid-column:1/-1}.rb-quick-credit-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.rb-quick-branch-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rb-quick-branch-options label{display:flex;gap:6px;align-items:center}@media(max-width:600px){.rb-quick-customer-fields,.rb-quick-credit-fields,.rb-quick-branch-options{grid-template-columns:1fr}.rb-quick-wide{grid-column:1}}
.rb-quick-customer-error{margin:12px 0 0;color:#b42318;font-weight:800;font-size:.85rem;line-height:1.5}
@media(max-width:390px){.rb-customer-field-head{align-items:flex-start}.rb-quick-customer-button{font-size:.72rem;padding:6px 9px}}
#rbTableSessionAccountLink{margin-top:9px;max-width:max-content}
#rbTableSessionAccountLink[hidden]{display:none!important}
/* Modal */
.rb-modal {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: grid;
    place-items: center;
    padding: 20px;
    direction: rtl;
}

.rb-modal[hidden] {
    display: none;
}

.rb-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .38);
    backdrop-filter: blur(2px);
}

.rb-modal-dialog {
    position: relative;
    z-index: 1;
    width: min(720px, 96vw);
    max-height: min(90dvh, 900px);
    overflow: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 10px;
    box-shadow: 0 24px 70px rgba(16, 24, 39, .2);
}

.rb-modal-head,
.rb-modal-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
}

.rb-modal-head {
    justify-content: space-between;
    border-bottom: 1px solid var(--rb-border);
}

.rb-modal-head h2 {
    margin: 0;
    color: var(--rb-text);
    font-size: 14px;
    font-weight: 900;
}

.rb-modal-head p {
    margin: 3px 0 0;
    color: var(--rb-muted);
    font-size: 9px;
}

.rb-modal-close {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border: 0;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
}

.rb-modal-body {
    padding: 15px 16px;
    min-width: 0;
    overflow-wrap: anywhere;
}

.rb-confirm-dialog {
    width: min(430px, 96vw);
}

.rb-confirm-dialog .rb-modal-body {
    font-size: .91rem;
    line-height: 1.85;
}

.rb-modal :focus-visible {
    outline: 2px solid var(--rb-orange);
    outline-offset: 2px;
}

.rb-modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 11px;
}

.rb-field-wide {
    grid-column: 1 / -1;
}

.rb-field label {
    display: block;
    margin-bottom: 5px;
    color: var(--rb-muted);
    font-size: 9px;
    font-weight: 800;
}

.rb-file-control {
    padding-top: 7px;
}

.rb-textarea {
    height: auto;
    min-height: 75px;
    padding: 9px 10px;
    resize: vertical;
}

.rb-modal-foot {
    justify-content: flex-end;
    border-top: 1px solid var(--rb-border);
}

.rb-modal-total {
    margin-left: auto;
    text-align: right;
}

.rb-modal-total span,
.rb-modal-total strong {
    display: block;
}

.rb-modal-total span {
    color: var(--rb-muted);
    font-size: 8.5px;
}

.rb-modal-total strong {
    margin-top: 2px;
    color: var(--rb-orange);
    font-size: 14px;
    direction: ltr;
}

/* Drawer */
.rb-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2147482100;
    background: rgba(15, 23, 42, .3);
    backdrop-filter: blur(2px);
}

.rb-drawer-backdrop[hidden] {
    display: none;
}

.rb-drawer {
    /* The drawer sits outside .rb-pos, so it needs its own theme tokens. */
    --rb-orange: var(--theme-accent, var(--gold, #C98516));
    --rb-text: var(--theme-text, var(--text-main, #172435));
    --rb-muted: var(--theme-text-muted, var(--text-muted, #687482));
    --rb-border: var(--theme-border, var(--border, #DDE2E7));
    --rb-bg: var(--theme-bg, var(--bg, #F5F6F8));
    --rb-white: var(--theme-surface, var(--card-bg, #FFFFFF));
    --rb-soft: color-mix(in srgb, var(--rb-white) 88%, var(--rb-bg) 12%);
    --rb-danger: var(--theme-danger, var(--error, #E22929));
    position: fixed;
    top: 0;
    right: 0;
    z-index: 2147482200;
    width: min(390px, 94vw);
    height: 100dvh;
    display: flex;
    flex-direction: column;
    color: var(--rb-text);
    background: var(--rb-white);
    border-left: 1px solid var(--rb-border);
    box-shadow: -22px 0 55px rgba(15, 23, 42, .16);
    transform: translateX(102%);
    transition: transform .2s ease;
    direction: rtl;
}

.rb-drawer.is-open {
    transform: translateX(0);
}

.rb-drawer-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 15px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-drawer-head h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 900;
}

.rb-drawer-head p {
    margin: 3px 0 0;
    color: var(--rb-muted);
    font-size: 12px;
}

.rb-drawer-head button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    padding: 0;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border: 0;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
}

.rb-drawer-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--rb-border);
}

.rb-drawer-section {
    padding: 11px 12px;
    overflow: visible;
}

.rb-drawer-section:last-child {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.rb-drawer-section-title {
    margin-bottom: 8px;
    color: var(--rb-muted);
    font-size: 12px;
    font-weight: 850;
}

.rb-server-order-card,
.rb-local-draft-card {
    display: block;
    margin-bottom: 7px;
    padding: 10px;
    color: var(--rb-text);
    background: var(--rb-white);
    border: 1px solid var(--rb-border);
    border-radius: 7px;
    text-decoration: none;
}

.rb-server-order-card:hover,
.rb-local-draft-card:hover {
    border-color: color-mix(in srgb, var(--rb-orange) 38%, var(--rb-border));
}

.rb-server-order-card summary {
    cursor: pointer;
    list-style-position: inside;
}

.rb-qr-order-items {
    margin: 10px 0 0;
    padding: 8px 18px 0 0;
    border-top: 1px solid var(--rb-border);
    font-size: 13px;
}

.rb-qr-order-open {
    display: inline-block;
    margin-top: 8px;
    color: var(--rb-orange);
    font-size: 13px;
    font-weight: 800;
}

.rb-server-order-card[hidden] {
    display: none;
}

.rb-server-order-top,
.rb-local-draft-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.rb-server-order-top strong,
.rb-local-draft-top strong {
    font-size: 14px;
    font-weight: 900;
}

.rb-server-order-top span,
.rb-local-draft-top span {
    padding: 2px 6px;
    color: var(--rb-muted);
    background: var(--rb-soft);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}

.rb-server-order-meta,
.rb-local-draft-meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 7px;
    color: var(--rb-muted);
    font-size: 12px;
}

.rb-local-draft-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}

.rb-local-draft-actions button {
    min-height: 29px;
    padding: 0 9px;
    border-radius: 5px;
    font-size: 8px;
    font-weight: 800;
    cursor: pointer;
}

.rb-draft-restore {
    color: #fff;
    background: var(--rb-orange);
    border: 1px solid var(--rb-orange);
}

.rb-draft-delete {
    color: var(--rb-danger);
    background: color-mix(in srgb, var(--rb-danger) 8%, var(--rb-white));
    border: 1px solid color-mix(in srgb, var(--rb-danger) 25%, var(--rb-border));
}

.rb-drawer-empty {
    padding: 24px 10px;
    color: var(--rb-muted);
    text-align: center;
    font-size: 13px;
}

/* Cashier screen ergonomics: readable touch targets, independent scroll areas and safe heights. */
@media (min-width: 821px) {
    .rb-pos-workspace {
        padding: clamp(10px, 1.4vw, 20px);
    }
    .rb-pos-shell {
        grid-template-columns: minmax(0, 1fr) clamp(330px, 29vw, 420px);
        align-items: start;
    }
    .rb-order-panel {
        top: 8px;
        max-height: calc(100dvh - 16px);
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .rb-products-panel {
        min-height: calc(100dvh - 110px);
    }
    .rb-product-grid {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 175px), 1fr));
    }
    .rb-control, .rb-search, .rb-category-tab {
        min-height: 42px;
    }
    .rb-control, .rb-search input {
        font-size: 13px;
    }
}
@media (min-width: 821px) and (max-height: 760px) {
    .rb-pos-header { margin-bottom: 8px; }
    .rb-pos-workspace { padding: 8px 12px; }
    .rb-order-panel { max-height: calc(100dvh - 12px); }
    .rb-cart-scroll { min-height: 100px; max-height: 24dvh; }
}
@media (min-width: 821px) and (max-width: 1180px) {
    .rb-pos-shell { grid-template-columns: minmax(0, 1fr) 330px; }
    .rb-products-toolbar { grid-template-columns: minmax(150px, 1fr) 125px 125px; }
    .rb-product-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 145px), 1fr)); }
    .rb-product-image { height: 116px; }
    .rb-product-card { min-height: 210px; }
}
@media (max-width: 820px) {
    .rb-pos { overflow-y: auto; overscroll-behavior: contain; }
    .rb-pos-header-actions .rb-btn { min-height: 42px; }
    .rb-product-card { min-height: 210px; }
    .rb-product-image { height: 120px; }
    .rb-control, .rb-search { min-height: 44px; }
    .rb-control, .rb-search input { font-size: 14px; }
}
@media (max-width: 560px) {
    .rb-product-grid { gap: 8px; }
    .rb-products-panel { padding: 10px; min-height: 0; }
    .rb-product-card { min-height: 188px; }
    .rb-product-image { height: 103px; }
}

/* Responsive */
@media (max-width: 1320px) {
    .rb-pos {
        grid-template-columns: 1fr;
    }

    .rb-pos-workspace {
        padding: 18px 16px 16px;
    }

    .rb-pos-shell {
        grid-template-columns: minmax(0, 1fr) 350px;
        gap: 12px;
    }

    .rb-product-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .rb-side-user {
        grid-template-columns: 28px minmax(0, 1fr);
    }
}

@media (max-width: 1080px) {
    .rb-pos {
        grid-template-columns: 1fr;
    }

    .rb-side-brand {
        justify-content: center;
        padding: 0;
    }

    .rb-side-brand > strong,
    .rb-side-user > div:last-child,
    .rb-side-link span,
    .rb-side-section {
        display: none;
    }

    .rb-side-user {
        display: flex;
        justify-content: center;
        padding-inline: 0;
    }

    .rb-side-link {
        justify-content: center;
        padding: 0;
    }

    .rb-pos-shell {
        grid-template-columns: minmax(0, 1fr) 330px;
    }

    .rb-product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 820px) {
    .rb-pos {
        grid-template-columns: 1fr;
        overflow: auto;
    }

    .rb-pos-sidebar {
        display: none;
    }

    .rb-pos-workspace {
        height: auto;
        min-height: 100dvh;
        overflow: visible;
        padding: 12px;
    }

    .rb-pos-shell {
        grid-template-columns: 1fr;
    }

    .rb-order-panel {
        position: static;
        max-height: none;
    }

    .rb-pos-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .rb-pos-heading {
        text-align: right;
    }

    .rb-pos-header-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .rb-products-toolbar {
        grid-template-columns: 1fr 1fr;
    }

    .rb-search-products {
        grid-column: 1 / -1;
    }

    .rb-product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .rb-pos-header-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .rb-btn {
        width: 100%;
    }

    .rb-products-toolbar {
        grid-template-columns: 1fr;
    }

    .rb-search-products {
        grid-column: auto;
    }

    .rb-product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rb-order-selects,
    .rb-modal-grid {
        grid-template-columns: 1fr;
    }

    .rb-field-wide {
        grid-column: auto;
    }

    .rb-order-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
        padding-inline: 8px;
    }
    .rb-action {min-height:68px;padding:8px 3px}
    .rb-action-title {font-size:11px}
    .rb-action-subtitle {font-size:9px}

    .rb-modal-foot {
        align-items: stretch;
        flex-direction: column;
    }

    .rb-modal {
        align-items: end;
        padding: max(.5rem, env(safe-area-inset-top, 0px))
            max(.5rem, env(safe-area-inset-right, 0px))
            max(.5rem, env(safe-area-inset-bottom, 0px))
            max(.5rem, env(safe-area-inset-left, 0px));
    }

    .rb-modal-dialog {
        width: 100%;
        max-height: calc(100dvh - 1rem - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
        border-radius: 18px 18px 12px 12px;
    }

    .rb-modal-foot .rb-btn {
        min-height: 44px;
    }

    .rb-modal :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
        min-height: 44px;
        font-size: 16px;
    }

    .rb-drawer-head button {
        min-width: 44px;
        min-height: 44px;
    }

    .rb-modal-total {
        margin-left: 0;
        margin-bottom: 4px;
    }
}
.rb-menu-management-link { color: var(--rb-orange); font-size: 11px; font-weight: 800; text-decoration: underline; }
.rb-menu-management-link:focus-visible { outline: 2px solid var(--rb-orange); outline-offset: 3px; }
/* Final viewport rules take precedence over legacy breakpoint selectors. */
@media (min-width: 821px) {
    .rb-product-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 175px), 1fr)); }
    .rb-order-panel { max-height: calc(100dvh - 16px); overflow-y: auto; top: 8px; }
}
@media (min-width: 821px) and (max-width: 1180px) {
    .rb-product-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 145px), 1fr)); }
}
@media (max-width: 820px) {
    .rb-pos-workspace { min-width: 0; width: 100%; }
    .rb-product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .rb-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
/* Touch-first controls: avoid accidental adjacent quantity taps. */
.rb-pos .rb-qty-btn {width:44px;height:44px;min-width:44px;min-height:44px;font-size:20px;border-radius:12px;touch-action:manipulation}
.rb-pos .rb-qty-value {min-width:30px;font-size:15px}
.rb-pos .rb-add-note {min-height:44px;padding:8px 12px;font-size:12px;touch-action:manipulation}
.rb-pos .rb-cart-remove {min-width:44px;min-height:44px;display:grid;place-items:center;touch-action:manipulation}
.rb-pos .rb-more-details {min-height:44px;font-size:12px}
.rb-pos .rb-quick-cash-btn,.rb-pos .rb-quick-cash-expand{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:1px solid var(--rb-border);border-radius:9px;padding:8px 14px;margin-top:8px;background:var(--rb-orange-soft);color:var(--rb-text);font-weight:800;cursor:pointer}
.rb-pos .rb-quick-cash-expand{background:var(--rb-white)}
.rb-pos .rb-quick-cash-expand[hidden]{display:none!important}
.rb-pos #rbCheckoutModal.rb-quick-cash [data-quick-cash-optional]{display:none!important}
.rb-pos .rb-mobile-cart-bar{display:none}
@media(max-width:820px){
  .rb-pos .rb-mobile-cart-bar{position:fixed;bottom:0;left:0;right:0;z-index:900;display:grid;grid-template-columns:minmax(0,1fr) minmax(128px,36%);gap:10px;padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px));background:var(--rb-white);border-top:1px solid var(--rb-border);box-shadow:0 -4px 18px rgba(0,0,0,.1);direction:rtl}
  .rb-pos .rb-mobile-cart-bar button{min-height:52px;border-radius:10px;padding:6px 12px;touch-action:manipulation;font-weight:850}
  .rb-pos .rb-mobile-cart-open{border:1px solid var(--rb-border);background:var(--rb-soft);color:var(--rb-text);display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
  .rb-pos .rb-mobile-cart-open small{font-size:11px}
  .rb-pos .rb-mobile-checkout{border:0;background:var(--rb-orange);color:#fff}
  .rb-pos .rb-mobile-checkout:disabled{opacity:.5;cursor:not-allowed}
  .rb-pos .rb-pos-workspace{padding-bottom:calc(100px + env(safe-area-inset-bottom,0px))}
  .rb-pos .rb-order-panel{scroll-margin-top:10px}
}
@media(max-width:390px){.rb-pos .rb-mobile-cart-bar{grid-template-columns:minmax(0,1fr) 118px;gap:6px;padding-inline:8px}.rb-pos .rb-mobile-cart-open small{display:none}}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rbRoot = document.getElementById('rbPosRoot');
    const rbDrawerBackdropNode = document.getElementById('rbDrawerBackdrop');
    const rbDrawerNode = document.getElementById('rbOrdersDrawer');

    document.documentElement.classList.add('rb-pos-mode');
    document.body.classList.add('rb-pos-mode');

    // Move the POS to <body> so the normal ERP sidebar/header cannot squeeze or overlay it.
    if (rbRoot && rbRoot.parentElement !== document.body) {
        document.body.appendChild(rbRoot);
    }
    if (rbDrawerBackdropNode && rbDrawerBackdropNode.parentElement !== document.body) {
        document.body.appendChild(rbDrawerBackdropNode);
    }
    if (rbDrawerNode && rbDrawerNode.parentElement !== document.body) {
        document.body.appendChild(rbDrawerNode);
    }
    let products = @json($productPayload);
    const catalogUrl = @json(route('restaurant.pos.catalog'));
    const qrOrdersUrl = @json(route('restaurant.pos.qr-orders'));
    const draftOrdersUrl = @json(route('restaurant.pos.drafts'));
    const receiptSearchUrl = @json(route('restaurant.pos.receipt-search'));
    const queuedLinesUrlPattern = @json(route('restaurant.pos.orders.queued-lines', ['order' => '__ORDER__']));
    const queuedCancelUrlPattern = @json(route('restaurant.pos.orders.cancel-queued-line', ['order' => '__ORDER__']));
    const cartAvailabilityUrl = @json(route('restaurant.pos.cart-availability'));
    const draftEditUrlPattern = @json(route('restaurant.pos.drafts.items.update', ['order' => '__ORDER__']));
    const draftCancelUrlPattern = @json(route('restaurant.pos.drafts.cancel', ['order' => '__ORDER__']));
    const cashierPosUrl = @json(route('restaurant.pos.index', ['location_id' => $location->id]));
    @can('customers.create')
    const quickCustomerUrl = @json(route('restaurant.pos.customers.quick-store'));
    @else
    const quickCustomerUrl = null;
    @endcan
    const channelRules = @json($channelDiscountRules);
    const categories = @json($catalogFacets['categories']);
    const brands = @json($catalogFacets['brands']);
    const oldItems = @json(old('items', []));
    const locationId = @json((int) $location->id);
    const activeSessionAccountUrls = @json($activeSessionAccountUrls);
    const cashierUserId = @json((int) auth()->id());
    const cashierName = @json($cashierName);
    const locationName = @json($location->name);

    const form = document.getElementById('restaurantPosForm');
    const actionInput = document.getElementById('rbPosAction');

    const productGrid = document.getElementById('rbProductGrid');
    const productSearch = document.getElementById('rbProductSearch');
    const categoryTabs = document.getElementById('rbCategoryTabs');
    const categorySelect = document.getElementById('rbCategorySelect');
    const brandSelect = document.getElementById('rbBrandSelect');
    const visibleProductCount = document.getElementById('rbVisibleProductCount');
    const matchingProductCount = document.getElementById('rbMatchingProductCount');
    const loadMoreButton = document.getElementById('rbLoadMore');
    const productOptionsModal = document.getElementById('rbProductOptionsModal');
    const productOptionsBody = document.getElementById('rbProductOptionsBody');
    const productOptionsPrice = document.getElementById('rbProductOptionsPrice');
    const addConfiguredProductButton = document.getElementById('rbAddConfiguredProduct');

    const cartItems = document.getElementById('rbCartItems');
    const cartEmpty = document.getElementById('rbCartEmpty');
    const hiddenItems = document.getElementById('rbHiddenItems');
    const cartCount = document.getElementById('rbCartCount');
    const subtotalEl = document.getElementById('rbSubtotal');
    const discountAmountEl = document.getElementById('rbDiscountAmount');
    const channelDiscountEl = document.getElementById('rbChannelDiscount');
    const grandTotalEl = document.getElementById('rbGrandTotal');
    const checkoutTotalEl = document.getElementById('rbCheckoutTotal');

    const serviceType = document.getElementById('rbServiceType');
    const tableSelect = document.getElementById('rbTableSelect');
    const tableSessionAccountLink = document.getElementById('rbTableSessionAccountLink');
    function updateTableSessionAccountLink() {
        const accountUrl = activeSessionAccountUrls[String(tableSelect?.value || '')];
        if (!tableSessionAccountLink) return;
        tableSessionAccountLink.hidden = !accountUrl;
        if (accountUrl) tableSessionAccountLink.href = accountUrl;
    }
    tableSelect?.addEventListener('change', updateTableSessionAccountLink);
    updateTableSessionAccountLink();
    const guestCountGroup = document.getElementById('rbGuestCountGroup');

    const discountType = document.getElementById('rbDiscountType');
    const discountValue = document.getElementById('rbDiscountValue');
    const discountReasonGroup = document.getElementById('rbDiscountReasonGroup');
    const discountReason = document.getElementById('rbDiscountReason');
    const sensitiveDiscountNotice = document.getElementById('rbSensitiveDiscountNotice');
    const sensitiveDiscountPercentage = @json((float) config('tish_pos.manager_discount_percentage', 10));
    const sensitiveDiscountFixed = @json((float) config('tish_pos.manager_discount_fixed', 20));
    const cashierCanApproveDiscount = @json($cashierCanApproveDiscount);
    function syncDiscountReason() {
        const needed = ['percentage', 'fixed'].includes(discountType.value)
            && Number(discountValue.value || 0) > 0;
        discountReasonGroup.hidden = !needed;
        discountReason.required = needed;
        sensitiveDiscountNotice.hidden = !needed || cashierCanApproveDiscount
            || !(discountType.value === 'percentage'
                ? Number(discountValue.value) > sensitiveDiscountPercentage
                : Number(discountValue.value) > sensitiveDiscountFixed);
    }
    [discountType, discountValue].forEach(field => {
        field?.addEventListener('input', syncDiscountReason);
        field?.addEventListener('change', syncDiscountReason);
    });
    syncDiscountReason();

    const splitTenderToggle = document.getElementById('rbSplitTenderToggle');
    const splitTenderOption = document.getElementById('rbSplitTenderOption');
    const splitTenderPanel = document.getElementById('rbSplitTenderPanel');
    const splitTenderBalance = document.getElementById('rbSplitTenderBalance');
    const splitMethods = [...form.querySelectorAll('[data-split-method]')];
    const splitAmounts = [...form.querySelectorAll('[data-split-amount]')];
    const splitReferences = [...form.querySelectorAll('[data-split-reference]')];
    const splitConfirmations = [...form.querySelectorAll('[data-split-confirm]')];
    const splitMode = () => splitTenderToggle.checked && paymentArrangement.value === 'pay_now';
    function renderSplitTender() {
        if (!splitMode()) return;
        const cents = Math.round(getTotals().total * 100);
        const collected = splitAmounts.reduce((sum, field) => sum + Math.round(Number(field.value || 0) * 100), 0);
        splitTenderBalance.textContent = 'الإجمالي ' + money(cents / 100)
            + ' — الموزع ' + money(collected / 100)
            + ' — المتبقي ' + money((cents - collected) / 100);
        splitTenderBalance.style.color = cents === collected ? '#167044' : '#aa1d19';
    }
    splitTenderToggle.addEventListener('change', syncPayment);
    [...splitMethods, ...splitAmounts, ...splitReferences, ...splitConfirmations]
        .forEach(field => {
            field.addEventListener('input', renderSplitTender);
            field.addEventListener('change', renderSplitTender);
        });

    const paymentArrangement = document.getElementById('rbPaymentArrangement');
    const paymentMethod = document.getElementById('rbPaymentMethod');
    const paymentMethodGroup = document.getElementById('rbPaymentMethodGroup');
    const paidAmountGroup = document.getElementById('rbPaidAmountGroup');
    const referenceGroup = document.getElementById('rbReferenceGroup');
    const senderNameGroup = document.getElementById('rbSenderNameGroup');
    const senderPhoneGroup = document.getElementById('rbSenderPhoneGroup');
    const senderAccountGroup = document.getElementById('rbSenderAccountGroup');
    const proofGroup = document.getElementById('rbProofGroup');
    const manualReceiptGroup = document.getElementById('rbManualReceiptGroup');
    const manualReceipt = document.getElementById('rbManualReceipt');

    const referenceInput = form.querySelector('[name="reference_number"]');
    const senderNameInput = form.querySelector('[name="sender_name"]');
    const senderPhoneInput = form.querySelector('[name="sender_phone"]');
    const senderAccountInput = form.querySelector('[name="sender_account_number"]');
    const proofInput = form.querySelector('[name="payment_proof"]');

    const checkoutModal = document.getElementById('rbCheckoutModal');
    const checkoutContinueBtn = document.getElementById('rbCheckoutContinueBtn');
    const quickCashBtn = document.getElementById('rbQuickCashBtn');
    const quickCashExpand = document.getElementById('rbQuickCashExpand');
    const customerSelect = document.getElementById('rbCustomerSelect');
    const quickCustomerModal = document.getElementById('rbQuickCustomerModal');
    const quickCustomerOpen = document.getElementById('rbQuickCustomerOpen');
    const quickCustomerSave = document.getElementById('rbQuickCustomerSave');
    const quickCustomerName = document.getElementById('rbQuickCustomerName');
    const quickCustomerPhone = document.getElementById('rbQuickCustomerPhone');
    const quickCustomerError = document.getElementById('rbQuickCustomerError');
    const quickCustomerType = document.getElementById('rbQuickCustomerType');
    const quickCustomerScope = document.getElementById('rbQuickCustomerScope');
    const quickCustomerAllowCredit = document.getElementById('rbQuickCustomerAllowCredit');
    let quickCustomerSaving = false;

    function quickValue(id) {
        return document.getElementById(id)?.value?.trim() || '';
    }
    function syncQuickCustomerFields() {
        const isInstitution = quickCustomerType?.value !== 'individual';
        quickCustomerModal.querySelectorAll('[data-quick-institution]').forEach(el => {
            el.hidden = !isInstitution;
        });
        if (!isInstitution) {
            if (quickCustomerScope) quickCustomerScope.value = 'branch';
            if (quickCustomerAllowCredit) quickCustomerAllowCredit.checked = false;
        }
        const selected = isInstitution && quickCustomerScope?.value === 'selected';
        const locationGroup = document.getElementById('rbQuickCustomerLocations');
        if (locationGroup) locationGroup.hidden = !selected;
        const creditFields = document.getElementById('rbQuickCustomerCreditFields');
        if (creditFields) creditFields.hidden = !(isInstitution && quickCustomerAllowCredit?.checked);
    }
    quickCustomerType?.addEventListener('change', syncQuickCustomerFields);
    quickCustomerScope?.addEventListener('change', syncQuickCustomerFields);
    quickCustomerAllowCredit?.addEventListener('change', syncQuickCustomerFields);

    function closeQuickCustomer() {
        if (!quickCustomerModal || quickCustomerSaving) return;
        quickCustomerModal.hidden = true;
        quickCustomerModal.setAttribute('aria-hidden', 'true');
        quickCustomerModal.classList.remove('rb-quick-customer-visible');
        quickCustomerOpen?.focus();
    }
    quickCustomerOpen?.addEventListener('click', () => {
        quickCustomerModal.querySelectorAll('input:not([type="checkbox"]),textarea').forEach(field => {
            field.value = '';
        });
        quickCustomerModal.querySelectorAll('input[type="checkbox"]').forEach(field => {
            field.checked = false;
        });
        quickCustomerName.value = '';
        quickCustomerPhone.value = '';
        quickCustomerType.value = 'individual';
        if (quickCustomerScope) quickCustomerScope.value = 'branch';
        const terms = document.getElementById('rbQuickCustomerPaymentTerms');
        if (terms) terms.value = '0';
        const cycle = document.getElementById('rbQuickCustomerBillingCycle');
        if (cycle) cycle.value = 'immediate';
        syncQuickCustomerFields();
        quickCustomerError.hidden = true;
        quickCustomerError.textContent = '';
        quickCustomerModal.hidden = false;
        quickCustomerModal.setAttribute('aria-hidden', 'false');
        quickCustomerModal.classList.add('rb-quick-customer-visible');
        quickCustomerName.focus();
    });
    quickCustomerModal?.querySelectorAll('[data-close-quick-customer]').forEach(button => {
        button.addEventListener('click', closeQuickCustomer);
    });
    quickCustomerSave?.addEventListener('click', async () => {
        if (quickCustomerSaving || !quickCustomerUrl) return;
        const name = quickCustomerName.value.trim();
        const phone = quickCustomerPhone.value.trim();
        if (!name || !phone) {
            quickCustomerError.textContent = 'أدخل اسم العميل ورقم الجوال.';
            quickCustomerError.hidden = false;
            (!name ? quickCustomerName : quickCustomerPhone).focus();
            return;
        }
        quickCustomerSaving = true;
        quickCustomerSave.disabled = true;
        quickCustomerSave.textContent = 'جارٍ الحفظ...';
        quickCustomerError.hidden = true;
        try {
            const response = await fetch(quickCustomerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({
                    name, phone, location_id: locationId,
                    customer_type: quickCustomerType.value,
                    secondary_phone: quickValue('rbQuickCustomerSecondaryPhone') || null,
                    contact_person: quickCustomerType.value !== 'individual'
                        ? quickValue('rbQuickCustomerContactPerson') || null : null,
                    tax_number: quickCustomerType.value !== 'individual'
                        ? quickValue('rbQuickCustomerTaxNumber') || null : null,
                    address: quickValue('rbQuickCustomerAddress') || null,
                    notes: quickValue('rbQuickCustomerNotes') || null,
                    scope: quickCustomerType.value !== 'individual'
                        ? (quickCustomerScope?.value || 'branch') : 'branch',
                    location_ids: quickCustomerScope?.value === 'selected'
                        && quickCustomerType.value !== 'individual'
                        ? [...quickCustomerModal.querySelectorAll('[data-quick-location]:checked')]
                            .map(field => Number(field.value)) : [],
                    allow_credit: quickCustomerType.value !== 'individual'
                        && Boolean(quickCustomerAllowCredit?.checked),
                    ...(quickCustomerAllowCredit?.checked && quickCustomerType.value !== 'individual' ? {
                        credit_limit: quickValue('rbQuickCustomerCreditLimit') || null,
                        billing_cycle: quickValue('rbQuickCustomerBillingCycle') || 'immediate',
                        payment_terms_days: Number(quickValue('rbQuickCustomerPaymentTerms') || 0),
                    } : {}),
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                const firstError = Object.values(data.errors || {}).flat()[0];
                throw new Error(firstError || data.message || 'تعذر حفظ العميل.');
            }
            const customer = data.customer;
            const option = new Option(customer.name + ' — ' + customer.phone, String(customer.id), true, true);
            customerSelect.add(option);
            customerSelect.value = String(customer.id);
            customerSelect.dispatchEvent(new Event('change', {bubbles:true}));
            closeQuickCustomerAfterSave();
        } catch (error) {
            quickCustomerError.textContent = error.message || 'تعذر حفظ العميل.';
            quickCustomerError.hidden = false;
        } finally {
            quickCustomerSaving = false;
            quickCustomerSave.disabled = false;
            quickCustomerSave.textContent = 'حفظ العميل';
        }
    });
    function closeQuickCustomerAfterSave() {
        quickCustomerModal.hidden = true;
        quickCustomerModal.setAttribute('aria-hidden', 'true');
        quickCustomerModal.classList.remove('rb-quick-customer-visible');
        customerSelect.focus();
    }

    const newOrderBtn = document.getElementById('rbNewOrderBtn');
    const newOrderConfirm = document.getElementById('rbNewOrderConfirm');
    let resolveNewOrderConfirmation = null;

    function confirmNewOrder() {
        if (!newOrderConfirm) return Promise.resolve(false);

        return new Promise(resolve => {
            // A second tap must not open another unresolved confirmation.
            if (resolveNewOrderConfirmation) {
                resolve(false);
                return;
            }

            resolveNewOrderConfirmation = resolve;
            newOrderConfirm.hidden = false;
            newOrderConfirm.setAttribute('aria-hidden', 'false');
            newOrderConfirm.querySelector('[data-cancel-new-order]')?.focus();
        });
    }

    function finishNewOrderConfirmation(accepted) {
        const resolve = resolveNewOrderConfirmation;
        if (!resolve) return;

        resolveNewOrderConfirmation = null;
        newOrderConfirm.hidden = true;
        newOrderConfirm.setAttribute('aria-hidden', 'true');
        newOrderBtn?.focus({preventScroll: true});
        resolve(accepted);
    }

    newOrderConfirm?.querySelectorAll('[data-cancel-new-order]').forEach(button => {
        button.addEventListener('click', () => finishNewOrderConfirmation(false));
    });
    document.getElementById('rbConfirmNewOrderButton')?.addEventListener('click', () => {
        finishNewOrderConfirmation(true);
    });
    newOrderConfirm?.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            finishNewOrderConfirmation(false);
        }
        if (event.key === 'Tab') {
            const buttons = [...newOrderConfirm.querySelectorAll('button')];
            const first = buttons[0], last = buttons[buttons.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    const tableOrderBtn = document.getElementById('rbTableOrderBtn');
    const openCheckoutBtn = document.getElementById('rbOpenCheckoutBtn');
    const saveDraftBtn = document.getElementById('rbSaveDraftBtn');
    const billPaymentBtn = document.getElementById('rbBillPaymentBtn');
    const billPrintBtn = document.getElementById('rbBillPrintBtn');
    const kotPrintBtn = document.getElementById('rbKotPrintBtn');

    const qrOrdersBtn = document.getElementById('rbQrOrdersBtn');
    const draftListBtn = document.getElementById('rbDraftListBtn');
    const receiptsBtn = document.getElementById('rbReceiptsBtn');
    const existingOrderSearch = document.getElementById('rbExistingOrderSearch');

    const drawer = document.getElementById('rbOrdersDrawer');
    const drawerBackdrop = document.getElementById('rbDrawerBackdrop');
    const drawerTitle = document.getElementById('rbDrawerTitle');
    const drawerSubtitle = document.getElementById('rbDrawerSubtitle');
    const drawerSearchInput = document.getElementById('rbDrawerSearchInput');
    const closeDrawerBtn = document.getElementById('rbCloseDrawer');
    const localDraftSection = document.getElementById('rbLocalDraftSection');
    const localDraftContainer = document.getElementById('rbLocalDraftContainer');
    const serverOrdersTitle = document.getElementById('rbServerOrdersTitle');
    const serverOrdersList = document.getElementById('rbServerOrdersList');
    const recentOrdersMarkup = serverOrdersList.innerHTML;
    const queuedCancelModal = document.getElementById('rbQueuedCancelModal');
    const queuedCancelSelect = document.getElementById('rbQueuedCancelItem');
    const queuedCancelReason = document.getElementById('rbQueuedCancelReason');
    const queuedCancelError = document.getElementById('rbQueuedCancelError');
    const queuedCancelConfirm = document.getElementById('rbQueuedCancelConfirm');
    let queuedCancelOrderId = null;
    let queuedCancelPending = false;
    async function checkCartAvailability() {
        const ids = [...new Set([...cart.values()].map(item => Number(item.product_id || item.id)).filter(Boolean))];
        if (!ids.length) return true;
        const url = new URL(cartAvailabilityUrl, window.location.origin);
        url.searchParams.set('location_id', String(locationId));
        ids.forEach(id => url.searchParams.append('ids[]', String(id)));
        const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
        if (!response.ok) throw new Error('تعذر فحص توفر الأصناف حاليًا. لم يتم إرسال الطلب؛ تحقق من الاتصال.');
        const data = await response.json();
        if (!Array.isArray(data.unavailable_ids)) throw new Error('تعذر التحقق من توفر الأصناف. لم يتم إرسال الطلب.');
        if (data.unavailable_ids.length) {
            const names = data.unavailable_ids.map(id => {
                const product = productCache.get(Number(id));
                return product?.name || ('#' + id);
            });
            throw new Error('هذه الأصناف لم تعد متاحة في منيو الفرع: ' + names.join('، ') + '. حدّث المنيو أو احذفها من السلة.');
        }
        return true;
    }
    const draftPreviewModal = document.getElementById('rbDraftPreviewModal');
    const draftPreviewBody = document.getElementById('rbDraftPreviewBody');
    const draftPreviewRestore = document.getElementById('rbDraftPreviewRestore');
    const draftPreviewSave = document.getElementById('rbDraftPreviewSave');
    const draftPreviewCancel = document.getElementById('rbDraftPreviewCancel');
    const draftCancelReasonGroup = document.getElementById('rbDraftCancelReasonGroup');
    const draftCancelReason = document.getElementById('rbDraftCancelReason');
    const draftEditError = document.getElementById('rbDraftEditError');
    let activeServerDraft = null;
    let pendingDraftCancellation = false;
    const draftPreviewOpen = document.getElementById('rbDraftPreviewOpen');
    let draftRequest = 0;
    let draftSearchTimer;
    let receiptSearchTimer;
    let receiptRequest = 0;
    let currentDraftPreview = null;
    let qrRequest = 0;
    let qrSearchTimer;

    const cart = new Map();
    let activeCategory = '__all__';
    let activeBrand = '__all__';
    let pendingAction = 'bill_payment';
    let drawerMode = 'all';
    let matchingTotal = @json($catalogTotal);
    let hasMoreProducts = @json($catalogHasMore);
    let nextCatalogPage = 2;
    let catalogLoading = false;
    let catalogError = false;
    let catalogRequest = 0;
    let catalogAbort = null;
    let searchTimer = null;
    let pendingProduct = null;
    let previousFocus = null;
    // Full active POS catalogue is kept in this tab between page navigation.
    // Cache is user+branch scoped, short-lived (5 minutes), never shared across
    // sessions and never used to bypass checkout's server-side validations.
    const catalogStorageKey = `tish_pos_catalog_v2_${cashierUserId}_${locationId}`;
    const catalogTtlMs = 5 * 60 * 1000;
    let completeCatalog = !hasMoreProducts;
    let allProducts = completeCatalog ? products.slice() : [];
    try {
        const cached = JSON.parse(sessionStorage.getItem(catalogStorageKey) || 'null');
        if (cached?.version === 2
            && cached.savedAt > Date.now() - catalogTtlMs
            && Array.isArray(cached.products)
            && cached.products.length === cached.total
            && cached.products.every(product => Number.isInteger(Number(product.id)))) {
            allProducts = cached.products;
            products = allProducts.slice();
            completeCatalog = true;
            matchingTotal = cached.total;
            hasMoreProducts = false;
            nextCatalogPage = 2;
        }
    } catch (_) { /* Private browsing or storage quota: server payload still works. */ }

    function saveCompleteCatalog() {
        if (!completeCatalog) return;
        try {
            sessionStorage.setItem(catalogStorageKey, JSON.stringify({
                version: 2, savedAt: Date.now(), total: allProducts.length,
                products: allProducts,
            }));
        } catch (_) { /* The live catalogue remains available in memory. */ }
    }

    function filterLoadedCatalog() {
        if (!completeCatalog) return false;
        const search = normalizeSearch(productSearch.value);
        products = allProducts.filter(product => {
            if (activeCategory !== '__all__' && Number(product.category_id) !== Number(activeCategory)) return false;
            if (activeBrand !== '__all__' && Number(product.brand_id) !== Number(activeBrand)) return false;
            if (!search) return true;
            return [product.name, product.description, product.category, product.brand,
                product.sku, product.barcode].some(value => normalizeSearch(value).includes(search));
        });
        matchingTotal = products.length;
        hasMoreProducts = false;
        catalogLoading = false;
        catalogError = false;
        renderProducts();
        return true;
    }

    const productCache = new Map(products.map(product => [Number(product.id), product]));
    if (completeCatalog && allProducts.length) saveCompleteCatalog();

    function normalizeSearch(value) {
        return String(value || '').toLocaleLowerCase('ar')
            .normalize('NFKD')
            .replace(/[\u064b-\u065f\u0670]/g, '')
            .replace(/[أإآٱ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه')
            .replace(/ـ/g, '')
            .trim();
    }

    // Never expose a saved cashier draft to a different signed-in user on a
    // shared terminal. Legacy branch-only drafts are intentionally not imported.
    const draftKey = `restaurant_pos_draft_v3_${cashierUserId}_${locationId}`;
    try { localStorage.removeItem(`restaurant_pos_draft_${locationId}`); } catch (_) {}
    const posAttemptKey = `tish_pos_attempt_${cashierUserId}_${locationId}`;
    const posRequestTokenInput = document.getElementById('rbPosRequestToken');
    function checkoutFingerprint() {
        return JSON.stringify(Array.from(new FormData(form).entries())
            .filter(([key]) => key !== '_token' && key !== 'pos_request_token')
            .map(([key, value]) => [key, value instanceof File
                ? [value.name, value.size, value.lastModified] : String(value)]));
    }
    function checkoutRequestToken() {
        const fingerprint = checkoutFingerprint();
        let attempt = null;
        try { attempt = JSON.parse(sessionStorage.getItem(posAttemptKey) || 'null'); } catch (_) {}
        if (!attempt || attempt.fingerprint !== fingerprint) {
            const random = window.crypto?.randomUUID?.();
            if (!random) {
                throw new Error('المتصفح لا يدعم توليد رمز طلب آمن. افتح النظام عبر HTTPS أو localhost.');
            }
            attempt = {fingerprint, token: random};
            sessionStorage.setItem(posAttemptKey, JSON.stringify(attempt));
        }
        posRequestTokenInput.value = attempt.token;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function money(value) {
        return `₪${Number(value || 0).toFixed(2)}`;
    }

    function applicableGroups(product, variantId) {
        const groups = new Map();
        product.modifier_groups.forEach(group => {
            if (group.product_variant_id && Number(group.product_variant_id) !== Number(variantId)) return;
            if (!groups.has(group.id) || group.product_variant_id) groups.set(group.id, group);
        });
        return [...groups.values()];
    }

    function makeCartItem(product, variantId = null, modifierRows = [], quantity = 1, note = '') {
        const variant = product.variants.find(option => Number(option.id) === Number(variantId)) || null;
        if ((variantId && !variant) || (product.requires_variant && !variant)) return null;

        const groups = applicableGroups(product, variant?.id);
        const modifiers = [];
        for (const selected of modifierRows || []) {
            const match = groups.flatMap(group => group.modifiers.map(modifier => ({ group, modifier })))
                .find(row => Number(row.modifier.id) === Number(selected.modifier_id));
            if (!match || modifiers.some(row => row.modifier_id === Number(selected.modifier_id))) return null;
            const modifierQuantity = Number(selected.quantity || 1);
            if (!Number.isInteger(modifierQuantity) || modifierQuantity < 1
                || modifierQuantity > Number(match.modifier.max_quantity || 1)
                || (!match.modifier.allow_quantity && modifierQuantity !== 1)) return null;
            modifiers.push({
                modifier_id: Number(match.modifier.id),
                quantity: modifierQuantity,
                name: match.modifier.name,
                price_delta: Number(match.modifier.price_delta),
            });
        }

        const key = `${product.id}:${variant?.id || 0}:${modifiers
            .map(row => `${row.modifier_id}-${row.quantity}`).sort().join(',')}`;
        const price = Number(variant?.price ?? product.price)
            + modifiers.reduce((sum, row) => sum + row.price_delta * row.quantity, 0);

        return {
            ...product,
            key,
            variant_id: variant?.id || null,
            name: variant ? `${product.name} — ${variant.name}` : product.name,
            modifiers,
            price,
            quantity: Math.max(1, Number(quantity || 1)),
            kitchen_notes: String(note || '').slice(0, 500),
        };
    }

    function addCartItem(row) {
        if (!row) return;
        if (cart.has(row.key)) {
            cart.get(row.key).quantity += row.quantity;
        } else {
            cart.set(row.key, row);
        }
        renderCart();
    }

    function closeProductOptions() {
        productOptionsModal.hidden = true;
        productOptionsModal.setAttribute('aria-hidden', 'true');
        pendingProduct = null;
        previousFocus?.focus();
    }

    function selectedVariantId() {
        return productOptionsBody.querySelector('[name="rb_variant"]:checked')?.value || null;
    }

    function selectedModifierRows() {
        return [...productOptionsBody.querySelectorAll('[data-modifier-id]:checked')].map(input => ({
            modifier_id: Number(input.dataset.modifierId),
            quantity: Number(input.closest('.rb-option-choice').querySelector('select')?.value || 1),
        }));
    }

    function renderProductGroups() {
        if (!pendingProduct) return;
        const variantId = selectedVariantId();
        const groupContainer = productOptionsBody.querySelector('#rbProductGroups');
        groupContainer.innerHTML = applicableGroups(pendingProduct, variantId).map(group => {
            const minimum = Math.max(Number(group.min_selections || 0), group.is_required ? 1 : 0);
            const maximum = group.selection_type === 'single' ? 1 : group.max_selections;
            return `
                <section class="rb-option-group" data-option-group="${group.id}">
                    <h3>${escapeHtml(group.name)}</h3>
                    <p>${minimum ? `مطلوب ${minimum} على الأقل` : 'اختياري'}${maximum ? ` · الحد الأقصى ${maximum}` : ''}</p>
                    ${group.modifiers.map(modifier => `
                        <label class="rb-option-choice">
                            <input type="${group.selection_type === 'single' ? 'radio' : 'checkbox'}"
                                name="rb_group_${group.id}" data-modifier-id="${modifier.id}"
                                ${modifier.is_default ? 'checked' : ''}>
                            <span>${escapeHtml(modifier.name)}</span>
                            <small>${Number(modifier.price_delta) ? `+ ${money(modifier.price_delta)}` : 'بدون زيادة'}</small>
                            ${modifier.allow_quantity ? `<select aria-label="كمية ${escapeHtml(modifier.name)}" ${modifier.is_default ? '' : 'disabled'}>
                                ${Array.from({ length: Math.max(1, Math.min(10, Number(modifier.max_quantity || 1))) }, (_, i) =>
                                    `<option value="${i + 1}">${i + 1}</option>`).join('')}
                            </select>` : ''}
                        </label>
                    `).join('')}
                </section>
            `;
        }).join('');
        updateProductOptionsPrice();
    }

    function updateProductOptionsPrice() {
        if (!pendingProduct) return;
        const variant = pendingProduct.variants.find(option => Number(option.id) === Number(selectedVariantId()));
        const groups = applicableGroups(pendingProduct, variant?.id);
        const delta = selectedModifierRows().reduce((sum, row) => {
            const modifier = groups.flatMap(group => group.modifiers)
                .find(option => Number(option.id) === row.modifier_id);
            return sum + Number(modifier?.price_delta || 0) * row.quantity;
        }, 0);
        productOptionsPrice.textContent = money(Number(variant?.price ?? pendingProduct.price) + delta);
    }

    function openProductOptions(product) {
        pendingProduct = product;
        previousFocus = document.activeElement;
        document.getElementById('rbProductOptionsTitle').textContent = product.name;
        productOptionsBody.innerHTML = `
            <div class="rb-options-error" id="rbOptionsError" role="alert" hidden></div>
            ${product.requires_variant ? `
                <section class="rb-option-group">
                    <h3>الحجم أو المتغير *</h3>
                    ${product.variants.map(variant => `
                        <label class="rb-option-choice">
                            <input type="radio" name="rb_variant" value="${variant.id}" ${variant.is_default ? 'checked' : ''}>
                            <span>${escapeHtml(variant.name)}</span>
                            <small>${money(variant.price)}</small>
                        </label>
                    `).join('')}
                </section>
            ` : ''}
            <div id="rbProductGroups"></div>
        `;
        renderProductGroups();
        productOptionsModal.hidden = false;
        productOptionsModal.setAttribute('aria-hidden', 'false');
        productOptionsBody.querySelector('input')?.focus();
    }

    function confirmProductOptions() {
        if (!pendingProduct) return;
        const error = document.getElementById('rbOptionsError');
        const variantId = selectedVariantId();
        if (pendingProduct.requires_variant && !variantId) {
            error.textContent = 'اختر الحجم أو المتغير أولًا.';
            error.hidden = false;
            return;
        }

        for (const group of applicableGroups(pendingProduct, variantId)) {
            const selected = productOptionsBody.querySelectorAll(`[data-option-group="${group.id}"] [data-modifier-id]:checked`).length;
            const minimum = Math.max(Number(group.min_selections || 0), group.is_required ? 1 : 0);
            const maximum = group.selection_type === 'single' ? 1 : group.max_selections;
            if (selected < minimum || (maximum !== null && selected > Number(maximum))) {
                error.textContent = `راجع خيارات ${group.name}: اختر من ${minimum} إلى ${maximum ?? 'أي عدد'}.`;
                error.hidden = false;
                return;
            }
        }

        const row = makeCartItem(pendingProduct, variantId, selectedModifierRows());
        if (!row) {
            error.textContent = 'تعذر إضافة هذه الخيارات. أعد اختيار المنتج.';
            error.hidden = false;
            return;
        }
        closeProductOptions();
        addCartItem(row);
    }

    function statusMessage(message, type = 'info') {
        const old = document.getElementById('rbPosToast');
        old?.remove();

        const toast = document.createElement('div');
        toast.id = 'rbPosToast';

        const background =
            type === 'error'
                ? 'var(--rb-danger, #D92D20)'
                : (
                    type === 'success'
                        ? 'var(--rb-green, #197438)'
                        : 'var(--rb-navy, #0A2948)'
                );

        toast.textContent = message;

        toast.style.cssText = `
            position:fixed;
            left:50%;
            bottom:28px;
            z-index:2147483646;
            transform:translateX(-50%);
            max-width:min(720px,92vw);
            padding:12px 18px;
            color:#fff;
            background:${background};
            border-radius:9px;
            font-size:12px;
            font-weight:850;
            line-height:1.7;
            direction:rtl;
            text-align:center;
            box-shadow:0 14px 38px rgba(0,0,0,.28);
        `;

        /*
         * مهم:
         * .rb-pos نفسه z-index = 2147482000.
         * لذلك إلحاق الـ Toast بـ body مع z-index صغير يجعله خلف شاشة POS.
         * نضعه داخل rbRoot نفسه ليبقى فوق الواجهة.
         */
        (rbRoot || document.body).appendChild(toast);

        window.setTimeout(
            () => toast.remove(),
            type === 'error' ? 6000 : 3000
        );
    }

    function clearCheckoutError() {
        document.getElementById('rbCheckoutServerError')?.remove();

        [
            referenceInput,
            proofInput,
            paymentMethod,
        ].forEach(field => {
            field?.style.removeProperty('border-color');
            field?.style.removeProperty('box-shadow');
        });
    }

    function showCheckoutError(message, fieldName = null) {
        clearCheckoutError();

        let box = document.getElementById('rbCheckoutServerError');

        if (!box) {
            box = document.createElement('div');
            box.id = 'rbCheckoutServerError';
            box.style.cssText = `
                margin:0 0 12px;
                padding:10px 12px;
                color:#B42318;
                background:#FEF3F2;
                border:1px solid #FECDCA;
                border-radius:7px;
                font-size:11px;
                font-weight:800;
                line-height:1.7;
                direction:rtl;
            `;

            checkoutModal
                .querySelector('.rb-modal-body')
                ?.prepend(box);
        }

        box.textContent = message;

        const field =
            fieldName === 'reference_number'
                ? referenceInput
                : (
                    fieldName === 'payment_proof'
                        ? proofInput
                        : (
                            fieldName === 'payment_method_id'
                                ? paymentMethod
                                : null
                        )
                );

        if (field) {
            field.style.borderColor = 'var(--rb-danger, #D92D20)';
            field.style.boxShadow = '0 0 0 3px rgba(217,45,32,.10)';

            window.setTimeout(
                () => field.focus(),
                80
            );
        }
    }

    function currentDiscount(subtotal) {
        const type = discountType?.value || 'none';
        const raw = Math.max(0, Number(discountValue?.value || 0));

        if (type === 'percentage') {
            return Math.min(subtotal, Math.floor((subtotal * Math.min(raw, 100) / 100 + Number.EPSILON) * 100) / 100);
        }

        if (type === 'fixed') {
            return Math.min(subtotal, raw);
        }

        return 0;
    }

    function getTotals() {
        const rows = [...cart.values()];
        const subtotal = rows.reduce(
            (sum, item) => sum + Number(item.price) * Number(item.quantity),
            0
        );
        const discount = currentDiscount(subtotal);
        const channel = channelRules[form.querySelector('[name="sales_channel_id"]')?.value];
        const afterOrderDiscount = Math.max(0, subtotal - discount);
        const channelDiscount = channel?.discount_type === 'percentage'
            ? Math.floor((afterOrderDiscount * Math.min(channel.discount_value, 100) / 100 + Number.EPSILON) * 100) / 100
            : channel?.discount_type === 'fixed'
                ? Math.min(afterOrderDiscount, channel.discount_value)
                : 0;
        return {
            subtotal,
            discount,
            channelDiscount,
            total: Math.max(0, afterOrderDiscount - channelDiscount),
        };
    }

    function productImage(product) {
        const placeholder = `
            <div class="rb-product-placeholder" ${product.image ? 'hidden' : ''}>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <path d="m21 15-5-5L5 21"></path>
                </svg>
                <span>صورة غير متوفرة</span>
            </div>
        `;

        if (product.image) {
            return `
                <img
                    src="${escapeHtml(product.image)}"
                    alt="${escapeHtml(product.name)}"
                    loading="lazy"
                    decoding="async"
                    onerror="this.style.display='none';this.nextElementSibling.hidden=false;"
                >
                ${placeholder}
            `;
        }

        return placeholder;
    }

    function renderFilters() {
        categorySelect.innerHTML = `
            <option value="__all__">كل الفئات</option>
            ${categories.map(category => `
                <option value="${category.id}">${escapeHtml(category.name)}</option>
            `).join('')}
        `;
        categorySelect.value = activeCategory;

        brandSelect.innerHTML = `
            <option value="__all__">كل العلامات</option>
            ${brands.map(brand => `
                <option value="${brand.id}">${escapeHtml(brand.name)}</option>
            `).join('')}
        `;
        brandSelect.value = activeBrand;

        const tabCategories = categories;
        categoryTabs.innerHTML = `
            <button type="button" class="rb-category-tab ${activeCategory === '__all__' ? 'active' : ''}" data-category="__all__" aria-pressed="${activeCategory === '__all__'}">
                عرض الكل (${categories.reduce((total, category) => total + category.count, 0)})
            </button>
            ${tabCategories.map(category => `
                <button
                    type="button"
                    class="rb-category-tab ${activeCategory === String(category.id) ? 'active' : ''}"
                    data-category="${category.id}"
                    aria-pressed="${activeCategory === String(category.id)}"
                >
                    ${escapeHtml(category.name)} (${category.count})
                </button>
            `).join('')}
        `;
        brandSelect.hidden = brands.length === 0;
    }

    function renderProducts() {
        visibleProductCount.textContent = products.length;
        matchingProductCount.textContent = matchingTotal;
        loadMoreButton.hidden = !hasMoreProducts;
        loadMoreButton.disabled = catalogLoading;
        loadMoreButton.textContent = catalogLoading ? 'جار التحميل...' : 'عرض المزيد من المنتجات';

        if (!products.length) {
            productGrid.innerHTML = `
                <div class="rb-products-empty">
                    ${catalogLoading ? 'جار تحميل منتجات المنيو...'
                        : catalogError
                            ? 'تعذر تحميل المنتجات.<button type="button" data-retry-products>إعادة المحاولة</button>'
                            : matchingTotal
                                ? 'لا توجد منتجات في هذه الصفحة.'
                                : categories.length
                                    ? 'لا توجد منتجات مطابقة. جرّب اسمًا أو رمزًا آخر.<button type="button" data-reset-products>مسح البحث والفلاتر</button>'
                                    : 'لا توجد منتجات متاحة للبيع في منيو هذا الفرع.'}
                </div>
            `;
            return;
        }

        productGrid.innerHTML = products.map(product => {
            const qty = [...cart.values()]
                .filter(item => Number(item.id) === Number(product.id))
                .reduce((total, item) => total + Number(item.quantity), 0);
            const hasOptions = product.requires_variant || product.modifier_groups.length > 0;

            return `
                <button
                    type="button"
                    class="rb-product-card"
                    data-product-id="${product.id}"
                    aria-label="${hasOptions ? 'اختيار خيارات' : 'إضافة'} ${escapeHtml(product.name)} — ${money(product.price)}"
                >
                    <div class="rb-product-image">
                        ${productImage(product)}
                    </div>

                    <span class="rb-product-qty-badge" ${qty > 0 ? '' : 'hidden'}>
                        ${qty > 0 ? qty : ''}
                    </span>

                    <div class="rb-product-info">
                        <div class="rb-product-meta-line">
                            <span class="rb-product-category">${escapeHtml(product.category || 'بدون فئة')}</span>
                            <span class="rb-product-sku">${escapeHtml(product.sku || '')}</span>
                        </div>

                        <span class="rb-product-name">${escapeHtml(product.name)}</span>
                        ${product.description
                            ? `<span class="rb-product-description" title="${escapeHtml(product.description)}">${escapeHtml(product.description)}</span>`
                            : ''}

                        <div class="rb-product-bottom">
                            <span class="rb-product-price">${money(product.price)}</span>
                            ${hasOptions
                                ? '<span class="rb-product-options-label">اختيار الخيارات</span>'
                                : `<span class="rb-product-plus" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14"></path>
                                    </svg>
                                </span>`}
                        </div>
                    </div>
                </button>
            `;
        }).join('');
    }

    async function loadCatalog(page = 1, append = false, forceServer = false) {
        if (!forceServer && !append && filterLoadedCatalog()) return { products, total: matchingTotal };
        clearTimeout(searchTimer);
        searchTimer = null;
        const requestNumber = ++catalogRequest;
        catalogAbort?.abort();
        catalogAbort = new AbortController();
        catalogLoading = true;
        catalogError = false;
        if (!append) {
            products = [];
            matchingTotal = 0;
            hasMoreProducts = false;
        }
        renderProducts();

        const url = new URL(catalogUrl, window.location.origin);
        url.searchParams.set('location_id', locationId);
        url.searchParams.set('page', page);
        if (productSearch.value.trim()) url.searchParams.set('q', productSearch.value.trim());
        if (activeCategory !== '__all__') url.searchParams.set('category_id', activeCategory);
        if (activeBrand !== '__all__') url.searchParams.set('brand_id', activeBrand);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: catalogAbort.signal,
            });
            if (!response.ok) throw new Error(`Catalog HTTP ${response.status}`);
            const data = await response.json();
            if (requestNumber !== catalogRequest) return null;

            products = append ? [...products, ...data.products] : data.products;
            data.products.forEach(product => productCache.set(Number(product.id), product));
            matchingTotal = data.total;
            hasMoreProducts = data.has_more;
            nextCatalogPage = data.page + 1;
            catalogLoading = false;
            renderProducts();
            return data;
        } catch (error) {
            if (requestNumber !== catalogRequest || error.name === 'AbortError') return null;
            catalogLoading = false;
            catalogError = true;
            renderProducts();
            statusMessage('تعذر تحميل منتجات المنيو. أعد المحاولة.', 'error');
            return null;
        }
    }

    async function loadProductsByIds(ids) {
        const uniqueIds = [...new Set(ids.map(Number).filter(id => Number.isInteger(id) && id > 0))].slice(0, 100);
        if (!uniqueIds.length) return [];

        const url = new URL(catalogUrl, window.location.origin);
        url.searchParams.set('location_id', locationId);
        uniqueIds.forEach(id => url.searchParams.append('ids[]', id));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Catalog HTTP ${response.status}`);
        const data = await response.json();
        data.products.forEach(product => productCache.set(Number(product.id), product));
        return data.products;
    }

    function syncHiddenItems() {
        hiddenItems.innerHTML = [...cart.values()].map((item, index) => `
            <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
            ${item.variant_id ? `<input type="hidden" name="items[${index}][product_variant_id]" value="${item.variant_id}">` : ''}
            <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
            <input type="hidden" name="items[${index}][kitchen_notes]" value="${escapeHtml(item.kitchen_notes || '')}">
            ${item.modifiers.map((modifier, modifierIndex) => `
                <input type="hidden" name="items[${index}][modifiers][${modifierIndex}][modifier_id]" value="${modifier.modifier_id}">
                <input type="hidden" name="items[${index}][modifiers][${modifierIndex}][quantity]" value="${modifier.quantity}">
            `).join('')}
        `).join('');
    }

    function renderSummary() {
        const totals = getTotals();
        subtotalEl.textContent = money(totals.subtotal);
        discountAmountEl.textContent = money(totals.discount);
        channelDiscountEl.textContent = money(totals.channelDiscount);
        grandTotalEl.textContent = money(totals.total);
        checkoutTotalEl.textContent = money(totals.total);
        document.getElementById('rbMobileCartTotal').textContent = money(totals.total);
        document.getElementById('rbMobileCartCount').textContent = [...cart.values()]
            .reduce((sum, item) => sum + Number(item.quantity), 0);
        document.getElementById('rbMobileCheckoutBtn').disabled = !cart.size;
        renderSplitTender();
        cartCount.textContent = cart.size;
    }

    function renderCart() {
        const rows = [...cart.values()];
        const hasCartItems = rows.length > 0;

        cartEmpty.hidden = hasCartItems;
        cartEmpty.style.display = hasCartItems ? 'none' : 'flex';

        cartItems.innerHTML = rows.map(item => {
            const lineTotal = Number(item.price) * Number(item.quantity);
            const hasNote = Boolean(item.kitchen_notes);

            return `
                <div class="rb-cart-item" data-cart-row="${item.key}">
                    <div class="rb-cart-item-head">
                        <div class="rb-cart-item-name">
                            <strong>${escapeHtml(item.name)}</strong>
                            ${item.modifiers.length ? `<small class="rb-cart-options">${escapeHtml(item.modifiers.map(modifier => modifier.name).join('، '))}</small>` : ''}
                            <div class="rb-cart-item-price-line">
                                <span>${money(item.price)}</span>
                                <span>×</span>
                                <span>${Number(item.quantity)}</span>
                                <span>=</span>
                                <span>${money(lineTotal)}</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rb-cart-remove"
                            data-cart-action="remove"
                            data-id="${item.key}"
                            title="حذف"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16M9 7V4h6v3M8 10v7M12 10v7M16 10v7M6 7l1 14h10l1-14"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="rb-cart-item-foot">
                        <div class="rb-qty-control">
                            <button type="button" class="rb-qty-btn" data-cart-action="minus" data-id="${item.key}">−</button>
                            <span class="rb-qty-value">${Number(item.quantity)}</span>
                            <button type="button" class="rb-qty-btn" data-cart-action="plus" data-id="${item.key}">+</button>
                        </div>

                        <button type="button" class="rb-add-note" data-note-toggle="${item.key}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 5h16v11H8l-4 4z"></path>
                            </svg>
                            ${hasNote ? 'تعديل الملاحظة' : 'إضافة ملاحظة'}
                        </button>
                    </div>

                    <div class="rb-item-note-wrap" data-note-wrap="${item.key}" ${hasNote ? '' : 'hidden'}>
                        <input
                            type="text"
                            class="rb-item-note"
                            data-kitchen-note="${item.key}"
                            maxlength="500"
                            value="${escapeHtml(item.kitchen_notes || '')}"
                            placeholder="مثال: بدون بصل، زيادة صوص..."
                        >
                    </div>
                </div>
            `;
        }).join('');

        syncHiddenItems();
        renderSummary();
        renderProducts();
    }

    function addProduct(productId) {
        const id = Number(productId);
        const product = productCache.get(id);
        if (!product) return;

        if (product.requires_variant || product.modifier_groups.length) {
            openProductOptions(product);
            return;
        }
        addCartItem(makeCartItem(product));
    }

    async function clearOrder(ask = true) {
        if (!cart.size && !ask) return;

        if (ask && cart.size && !await confirmNewOrder()) {
            return;
        }

        cart.clear();
        sessionStorage.removeItem(posAttemptKey);
        posRequestTokenInput.value = '';
        renderCart();
        productSearch.value = '';
        activeCategory = '__all__';
        activeBrand = '__all__';
        renderFilters();
        loadCatalog();
        statusMessage('تم فتح طلب جديد.');
    }

    function syncServiceType() {
        const dineIn = serviceType.value === 'dine_in';
        tableSelect.required = dineIn;
        guestCountGroup.hidden = !dineIn;

        if (!dineIn) {
            tableSelect.value = '';
        }

        const hasArrangement = value => Array.from(paymentArrangement.options)
            .some(option => option.value === value);

        if (dineIn && paymentArrangement.value === 'pay_now'
            && !paymentMethod.value && hasArrangement('pay_on_pickup')) {
            paymentArrangement.value = 'pay_on_pickup';
        }

        if (!dineIn && paymentArrangement.value === 'pay_on_pickup'
            && hasArrangement('pay_now')) {
            paymentArrangement.value = 'pay_now';
        }

        syncPayment();
    }

    function syncPayment() {
        const arrangement = paymentArrangement.value;
        splitTenderOption.hidden = arrangement !== 'pay_now';
        const splitActive = splitMode();
        splitTenderPanel.hidden = !splitActive;
        splitMethods.forEach((field, i) => {
            field.disabled = !splitActive;
            splitAmounts[i].disabled = !splitActive;
            field.required = splitActive;
            splitAmounts[i].required = splitActive;
            const option = field.options[field.selectedIndex];
            const nonCash = splitActive && !!field.value && option?.dataset.type !== 'cash';
            const needsReference = nonCash || (splitActive && option?.dataset.reference === '1');
            // Hide irrelevant transfer fields and prevent stale values from
            // being submitted after switching to a cash payment method.
            splitReferences[i].disabled = !needsReference;
            splitReferences[i].required = needsReference;
            splitReferences[i].style.display = needsReference ? '' : 'none';
            form.querySelector('label[for="' + splitReferences[i].id + '"]').style.display =
                needsReference ? '' : 'none';
            splitConfirmations[i].disabled = !nonCash;
            splitConfirmations[i].required = nonCash;
            splitConfirmations[i].parentElement.style.display = nonCash ? '' : 'none';
        });
        if (splitActive) {
            paymentMethod.disabled = true;
            form.querySelector('[name="paid_amount"]').disabled = true;
            paymentMethodGroup.hidden = true;
            paymentMethod.required = false;
            paidAmountGroup.hidden = true;
            referenceGroup.hidden = true;
            senderNameGroup.hidden = true;
            senderPhoneGroup.hidden = true;
            senderAccountGroup.hidden = true;
            proofGroup.hidden = true;
            manualReceiptGroup.hidden = true;
            manualReceipt.required = false;
            referenceInput.required = false;
            proofInput.required = false;
            renderSplitTender();
            clearCheckoutError();
            return;
        }

        paymentMethod.disabled = false;
        form.querySelector('[name="paid_amount"]').disabled = false;
        const noImmediate = [
            'pay_on_pickup',
            'on_account'
        ].includes(arrangement);

        const partial = [
            'deposit',
            'partial_payment'
        ].includes(arrangement);

        paymentMethodGroup.hidden = noImmediate;
        paymentMethod.required = !noImmediate;
        paidAmountGroup.hidden = !partial;

        const selected =
            paymentMethod.options[
                paymentMethod.selectedIndex
            ];

        const methodType =
            !noImmediate
                ? (selected?.dataset.type || '')
                : '';

        const isNonCash =
            !noImmediate
            && paymentMethod.value !== ''
            && methodType !== 'cash';

        const methodRequiresReference =
            !noImmediate
            && selected?.dataset.reference === '1';

        const methodRequiresVerification =
            !noImmediate
            && selected?.dataset.verification === '1';

        /*
         * في الكاشير: أي دفع غير نقدي يجب أن يحمل بيانات العملية
         * حتى يمكن مراجعته لاحقاً من الطلب وسجل المدفوعات.
         */
        const requiresReference =
            !noImmediate
            && (
                isNonCash
                || methodRequiresReference
                || methodRequiresVerification
                || arrangement === 'pending_verification'
            );

        const requiresProof =
            !noImmediate
            && (
                methodRequiresVerification
                || arrangement === 'pending_verification'
            );

        referenceGroup.hidden = !requiresReference;
        senderNameGroup.hidden = !isNonCash;
        senderPhoneGroup.hidden = !isNonCash;
        senderAccountGroup.hidden = !isNonCash;
        proofGroup.hidden = !requiresProof;
        manualReceiptGroup.hidden = !isNonCash || requiresProof;
        manualReceipt.required = isNonCash && !requiresProof;

        if (referenceInput) {
            referenceInput.required = requiresReference;
        }

        if (proofInput) {
            proofInput.required = requiresProof;
        }

        clearCheckoutError();
    }

    function openCheckout(action = 'bill_payment') {
        checkoutModal.classList.remove('rb-quick-cash');
        quickCashExpand.hidden = true;
        const printingInvoice = action === 'bill_print';
        const payNowOption = [...paymentArrangement.options]
            .find(option => option.value === 'pay_now');
        if (printingInvoice && (!payNowOption || payNowOption.disabled)) {
            statusMessage('الدفع الفوري غير متاح في إعدادات الفرع؛ لا يمكن إصدار فاتورة مدفوعة وطبعها.', 'error');
            return;
        }

        pendingAction = action;
        actionInput.value = action;
        // Invoice printing must not accidentally inherit a deferred/partial
        // arrangement from another checkout. Keep those choices for payment
        // without printing, and enforce the same rule on the Laravel server.
        [...paymentArrangement.options].forEach(option => {
            option.disabled = printingInvoice && option.value !== 'pay_now';
        });
        if (printingInvoice) paymentArrangement.value = 'pay_now';
        document.getElementById('rbCheckoutTitle').textContent = printingInvoice
            ? 'دفع كامل وطباعة فاتورة العميل' : 'تفاصيل الطلب والدفع';
        document.getElementById('rbCheckoutHint').textContent = printingInvoice
            ? 'سيُحفظ الطلب ويُحصّل المبلغ كاملًا قبل طباعة الفاتورة. إذا أردت الدفع لاحقًا استخدم الفاتورة والدفع.'
            : 'يمكنك الدفع الآن أو اختيار الدفع عند الاستلام وفق إعدادات الفرع.';
        document.getElementById('rbCheckoutContinueBtn').textContent = printingInvoice
            ? 'تأكيد الدفع وطباعة الفاتورة' : 'تأكيد الطلب والمتابعة';
        syncPayment();
        renderSummary();
        checkoutModal.hidden = false;
        checkoutModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeCheckout() {
        // Restore all branch-enabled payment arrangements when modal closes.
        [...paymentArrangement.options].forEach(option => { option.disabled = false; });
        checkoutModal.hidden = true;
        checkoutModal.classList.remove('rb-quick-cash');
        quickCashExpand.hidden = true;
        checkoutModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function validateOrderBasics() {
        if (!cart.size) {
            statusMessage('أضف منتجًا واحدًا على الأقل للطلب.', 'error');
            return false;
        }

        if (serviceType.value === 'dine_in' && !tableSelect.value) {
            statusMessage('اختر الطاولة لطلب الصالة.', 'error');
            tableSelect.focus();
            return false;
        }

        return true;
    }

    function validatePaymentFields() {
        syncPayment();

        if (splitMode() && splitMethods.some(field => field.options.length <= 1)) {
            showCheckoutError('لا توجد طريقتا دفع مختلط متاحتان لهذا الفرع. راجع إعدادات طرق الدفع.', 'split_payments');
            return false;
        }
        if (!splitMode() && paymentMethod.required && paymentMethod.options.length <= 1) {
            showCheckoutError('لا توجد طريقة دفع نشطة لهذا الفرع. اطلب من المدير تفعيل طرق الدفع.', 'payment_method_id');
            return false;
        }

        // Catch an invalid print + deferred payment combination before any
        // network request. The request validator repeats this on the server.
        if (pendingAction === 'bill_print' && paymentArrangement.value !== 'pay_now') {
            showCheckoutError('طباعة فاتورة مدفوعة تتطلب اختيار الدفع الفوري الكامل.', 'payment_arrangement');
            return false;
        }

        const salesChannel = form.querySelector(
            '[name="sales_channel_id"]'
        );

        if (!salesChannel?.value) {
            showCheckoutError(
                'اختر قناة البيع.',
                'sales_channel_id'
            );
            salesChannel?.focus();
            return false;
        }

        if (splitMode()) {
            const cents = Math.round(getTotals().total * 100);
            const pieces = splitAmounts.map(field => Math.round(Number(field.value || 0) * 100));
            if (pieces.some(value => !Number.isSafeInteger(value) || value <= 0)
                || pieces.reduce((sum,value) => sum + value, 0) !== cents
                || splitMethods.some(field => !field.value)
                || splitMethods[0].value === splitMethods[1].value) {
                showCheckoutError('اختر طريقتين مختلفتين ووزع الإجمالي بدقة دون زيادة أو نقص.', 'split_payments');
                return false;
            }
            for (let i = 0; i < 2; i++) {
                const option = splitMethods[i].options[splitMethods[i].selectedIndex];
                if (option?.dataset.type !== 'cash'
                    && (!splitReferences[i].value.trim() || !splitConfirmations[i].checked)) {
                    showCheckoutError('كل دفعة غير نقدية تحتاج رقم عملية وتأكيد التحصيل.', 'split_payments');
                    return false;
                }
            }
            clearCheckoutError();
            return true;
        }

        if (
            paymentMethod.required
            && !paymentMethod.value
        ) {
            showCheckoutError(
                'اختر طريقة الدفع.',
                'payment_method_id'
            );
            return false;
        }

        if (
            referenceInput?.required
            && !referenceInput.value.trim()
        ) {
            showCheckoutError(
                'رقم العملية / المرجع مطلوب لطريقة الدفع المحددة.',
                'reference_number'
            );
            return false;
        }

        const selectedPaymentMethod =
            paymentMethod.options[
                paymentMethod.selectedIndex
            ];

        const nonCashPayment =
            paymentMethod.required
            && paymentMethod.value !== ''
            && (selectedPaymentMethod?.dataset.type || '') !== 'cash';

        if (nonCashPayment && manualReceipt.required && !manualReceipt.checked) {
            showCheckoutError('تأكد من وصول المبلغ ثم أكد التحصيل.', 'payment_received_confirmed');
            manualReceipt.focus();
            return false;
        }

        if (
            proofInput?.required
            && !proofInput.files.length
        ) {
            showCheckoutError(
                'هذه طريقة دفع تحتاج إثبات دفع.',
                'payment_proof'
            );
            return false;
        }

        clearCheckoutError();

        return true;
    }

    function serializeDraft() {
        const simpleField = name => form.querySelector(`[name="${name}"]`)?.value ?? '';

        return {
            version: 2,
            location_id: locationId,
            saved_at: new Date().toISOString(),
            items: [...cart.values()].map(item => ({
                id: item.id,
                product_variant_id: item.variant_id,
                modifiers: item.modifiers.map(modifier => ({
                    modifier_id: modifier.modifier_id,
                    quantity: modifier.quantity,
                })),
                quantity: item.quantity,
                kitchen_notes: item.kitchen_notes || '',
            })),
            fields: {
                service_type: serviceType.value,
                restaurant_table_id: tableSelect.value,
                guest_count: simpleField('guest_count'),
                customer_id: simpleField('customer_id'),
                sales_channel_id: simpleField('sales_channel_id'),
                payment_arrangement: paymentArrangement.value,
            split_tender_enabled: splitTenderToggle.checked,
            split_payments: splitMode() ? splitMethods.map((field, i) => ({
                payment_method_id: field.value,
                amount: splitAmounts[i].value,
                reference_number: splitReferences[i].value,
                payment_received_confirmed: splitConfirmations[i].checked,
            })) : [],
                payment_method_id: paymentMethod.value,
                paid_amount: simpleField('paid_amount'),
                discount_type: discountType.value,
                discount_value: discountValue.value,
                discount_reason: discountReason.value.trim(),
                reference_number: simpleField('reference_number'),
                sender_name: simpleField('sender_name'),
                sender_phone: simpleField('sender_phone'),
                sender_account_number: simpleField('sender_account_number'),
                notes: simpleField('notes'),
            },
        };
    }

    // Saving a cart locally is never a payment confirmation or a server order.
    function preserveUnfinishedCart() {
        if (!cart.size || rbCheckoutSucceeded) return false;
        try {
            localStorage.setItem(draftKey, JSON.stringify(serializeDraft()));
            return true;
        } catch (_) {
            return false;
        }
    }

    function saveLocalDraft() {
        if (!cart.size) {
            statusMessage('لا يوجد طلب لحفظه كمسودة.', 'error');
            return;
        }

        try {
            localStorage.setItem(draftKey, JSON.stringify(serializeDraft()));
        } catch (error) {
            statusMessage('تعذر حفظ المسودة على الجهاز. لا تغلق الصفحة حتى تحفظ تفاصيل الطلب بطريقة آمنة.', 'error');
            return;
        }
        statusMessage('تم حفظ المسودة لهذا الكاشير على هذا الجهاز.');
        renderLocalDraftCard();
    }

    function loadLocalDraft() {
        let raw;
        try { raw = localStorage.getItem(draftKey); } catch (_) { return null; }
        if (!raw) return null;

        try {
            return JSON.parse(raw);
        } catch (_) {
            return null;
        }
    }

    async function restoreLocalDraft() {
        const draft = loadLocalDraft();
        if (!draft || !Array.isArray(draft.items)) {
            statusMessage('المسودة غير متاحة أو تالفة.', 'error');
            return;
        }

        let availableProducts;
        try {
            availableProducts = await loadProductsByIds(draft.items.map(row => row.id));
        } catch (error) {
            statusMessage('تعذر التحقق من منتجات المسودة. أعد المحاولة.', 'error');
            return;
        }

        const available = new Map(availableProducts.map(product => [Number(product.id), product]));
        cart.clear();
        draft.items.forEach(row => {
            const product = available.get(Number(row.id));
            if (!product) return;
            const item = makeCartItem(product, row.product_variant_id, row.modifiers, row.quantity, row.kitchen_notes);
            if (item) cart.set(item.key, item);
        });

        const fields = draft.fields || {};
        Object.entries(fields).forEach(([name, value]) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field && field.type !== 'file') {
                field.value = value ?? '';
            }
        });

        splitTenderToggle.checked = Boolean(fields.split_tender_enabled);
        if (Array.isArray(fields.split_payments)) {
            fields.split_payments.slice(0,2).forEach((part,i) => {
                if (!splitMethods[i]) return;
                splitMethods[i].value = String(part.payment_method_id || '');
                splitAmounts[i].value = part.amount || '';
                splitReferences[i].value = part.reference_number || '';
                splitConfirmations[i].checked = Boolean(part.payment_received_confirmed);
            });
        }
        syncServiceType();
        syncPayment();
        renderCart();
        closeDrawer();
        statusMessage(cart.size ? 'تم استرجاع المسودة بالمنتجات المتاحة في الفرع.' : 'منتجات المسودة لم تعد متاحة للبيع.', cart.size ? 'success' : 'error');
    }

    function deleteLocalDraft() {
        localStorage.removeItem(draftKey);
        renderLocalDraftCard();
        statusMessage('تم حذف المسودة المحلية.');
    }

    function openDraftPreview(draft, kind) {
        currentDraftPreview = kind;
        activeServerDraft = kind === 'server' ? draft : null;
        draftEditError.hidden = true;
        draftEditError.textContent = '';
        draftCancelReason.value = '';
        pendingDraftCancellation = false;
        draftPreviewCancel.textContent = 'إلغاء الطلب';
        const items = Array.isArray(draft.items) ? draft.items : [];
        const total = kind === 'server' ? `<p><strong>الإجمالي:</strong> ${money(draft.total)}</p>` : '';
        const label = kind === 'server' ? 'طلب مسجل على الخادم (للعرض فقط)' : 'مسودة محفوظة على هذا الجهاز';
        draftPreviewBody.innerHTML = `<p>${escapeHtml(label)}</p>
            <p><strong>الرقم / العميل:</strong> ${escapeHtml(draft.number || draft.customer || 'مسودة محلية')}</p>
            ${total}
            <ul class="rb-draft-preview-items">${items.length ? items.map(item =>
                `<li>${escapeHtml(item.name || productCache.get(Number(item.id))?.name_ar || productCache.get(Number(item.id))?.name || ('منتج #' + item.id))}
                    × ${escapeHtml(item.quantity)}
                    ${item.notes || item.kitchen_notes ? `<small>${escapeHtml(item.notes || item.kitchen_notes)}</small>` : ''}
                </li>`).join('') : '<li>لا توجد منتجات.</li>'}</ul>`;
        document.getElementById('rbDraftPreviewTitle').textContent =
            kind === 'server' ? 'تفاصيل الطلب المسودة' : 'معاينة المسودة المحلية';
        draftPreviewSave.hidden = kind !== 'server' || !draft.editable;
        draftPreviewCancel.hidden = kind !== 'server' || !draft.cancelable;
        draftCancelReasonGroup.hidden = draftPreviewCancel.hidden;
        if (kind === 'server' && draft.editable) {
            draftPreviewBody.querySelectorAll('.rb-draft-preview-items li').forEach((line, index) => {
                const item = items[index];
                if (!item) return;
                const controls = document.createElement('div');
                controls.style.cssText = 'display:grid;grid-template-columns:100px 1fr;gap:10px;margin-top:8px';
                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.min = '0.001';
                quantity.step = '0.001';
                quantity.value = String(item.quantity);
                quantity.dataset.draftQuantity = String(item.id);
                quantity.setAttribute('aria-label', 'الكمية');
                quantity.className = 'rb-control';
                const notes = document.createElement('input');
                notes.type = 'text';
                notes.maxLength = 500;
                notes.value = item.notes || '';
                notes.dataset.draftNotes = String(item.id);
                notes.setAttribute('aria-label', 'ملاحظات المطبخ');
                notes.placeholder = 'ملاحظات المطبخ';
                notes.className = 'rb-control';
                const cancelLabel = document.createElement('label');
                cancelLabel.style.cssText = 'display:flex;gap:6px;align-items:center;margin-top:8px';
                const remove = document.createElement('input');
                remove.type = 'checkbox';
                remove.dataset.draftRemove = String(item.id);
                cancelLabel.append(remove, document.createTextNode('إلغاء هذا العنصر قبل إرساله للمطبخ'));
                remove.addEventListener('change', () => {
                    quantity.disabled = notes.disabled = remove.checked;
                    line.style.opacity = remove.checked ? '.55' : '1';
                });
                controls.append(quantity, notes);
                line.append(controls, cancelLabel);
            });
        }
        draftPreviewRestore.hidden = kind !== 'local';
        draftPreviewOpen.hidden = kind !== 'server' || !draft.url;
        if (draft.url) draftPreviewOpen.href = draft.url;
        closeDrawer();
        draftPreviewModal.hidden = false;
        draftPreviewModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeDraftPreview() {
        draftPreviewModal.hidden = true;
        draftPreviewModal.setAttribute('aria-hidden', 'true');
        currentDraftPreview = null;
        activeServerDraft = null;
        pendingDraftCancellation = false;
        document.body.style.overflow = '';
    }

    async function loadServerDrafts(page = 1) {
        const request = ++draftRequest;
        if (page === 1) serverOrdersList.innerHTML = '<div class="rb-drawer-empty">جار تحميل المسودات...</div>';
        const url = new URL(draftOrdersUrl, window.location.origin);
        url.searchParams.set('location_id', locationId);
        url.searchParams.set('page', page);
        if (drawerSearchInput.value.trim()) url.searchParams.set('q', drawerSearchInput.value.trim());
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('تعذر تحميل المسودات.');
            const data = await response.json();
            if (request !== draftRequest || drawerMode !== 'draft') return;
            if (page === 1) serverOrdersList.replaceChildren();
            serverOrdersList.querySelector('[data-draft-more]')?.remove();
            (data.orders || []).forEach(order => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'rb-server-order-card';
                card.style.width = '100%';
                card.style.textAlign = 'right';
                card.innerHTML = `<div class="rb-server-order-top"><strong>${escapeHtml(order.number)}</strong><span>مسودة</span></div>
                    <div class="rb-server-order-meta"><span>${escapeHtml(order.customer)}</span><span>${money(order.total)}</span></div>`;
                card.addEventListener('click', () => openDraftPreview(order, 'server'));
                serverOrdersList.appendChild(card);
            });
            if (!serverOrdersList.children.length) serverOrdersList.innerHTML = '<div class="rb-drawer-empty">لا توجد مسودات مسجلة في هذا الفرع.</div>';
            if (data.has_more) {
                const more = document.createElement('button');
                more.type = 'button';
                more.dataset.draftMore = String(page + 1);
                more.className = 'rb-load-more';
                more.textContent = 'عرض المزيد من المسودات';
                serverOrdersList.appendChild(more);
            }
        } catch (error) {
            if (request !== draftRequest || drawerMode !== 'draft') return;
            serverOrdersList.innerHTML = '<div class="rb-drawer-empty">تعذر تحميل المسودات. أعد المحاولة.</div>';
        }
    }

    function renderLocalDraftCard() {
        const draft = loadLocalDraft();

        if (!draft || !Array.isArray(draft.items) || !draft.items.length) {
            localDraftSection.hidden = true;
            localDraftContainer.innerHTML = '';
            return;
        }

        const itemCount = draft.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        const savedAt = draft.saved_at ? new Date(draft.saved_at) : null;
        const savedLabel = savedAt && !Number.isNaN(savedAt.getTime())
            ? savedAt.toLocaleString('ar')
            : '—';

        localDraftSection.hidden = false;
        localDraftContainer.innerHTML = `
            <div class="rb-local-draft-card">
                <div class="rb-local-draft-top">
                    <strong>مسودة محلية</strong>
                    <span>${itemCount} قطعة</span>
                </div>
                <div class="rb-local-draft-meta">
                    <span>${escapeHtml(locationName)}</span>
                    <span>${escapeHtml(savedLabel)}</span>
                </div>
                <div class="rb-local-draft-actions">
                    <button type="button" class="rb-draft-restore" id="rbPreviewLocalDraftBtn">عرض التفاصيل</button>
                    <button type="button" class="rb-draft-delete" id="rbDeleteDraftBtn">حذف</button>
                </div>
            </div>
        `;

        document.getElementById('rbPreviewLocalDraftBtn')?.addEventListener('click', () => {
            const current = loadLocalDraft();
            if (current) openDraftPreview(current, 'local');
        });
        document.getElementById('rbDeleteDraftBtn')?.addEventListener('click', deleteLocalDraft);
    }

    function filterServerOrders() {
        if (drawerMode === 'draft') return;
        const query = (drawerSearchInput.value || '').trim().toLowerCase();

        serverOrdersList.querySelectorAll('[data-server-order]').forEach(card => {
            const matchesQuery = !query || (card.dataset.search || '').includes(query);

            let matchesMode = true;
            if (drawerMode === 'draft') {
                matchesMode = (card.dataset.status || '') === 'draft';
            }

            const wrapper = card.closest('.rb-server-order-wrapper') || card;
            wrapper.hidden = !(matchesQuery && matchesMode);
        });
    }

    async function loadQrOrders(page = 1) {
        const request = ++qrRequest;
        if (page === 1) serverOrdersList.innerHTML = '<div class="rb-drawer-empty">جار تحميل طلبات QR...</div>';
        const url = new URL(qrOrdersUrl, window.location.origin);
        url.searchParams.set('location_id', locationId);
        url.searchParams.set('page', page);
        if (drawerSearchInput.value.trim()) url.searchParams.set('q', drawerSearchInput.value.trim());
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('تعذر تحميل طلبات QR.');
            const data = await response.json();
            if (request !== qrRequest || drawerMode !== 'qr') return;
            if (page === 1) serverOrdersList.replaceChildren();
            serverOrdersList.querySelector('[data-qr-more]')?.remove();
            data.orders.forEach(order => {
                const card = document.createElement('details');
                card.className = 'rb-server-order-card';
                const items = Array.isArray(order.items) ? order.items : [];
                card.innerHTML = `<summary><span class="rb-server-order-top"><strong>${escapeHtml(order.number)}</strong><span>${escapeHtml(order.status)}</span></span>
                    <span class="rb-server-order-meta"><span>${escapeHtml(order.service)}</span><span>${escapeHtml(order.customer)}</span></span></summary>
                    <ul class="rb-qr-order-items">${items.length
                        ? items.map(item => `<li>${escapeHtml(item.name || 'منتج')} × ${escapeHtml(item.quantity)}</li>`).join('')
                        : '<li>لا توجد أصناف مسجلة.</li>'}</ul>
                    ${order.url ? `<a class="rb-qr-order-open" href="${escapeHtml(order.url)}">فتح الطلب</a>` : ''}`;
                serverOrdersList.appendChild(card);
            });
            if (!serverOrdersList.children.length) serverOrdersList.innerHTML = '<div class="rb-drawer-empty">لا توجد طلبات QR مطابقة في هذا الفرع.</div>';
            if (data.has_more) {
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'rb-load-more';
                more.dataset.qrMore = String(page + 1);
                more.textContent = 'عرض المزيد من طلبات QR';
                serverOrdersList.appendChild(more);
            }
        } catch (error) {
            if (request !== qrRequest || drawerMode !== 'qr') return;
            serverOrdersList.insertAdjacentHTML('beforeend', '<div class="rb-drawer-empty">تعذر تحميل طلبات QR. أعد المحاولة بالبحث.</div>');
        }
    }

    async function loadReceiptSearch() {
        const request = ++receiptRequest;
        serverOrdersList.innerHTML = '<div class="rb-drawer-empty">جار البحث في الفواتير...</div>';
        const url = new URL(receiptSearchUrl, window.location.origin);
        url.searchParams.set('location_id', String(locationId));
        if (drawerSearchInput.value.trim()) url.searchParams.set('q', drawerSearchInput.value.trim());
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('تعذر البحث');
            const data = await response.json();
            if (request !== receiptRequest || drawerMode !== 'receipts') return;
            serverOrdersList.replaceChildren();
            (data.orders || []).forEach(order => {
                const card = document.createElement('a');
                card.href = order.print_url;
                card.target = '_blank';
                card.rel = 'noopener';
                card.className = 'rb-server-order-card';
                card.innerHTML = `<div class="rb-server-order-top"><strong>${escapeHtml(order.number)}</strong><span>فاتورة</span></div>
                    <div class="rb-server-order-meta"><span>${escapeHtml(order.invoice_number || '')}</span><span>إعادة الطباعة</span></div>`;
                serverOrdersList.appendChild(card);
            });
            if (!serverOrdersList.children.length)
                serverOrdersList.innerHTML = '<div class="rb-drawer-empty">لا توجد فواتير مطابقة ضمن صلاحياتك في هذا الفرع.</div>';
        } catch (_) {
            if (request === receiptRequest && drawerMode === 'receipts')
                serverOrdersList.innerHTML = '<div class="rb-drawer-empty">تعذر تحميل الفواتير. تحقق من الاتصال ثم أعد البحث.</div>';
        }
    }

    function openDrawer(mode = 'all', query = '') {
        drawerMode = mode;
        drawerSearchInput.value = query || '';

        if (mode === 'receipts') {
            qrRequest++;
            draftRequest++;
            drawerTitle.textContent = 'البحث عن فاتورة سابقة';
            drawerSubtitle.textContent = 'إعادة الطباعة دون إنشاء طلب أو تحصيل جديد';
            serverOrdersTitle.textContent = 'الفواتير المتاحة';
            localDraftSection.hidden = true;
            loadReceiptSearch();
        } else if (mode === 'qr') {
            drawerTitle.textContent = 'طلبات QR';
            drawerSubtitle.textContent = 'طلبات منيو العميل في هذا الفرع';
            serverOrdersTitle.textContent = 'طلبات QR';
            localDraftSection.hidden = true;
            loadQrOrders();
        } else if (mode === 'draft') {
            qrRequest++;
            serverOrdersList.innerHTML = '';
            drawerTitle.textContent = 'قائمة المسودات';
            drawerSubtitle.textContent = 'مسودات الجهاز + الطلبات المسجلة بحالة Draft';
            serverOrdersTitle.textContent = 'مسودات الخادم الحديثة';
            renderLocalDraftCard();
            loadServerDrafts();
        } else {
            qrRequest++;
            serverOrdersList.innerHTML = recentOrdersMarkup;
            drawerTitle.textContent = 'البحث في الطلبات الحالية';
            drawerSubtitle.textContent = locationName;
            serverOrdersTitle.textContent = 'آخر الطلبات المسجلة';
            localDraftSection.hidden = true;
        }

        if (mode !== 'qr' && mode !== 'receipts') filterServerOrders();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        drawerBackdrop.hidden = false;
        document.body.style.overflow = 'hidden';

        window.setTimeout(() => drawerSearchInput.focus(), 80);
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        drawerBackdrop.hidden = true;
        document.body.style.overflow = '';
    }

   let rbSubmitting = false;
   let rbCheckoutSucceeded = false;

   // Emergency recovery when a cashier closes/reloads the tab in a rush.
   // This uses the existing cashier- and branch-scoped draft key.
   window.addEventListener('pagehide', preserveUnfinishedCart);
   window.addEventListener('beforeunload', event => {
       if (!cart.size || rbCheckoutSucceeded) return;
       preserveUnfinishedCart();
       event.preventDefault();
       event.returnValue = '';
   });

function validatePosCheckoutConfirmation(response, data) {
    // A 2xx status with a proxy/HTML/truncated response is ambiguous.
    // Never rotate the retry UUID or tell the cashier the sale succeeded
    // unless the API confirms the persisted order identity.
    if (response.ok && (
        !data || data.ok !== true
        || !Number.isSafeInteger(Number(data.order_id))
        || Number(data.order_id) <= 0
    )) {
        throw new Error('لم يصل تأكيد حفظ الطلب من السيرفر. افحص الطلبات ثم أعد المحاولة بنفس العملية.');
    }
}

function safePosPrintNavigation(printWindow, url) {
    if (!printWindow) return false;
    if (!url) {
        try { printWindow.close(); } catch (_) {}
        return false;
    }
    try {
        printWindow.location.href = url;
        return true;
    } catch (error) {
        console.warn('POS receipt preview unavailable after saved checkout:', error);
        try { printWindow.close(); } catch (_) {}
        return false;
    }
}

async function submitOrder(action) {
    if (rbSubmitting || rbCheckoutSucceeded) {
        return;
    }

    if (!validateOrderBasics()) {
        return;
    }

    actionInput.value = action;

    if (action === 'kot_print') {
        const salesChannel = form.querySelector(
            '[name="sales_channel_id"]'
        );

        if (!salesChannel?.value) {
            openCheckout(action);
            return;
        }
    } else {
        if (!validatePaymentFields()) {
            openCheckout(action);
            return;
        }
    }

    syncHiddenItems();
    // A retry after an ambiguous server response MUST reach the original
    // idempotency token even if the last item sold out meanwhile.
    let hadMatchingCheckoutAttempt = false;
    try {
        const saved = JSON.parse(sessionStorage.getItem(posAttemptKey) || 'null');
        hadMatchingCheckoutAttempt = saved?.fingerprint === checkoutFingerprint();
    } catch (_) {}
    try { checkoutRequestToken(); } catch (error) {
        statusMessage(String(error.message || error), 'error');
        return;
    }

    rbSubmitting = true;
    // Warn about a slow request; never send a second checkout automatically.
    const slowCheckoutNotice = window.setTimeout(() => {
        if (!rbSubmitting || rbCheckoutSucceeded) return;
        preserveUnfinishedCart();
        const overlay = document.getElementById('rbPosSavingOverlay');
        if (overlay && !document.getElementById('rbPosSlowNotice')) {
            const message = document.createElement('p');
            message.id = 'rbPosSlowNotice';
            message.setAttribute('role', 'status');
            message.style.cssText = 'color:#fff;font-size:12px;margin:12px 0 0;max-width:320px;line-height:1.7';
            message.textContent = 'استجابة السيرفر متأخرة. لا تغلق الشاشة ولا تضغط دفع مرة ثانية. الطلب قيد التحقق.';
            overlay.firstElementChild?.appendChild(message);
        }
    }, 12000);

    const buttons = [
        billPaymentBtn,
        billPrintBtn,
        kotPrintBtn,
        checkoutContinueBtn,
        newOrderBtn,
    ];

    buttons.forEach(button => {
        if (button) {
            button.disabled = true;
        }
    });

    let loader = document.getElementById(
        'rbPosSavingOverlay'
    );

    if (!loader) {
        loader = document.createElement('div');

        loader.id = 'rbPosSavingOverlay';

        loader.innerHTML = `
            <div style="
                background:#111827;
                color:#fff;
                padding:22px 28px;
                border-radius:14px;
                min-width:280px;
                text-align:center;
                box-shadow:0 20px 60px rgba(0,0,0,.30)
            ">
                <div style="
                    width:34px;
                    height:34px;
                    margin:0 auto 12px;
                    border:3px solid rgba(255,255,255,.25);
                    border-top-color:#fff;
                    border-radius:50%;
                    animation:rbSavingSpin .7s linear infinite
                "></div>

                <strong>
                    جاري تنفيذ الطلب...
                </strong>

                <div style="
                    margin-top:6px;
                    font-size:11px;
                    opacity:.7
                ">
                    يرجى عدم الضغط مرة أخرى
                </div>
            </div>
        `;

        loader.style.cssText = `
            position:fixed;
            inset:0;
            z-index:2147483645;
            display:grid;
            place-items:center;
            background:rgba(15,23,42,.30);
            backdrop-filter:blur(2px);
        `;

        const style = document.createElement('style');

        style.textContent = `
            @keyframes rbSavingSpin {
                to {
                    transform:rotate(360deg);
                }
            }
        `;

        document.head.appendChild(style);
        (rbRoot || document.body).appendChild(loader);
    }

    let printWindow = null;

    if (
        action === 'bill_print'
        || action === 'kot_print'
    ) {
        printWindow = window.open(
            'about:blank',
            '_blank',
            'width=460,height=760'
        );

        if (printWindow) {
            printWindow.document.write(`
                <html lang="ar" dir="rtl">
                    <head>
                        <meta charset="utf-8">
                        <title>جاري تجهيز الطباعة</title>
                    </head>

                    <body style="
                        font-family:Arial;
                        text-align:center;
                        padding:40px
                    ">
                        جاري حفظ الطلب وتجهيز الطباعة...
                    </body>
                </html>
            `);

            printWindow.document.close();
        }
    }

    try {
        // Check active branch menu before committing. The server still
        // revalidates availability and stock atomically during checkout.
        if (!hadMatchingCheckoutAttempt) {
            try {
                await checkCartAvailability();
            } catch (error) {
                // No sale was submitted: a future attempt must check again.
                sessionStorage.removeItem(posAttemptKey);
                throw error;
            }
        }
        const response = await fetch(
            form.action,
            {
                method: 'POST',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },

                credentials: 'same-origin',

                body: new FormData(form),
            }
        );

        let data = null;

        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        validatePosCheckoutConfirmation(response, data);

        if (!response.ok) {
            printWindow?.close();

            const errorBag = data?.errors || {};
            const errors =
                Object.values(errorBag)
                    .flat()
                    .filter(Boolean);

            const message =
                errors.length
                    ? errors.join(' — ')
                    : (
                        data?.message
                        || 'تعذر تنفيذ الطلب.'
                    );

            const firstField =
                Object.keys(errorBag)[0]
                || null;

            /*
             * في الفاتورة/الدفع نعيد إظهار شاشة الدفع نفسها
             * ونضع الخطأ بداخلها حتى لا يضيع على المستخدم.
             */
            if (action !== 'kot_print') {
                openCheckout(action);
                showCheckoutError(
                    message,
                    firstField
                );
            }

            statusMessage(
                message,
                'error'
            );

            return;
        }

        localStorage.removeItem(draftKey);
        sessionStorage.removeItem(posAttemptKey);
        rbCheckoutSucceeded = true;

        // Printing is a separate action from checkout: a blocked popup or
        // printer problem must NEVER misreport a committed sale as a failure.
        const printRequested = action === 'bill_print' || action === 'kot_print';
        const printUrl = action === 'bill_print'
            ? data?.invoice_print_url
            : (data?.kitchen_dispatched ? data?.kitchen_print_url : null);
        const previewOpened = printRequested
            ? safePosPrintNavigation(printWindow, printUrl)
            : false;

        statusMessage(
            (data?.message || 'تم تنفيذ الطلب بنجاح.')
            + (printRequested && !previewOpened
                ? ' تم حفظ الطلب، لكن تعذرت معاينة الطباعة. افتح الطلب المسجل وأعد الطباعة، ولا تعِد تسجيل البيع.'
                : ''),
            'success'
        );

        // Stay on POS after a committed checkout. Reloading the same cashier
        // screen resets the cart, refreshes recent orders and prevents replay.
        window.setTimeout(() => {
            const url = new URL(cashierPosUrl, window.location.origin);
            url.searchParams.set('order_created', data.order_number || data.order_id);
            window.location.assign(url.toString());
        }, 700);

    } catch (error) {
        printWindow?.close();

        console.error(
            'POS submit error:',
            error
        );

        // A dropped response may follow a successfully committed checkout.
        // Keep the original request UUID; save the current cart so the same
        // signed-in cashier can restore it after reloading the terminal.
        if (cart.size) {
            try {
                localStorage.setItem(draftKey, JSON.stringify(serializeDraft()));
                renderLocalDraftCard();
            } catch (_) { /* Storage may be blocked by the browser. */ }
        }
        statusMessage(
            'تعذر تأكيد اتصال السيرفر. احتفظنا بمسودة الطلب لهذا الكاشير إن سمح المتصفح. افحص الطلبات قبل إعادة المحاولة؛ إذا لم يُسجل البيع فأعد المحاولة بنفس التفاصيل دون تغييرها.'
        );

    } finally {
        window.clearTimeout(slowCheckoutNotice);
        rbSubmitting = rbCheckoutSucceeded;

        buttons.forEach(button => {
            if (button) {
                button.disabled = rbCheckoutSucceeded;
            }
        });

        document
            .getElementById(
                'rbPosSavingOverlay'
            )
            ?.remove();
    }
}

    // -----------------------------
    // Product listeners
    // -----------------------------
    productGrid.addEventListener('click', event => {
        if (event.target.closest('[data-retry-products]')) {
            loadCatalog();
            return;
        }
        if (event.target.closest('[data-reset-products]')) {
            productSearch.value = '';
            activeCategory = '__all__';
            activeBrand = '__all__';
            renderFilters();
            loadCatalog();
            productSearch.focus();
            return;
        }
        const card = event.target.closest('[data-product-id]');
        if (!card) return;
        addProduct(card.dataset.productId);
    });

    productSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => loadCatalog(), 250);
    });
    productSearch.addEventListener('keydown', async event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        clearTimeout(searchTimer);
        const scanned = normalizeSearch(productSearch.value);
        if (!scanned) return;
        let match = [...productCache.values()].find(product => [product.barcode, product.sku]
            .some(value => normalizeSearch(value) === scanned));
        if (!match) {
            const result = await loadCatalog();
            if (!result) return;
            match = products.find(product => [product.barcode, product.sku]
                .some(value => normalizeSearch(value) === scanned));
        }
        if (!match) return;
        addProduct(match.id);
        productSearch.value = '';
        loadCatalog();
    });
    loadMoreButton.addEventListener('click', () => {
        if (!catalogLoading && hasMoreProducts) loadCatalog(nextCatalogPage, true);
    });

    categoryTabs.addEventListener('click', event => {
        const button = event.target.closest('[data-category]');
        if (!button) return;

        activeCategory = button.dataset.category;
        categorySelect.value = activeCategory;
        renderFilters();
        loadCatalog();
    });

    categorySelect.addEventListener('change', () => {
        activeCategory = categorySelect.value;
        renderFilters();
        loadCatalog();
    });

    brandSelect.addEventListener('change', () => {
        activeBrand = brandSelect.value;
        loadCatalog();
    });

    productOptionsBody.addEventListener('change', event => {
        if (event.target.name === 'rb_variant') {
            renderProductGroups();
        } else {
            const choice = event.target.closest('.rb-option-choice');
            if (event.target.matches('[data-modifier-id]')) {
                const quantity = choice.querySelector('select');
                if (quantity) quantity.disabled = !event.target.checked;
            }
            updateProductOptionsPrice();
        }
        document.getElementById('rbOptionsError')?.setAttribute('hidden', '');
    });
    productOptionsModal.querySelectorAll('[data-close-product-options]').forEach(button => {
        button.addEventListener('click', closeProductOptions);
    });
    addConfiguredProductButton.addEventListener('click', confirmProductOptions);

    // -----------------------------
    // Cart listeners
    // -----------------------------
    cartItems.addEventListener('click', event => {
        const actionButton = event.target.closest('[data-cart-action]');
        if (actionButton) {
            const key = actionButton.dataset.id;
            const item = cart.get(key);
            if (!item) return;

            if (actionButton.dataset.cartAction === 'plus') {
                item.quantity += 1;
            } else if (actionButton.dataset.cartAction === 'minus') {
                item.quantity -= 1;
                if (item.quantity <= 0) cart.delete(key);
            } else if (actionButton.dataset.cartAction === 'remove') {
                cart.delete(key);
            }

            renderCart();
            return;
        }

        const noteButton = event.target.closest('[data-note-toggle]');
        if (noteButton) {
            const key = noteButton.dataset.noteToggle;
            const wrap = [...cartItems.querySelectorAll('[data-note-wrap]')]
                .find(node => node.dataset.noteWrap === key);
            if (!wrap) return;
            wrap.hidden = !wrap.hidden;
            if (!wrap.hidden) {
                wrap.querySelector('[data-kitchen-note]')?.focus();
            }
        }
    });

    cartItems.addEventListener('input', event => {
        const noteInput = event.target.closest('[data-kitchen-note]');
        if (!noteInput) return;

        const key = noteInput.dataset.kitchenNote;
        const item = cart.get(key);
        if (!item) return;

        item.kitchen_notes = noteInput.value.slice(0, 500);
        syncHiddenItems();
    });

    // -----------------------------
    // Order / payment listeners
    // -----------------------------
    serviceType.addEventListener('change', syncServiceType);
    paymentArrangement.addEventListener('change', syncPayment);
    paymentMethod.addEventListener('change', syncPayment);

    [discountType, discountValue].forEach(element => {
        element?.addEventListener('input', renderSummary);
        element?.addEventListener('change', renderSummary);
    });
    form.querySelector('[name="sales_channel_id"]')?.addEventListener('change', renderSummary);

    newOrderBtn.addEventListener('click', () => clearOrder(true));
    tableOrderBtn?.addEventListener('click', () => {
        serviceType.value = 'dine_in';
        syncServiceType();
        tableSelect.focus();
        statusMessage('اختر الطاولة لبدء طلب صالة.');
    });
    openCheckoutBtn.addEventListener('click', () => openCheckout('bill_payment'));
    saveDraftBtn.addEventListener('click', saveLocalDraft);
    billPaymentBtn.addEventListener('click', () => openCheckout('bill_payment'));
    document.getElementById('rbMobileCartOpen').addEventListener('click', () => {
        document.querySelector('.rb-order-panel')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
    document.getElementById('rbMobileCheckoutBtn').addEventListener('click', () => {
        if (!cart.size) return;
        openCheckout('bill_payment');
    });
    quickCashBtn.addEventListener('click', () => {
        const payNow = [...paymentArrangement.options].find(option => option.value === 'pay_now' && !option.disabled);
        const cash = [...paymentMethod.options].find(option => option.value && option.dataset.type === 'cash' && !option.disabled);
        if (!payNow || !cash) {
            showCheckoutError('الدفع النقدي الفوري غير مفعّل في إعدادات هذا الفرع.', 'payment_method_id');
            return;
        }
        // Prefill valid branch configuration only. Never submit or record money
        // without the cashier explicitly confirming the ordinary checkout.
        paymentArrangement.value = 'pay_now';
        splitTenderToggle.checked = false;
        paymentMethod.value = cash.value;
        syncPayment();
        const discountAlreadyEntered = discountType.value !== 'none' && Number(discountValue.value || 0) > 0;
        const notesAlreadyEntered = Boolean(form.querySelector('[name="notes"]')?.value?.trim());
        const canCompact = !discountAlreadyEntered && !notesAlreadyEntered;
        checkoutModal.classList.toggle('rb-quick-cash', canCompact);
        quickCashExpand.hidden = !canCompact;
        checkoutContinueBtn.focus();
    });
    quickCashExpand.addEventListener('click', () => {
        checkoutModal.classList.remove('rb-quick-cash');
        quickCashExpand.hidden = true;
    });
    billPrintBtn.addEventListener('click', () => openCheckout('bill_print'));
    kotPrintBtn.addEventListener('click', () => submitOrder('kot_print'));

    checkoutContinueBtn.addEventListener(
        'click',
        async () => {
            if (rbSubmitting) {
                return;
            }

            if (
                !validateOrderBasics()
                || !validatePaymentFields()
            ) {
                return;
            }

            /*
             * لا نغلق Modal قبل رد السيرفر.
             * إذا رجع 422 سيشاهد المستخدم الخطأ داخل نفس نافذة الدفع.
             */
            await submitOrder(
                pendingAction
            );
        }
    );

    checkoutModal.querySelectorAll('[data-close-checkout]').forEach(element => {
        element.addEventListener('click', closeCheckout);
    });

    // -----------------------------
    // Drawer / existing orders
    // -----------------------------
    async function openQueuedCancel(orderId) {
        queuedCancelOrderId = orderId;
        queuedCancelPending = false;
        queuedCancelReason.value = '';
        queuedCancelConfirm.textContent = 'إلغاء العنصر';
        queuedCancelConfirm.disabled = true;
        queuedCancelError.hidden = false;
        queuedCancelError.textContent = 'جار تحميل حالة المطبخ...';
        queuedCancelSelect.replaceChildren();
        closeDrawer();
        queuedCancelModal.hidden = false;
        queuedCancelModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        try {
            const url = new URL(queuedLinesUrlPattern.replace('__ORDER__', String(orderId)), window.location.origin);
            const response = await fetch(url, {headers:{Accept:'application/json'},credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'تعذر التحقق من الطلب.');
            if (queuedCancelOrderId !== orderId) return;
            document.getElementById('rbQueuedCancelTitle').textContent = 'إلغاء عنصر — ' + data.number;
            (data.items || []).forEach(item => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.disabled = !item.eligible;
                option.textContent = item.name + ' × ' + item.quantity + ' — ' + item.status;
                queuedCancelSelect.appendChild(option);
            });
            const available = [...queuedCancelSelect.options].find(option => !option.disabled);
            if (available) {
                queuedCancelSelect.value = available.value;
                queuedCancelConfirm.disabled = false;
                queuedCancelError.hidden = true;
            } else {
                queuedCancelError.textContent = 'لا توجد عناصر يمكن إلغاؤها قبل التحضير، أو أن الطلب مرتبط بدفعة. راجع المدير والمطبخ.';
            }
        } catch (error) {
            if (queuedCancelOrderId === orderId) queuedCancelError.textContent = error.message || 'تعذر تحميل حالة المطبخ.';
        }
    }
    function closeQueuedCancel() {
        queuedCancelOrderId = null;
        queuedCancelPending = false;
        queuedCancelModal.hidden = true;
        queuedCancelModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    queuedCancelModal.querySelectorAll('[data-close-queued-cancel]').forEach(button =>
        button.addEventListener('click', closeQueuedCancel));
    queuedCancelSelect.addEventListener('change', () => {
        queuedCancelPending = false;
        queuedCancelConfirm.textContent = 'إلغاء العنصر';
    });
    queuedCancelReason.addEventListener('input', () => {
        queuedCancelPending = false;
        queuedCancelConfirm.textContent = 'إلغاء العنصر';
    });
    queuedCancelConfirm.addEventListener('click', async () => {
        if (!queuedCancelOrderId || queuedCancelConfirm.disabled || !queuedCancelSelect.value) return;
        const reason = queuedCancelReason.value.trim();
        if (reason.length < 8) {
            queuedCancelError.textContent = 'اكتب سبب الإلغاء بثمانية أحرف على الأقل.';
            queuedCancelError.hidden = false;
            return;
        }
        if (!queuedCancelPending) {
            queuedCancelPending = true;
            queuedCancelConfirm.textContent = 'تأكيد إلغاء العنصر';
            queuedCancelError.textContent = 'تأكيد نهائي: ستُحدّث الفاتورة والمطبخ والمخزون. اضغط الزر مرة ثانية.';
            queuedCancelError.hidden = false;
            return;
        }
        queuedCancelConfirm.disabled = true;
        try {
            const response = await fetch(queuedCancelUrlPattern.replace('__ORDER__', String(queuedCancelOrderId)), {
                method:'POST',
                headers:{Accept:'application/json','Content-Type':'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                credentials:'same-origin',
                body:JSON.stringify({order_item_id:Number(queuedCancelSelect.value),cancellation_reason:reason}),
            });
            const data = await response.json();
            if (!response.ok || !data.ok)
                throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'تعذر إلغاء العنصر.');
            closeQueuedCancel();
            statusMessage('تم إلغاء العنصر وتحديث الطلب والمطبخ والمحاسبة.', 'success');
            window.location.assign(cashierPosUrl);
        } catch (error) {
            queuedCancelError.textContent = error.message || 'تعذر تأكيد الإلغاء؛ تحقق من حالة الطلب.';
            queuedCancelError.hidden = false;
            queuedCancelConfirm.disabled = false;
            queuedCancelPending = false;
            queuedCancelConfirm.textContent = 'إلغاء العنصر';
        }
    });
    qrOrdersBtn.addEventListener('click', () => openDrawer('qr'));
    draftListBtn.addEventListener('click', () => openDrawer('draft'));
    receiptsBtn.addEventListener('click', () => openDrawer('receipts'));

    existingOrderSearch.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        openDrawer('all', existingOrderSearch.value);
    });

    existingOrderSearch.addEventListener('focus', () => {
        if (!existingOrderSearch.value) return;
    });

    drawerSearchInput.addEventListener('input', () => {
        if (drawerMode === 'receipts') {
            clearTimeout(receiptSearchTimer);
            receiptSearchTimer = setTimeout(loadReceiptSearch, 250);
        } else if (drawerMode === 'draft') {
            clearTimeout(draftSearchTimer);
            draftSearchTimer = setTimeout(() => loadServerDrafts(), 250);
        } else if (drawerMode === 'qr') {
            clearTimeout(qrSearchTimer);
            qrSearchTimer = setTimeout(() => loadQrOrders(), 250);
        } else filterServerOrders();
    });
    serverOrdersList.addEventListener('click', event => {
        const queued = event.target.closest('[data-queued-cancel-order]');
        if (queued) {
            openQueuedCancel(Number(queued.dataset.queuedCancelOrder));
            return;
        }
        const more = event.target.closest('[data-qr-more]');
        if (more) loadQrOrders(Number(more.dataset.qrMore));
        const draftMore = event.target.closest('[data-draft-more]');
        if (draftMore) loadServerDrafts(Number(draftMore.dataset.draftMore));
    });
    draftPreviewModal.querySelectorAll('[data-close-draft-preview]').forEach(button =>
        button.addEventListener('click', closeDraftPreview));
    draftPreviewCancel.addEventListener('click', async () => {
        if (!activeServerDraft?.cancelable || draftPreviewCancel.disabled) return;
        const reason = draftCancelReason.value.trim();
        if (reason.length < 8) {
            draftEditError.textContent = 'أدخل سبب الإلغاء بثمانية أحرف على الأقل.';
            draftEditError.hidden = false;
            return;
        }
        if (!pendingDraftCancellation) {
            pendingDraftCancellation = true;
            draftPreviewCancel.textContent = 'تأكيد إلغاء المسودة';
            draftEditError.textContent = 'تأكيد نهائي: سيتم إلغاء المسودة وتوثيق السبب. اضغط تأكيد الإلغاء مرة ثانية.';
            draftEditError.hidden = false;
            draftPreviewCancel.focus();
            return;
        }
        draftPreviewCancel.disabled = true;
        draftEditError.hidden = true;
        try {
            const response = await fetch(draftCancelUrlPattern.replace('__ORDER__', String(activeServerDraft.id)), {
                method: 'POST',
                headers: {'Accept':'application/json', 'Content-Type':'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                credentials: 'same-origin',
                body: JSON.stringify({cancellation_reason: reason}),
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'تعذر إلغاء الطلب.');
            closeDraftPreview();
            statusMessage('تم إلغاء المسودة مع تسجيل السبب.', 'success');
            openDrawer('draft');
        } catch (error) {
            draftEditError.textContent = error.message || 'تعذر تأكيد الإلغاء.';
            draftEditError.hidden = false;
        } finally {
            draftPreviewCancel.disabled = false;
        }
    });
    draftPreviewSave.addEventListener('click', async () => {
        if (!activeServerDraft?.editable || draftPreviewSave.disabled) return;
        const removedIds = activeServerDraft.items
            .filter(item => draftPreviewBody.querySelector('[data-draft-remove="' + item.id + '"]')?.checked)
            .map(item => item.id);
        const items = activeServerDraft.items
            .filter(item => !removedIds.includes(item.id))
            .map(item => ({
                id: item.id,
                quantity: Number(draftPreviewBody.querySelector('[data-draft-quantity="' + item.id + '"]')?.value),
                kitchen_notes: draftPreviewBody.querySelector('[data-draft-notes="' + item.id + '"]')?.value || '',
            }));
        if (!items.length) {
            draftEditError.textContent = 'لا يمكن حذف جميع العناصر. لإلغاء الطلب بالكامل استخدم زر إلغاء الطلب.';
            draftEditError.hidden = false;
            return;
        }
        if (items.some(item => !Number.isFinite(item.quantity) || item.quantity <= 0)) {
            draftEditError.textContent = 'يجب أن تكون جميع الكميات أكبر من صفر.';
            draftEditError.hidden = false;
            return;
        }
        draftPreviewSave.disabled = true;
        draftEditError.hidden = true;
        try {
            const response = await fetch(draftEditUrlPattern.replace('__ORDER__', String(activeServerDraft.id)), {
                method: 'PATCH',
                headers: {'Accept':'application/json', 'Content-Type':'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                credentials: 'same-origin',
                body: JSON.stringify({items, remove_item_ids: removedIds}),
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'تعذر تحديث المسودة.');
            closeDraftPreview();
            statusMessage('تم تعديل المسودة المحفوظة بنجاح.', 'success');
            openDrawer('draft');
        } catch (error) {
            draftEditError.textContent = error.message || 'فشل الاتصال أثناء حفظ المسودة؛ تحقق قبل تكرار العملية.';
            draftEditError.hidden = false;
        } finally {
            draftPreviewSave.disabled = false;
        }
    });
    draftPreviewRestore.addEventListener('click', () => {
        if (currentDraftPreview !== 'local') return;
        closeDraftPreview();
        restoreLocalDraft();
    });
    closeDrawerBtn.addEventListener('click', closeDrawer);
    drawerBackdrop.addEventListener('click', closeDrawer);

    // -----------------------------
    // Keyboard shortcuts useful for cashier operation
    // -----------------------------
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (!queuedCancelModal.hidden) {
                closeQueuedCancel();
                return;
            }
            if (!draftPreviewModal.hidden) {
                closeDraftPreview();
                return;
            }
            if (quickCustomerModal && !quickCustomerModal.hidden) {
                closeQuickCustomer();
                return;
            }
            if (newOrderConfirm && !newOrderConfirm.hidden) {
                finishNewOrderConfirmation(false);
                return;
            }
            if (!productOptionsModal.hidden) {
                closeProductOptions();
                return;
            }
            if (!checkoutModal.hidden) {
                closeCheckout();
                return;
            }
            closeDrawer();
        }

        if (event.key === 'F2') {
            event.preventDefault();
            productSearch.focus();
            productSearch.select();
        }

        if (event.key === 'F4') {
            event.preventDefault();
            saveLocalDraft();
        }

        if (event.key === 'F8') {
            event.preventDefault();
            openCheckout('bill_payment');
        }
    });

    // Keep every POS submission inside the single AJAX flow.
    form.addEventListener('submit', event => {
        event.preventDefault();

        if (rbSubmitting) {
            return;
        }

        if (!validateOrderBasics()) {
            return;
        }

        submitOrder(
            actionInput.value
            || 'bill_payment'
        );
    });

    // Populate the whole branch catalogue once (also for future larger menus).
    // Filtering/navigation after warm-up is memory-only; the explicit refresh
    // control is the way to pick up revised prices/availability immediately.
    async function warmCatalog() {
        if (completeCatalog) return;
        const collected = products.slice();
        let page = 2;
        try {
            while (page <= 20) {
                const url = new URL(catalogUrl, window.location.origin);
                url.searchParams.set('location_id', locationId);
                url.searchParams.set('page', page);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                if (!Array.isArray(data.products)) return;
                collected.push(...data.products);
                if (!data.has_more) {
                    const byId = new Map(collected.map(product => [Number(product.id), product]));
                    if (byId.size !== Number(data.total)) return;
                    allProducts = [...byId.values()];
                    completeCatalog = true;
                    allProducts.forEach(product => productCache.set(Number(product.id), product));
                    saveCompleteCatalog();
                    filterLoadedCatalog();
                    return;
                }
                page++;
            }
        } catch (_) { /* Existing paginated server search remains available. */ }
    }

    document.getElementById('rbRefreshCatalog')?.addEventListener('click', () => {
        try { sessionStorage.removeItem(catalogStorageKey); } catch (_) {}
        completeCatalog = false;
        allProducts = [];
        products = [];
        productCache.clear();
        activeCategory = '__all__';
        activeBrand = '__all__';
        productSearch.value = '';
        renderFilters();
        loadCatalog(1, false, true).then(data => {
            if (!data) return;
            if (!data.has_more) {
                allProducts = data.products.slice();
                completeCatalog = true;
                saveCompleteCatalog();
            } else {
                warmCatalog();
            }
        });
    });

    // Initial render
    renderFilters();
    renderCart();
    syncServiceType();
    syncPayment();
    renderLocalDraftCard();
    if (completeCatalog) filterLoadedCatalog();
    else { renderProducts(); warmCatalog(); }
    if (Array.isArray(oldItems) && oldItems.length) {
        loadProductsByIds(oldItems.map(item => item.product_id))
            .then(availableProducts => {
                const available = new Map(availableProducts.map(product => [Number(product.id), product]));
                oldItems.forEach(item => {
                    const product = available.get(Number(item.product_id));
                    if (!product) return;
                    const row = makeCartItem(product, item.product_variant_id, item.modifiers,
                        item.quantity, item.kitchen_notes);
                    if (row) cart.set(row.key, row);
                });
                renderCart();
            })
            .catch(() => statusMessage('تعذر استرجاع أصناف الطلب السابق.', 'error'));
    }
});
</script>
@endsection

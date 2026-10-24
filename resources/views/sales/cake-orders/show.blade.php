@extends('layouts.app')

@section('title', 'طلب كيك: ' . $cakeOrder->order_number)

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | حالات الطلب
    |--------------------------------------------------------------------------
    */
    $statusLabels = collect(\App\Enums\CakeOrderStatus::cases())
        ->mapWithKeys(fn ($status) => [
            $status->value => $status->label()
        ])
        ->all();

    $statusLabels = array_merge([
        'pending_deposit' => 'بانتظار دفع العربون',
        'deposit_paid' => 'تم دفع العربون',
        'in_decoration' => 'قيد التزيين',
        'dispatched_to_branch' => 'تم الإرسال إلى الفرع',
        'received_at_branch' => 'تم الاستلام في الفرع',
        'ready_for_pickup' => 'جاهز لاستلام العميل',
        'delivered' => 'مكتمل',
        'canceled' => 'ملغى',
    ], $statusLabels);

    $statusValue = $cakeOrder->status instanceof \BackedEnum
        ? $cakeOrder->status->value
        : (string) $cakeOrder->status;

    $statusLabel = $statusLabels[$statusValue] ?? 'غير محدد';

    $latestPayment = $cakeOrder->payments
        ?->sortByDesc('created_at')
        ->first();

    $allowed = $allowedTransitions ?? [];
    $cancelledHasFinancialRecords = $cakeOrder->status === \App\Enums\CakeOrderStatus::Cancelled
        && ($cakeOrder->payments->isNotEmpty() || $cakeOrder->invoice()->exists());


    /*
    |--------------------------------------------------------------------------
    | القيم العربية لمواصفات الكيك
    |--------------------------------------------------------------------------
    */

    $systemValueLabels = [

        'cake_type' => [
            'chocolate' => 'شوكولاتة',
            'vanilla' => 'فانيلا',
            'red_velvet' => 'ريد فيلفت',
            'strawberry' => 'فراولة',
            'fruit' => 'فواكه',
            'caramel' => 'كراميل',
            'coffee' => 'قهوة',
            'oreo' => 'أوريو',
            'lotus' => 'لوتس',
            'custom' => 'حسب الطلب',
        ],

        'cake_size' => [
            'small' => 'صغير',
            'medium' => 'متوسط',
            'large' => 'كبير',
            'x_large' => 'كبير جداً',
            'extra_large' => 'كبير جداً',
            'mini' => 'ميني',
            '30x30' => '30 × 30 سم',
            '40x30' => '40 × 30 سم',
            '60x40' => '60 × 40 سم',
            '120x30' => '120 × 30 سم',
            '120x80' => '120 × 80 سم',
            'standard' => 'ستاندر',
        ],

        'flavor' => [
            'chocolate' => 'شوكولاتة',
            'vanilla' => 'فانيلا',
            'strawberry' => 'فراولة',
            'red_velvet' => 'ريد فيلفت',
            'caramel' => 'كراميل',
            'coffee' => 'قهوة',
            'oreo' => 'أوريو',
            'lotus' => 'لوتس',
            'lemon' => 'ليمون',
            'orange' => 'برتقال',
            'mixed' => 'مشكل',
        ],

        'filling' => [
            'chocolate' => 'شوكولاتة',
            'vanilla' => 'فانيلا',
            'cream' => 'كريمة',
            'strawberry' => 'فراولة',
            'caramel' => 'كراميل',
            'lotus' => 'لوتس',
            'oreo' => 'أوريو',
            'fruit' => 'فواكه',
            'nuts' => 'مكسرات',
            'none' => 'بدون حشو',
        ],

        'shape' => [
            'round' => 'دائري',
            'slab' => 'بلاطة',
            'wedding_tiers' => 'طوابق أفراح',
            'standard_tiers' => 'طوابق ستاندر',
            'square' => 'مربع',
            'rectangle' => 'مستطيل',
            'rectangular' => 'مستطيل',
            'heart' => 'قلب',
            'oval' => 'بيضاوي',
            'custom' => 'شكل خاص',
        ],

        'color' => [
            'white' => 'أبيض',
            'black' => 'أسود',
            'red' => 'أحمر',
            'blue' => 'أزرق',
            'green' => 'أخضر',
            'yellow' => 'أصفر',
            'pink' => 'زهري',
            'purple' => 'بنفسجي',
            'gold' => 'ذهبي',
            'silver' => 'فضي',
            'brown' => 'بني',
            'orange' => 'برتقالي',
            'beige' => 'بيج',
            'mixed' => 'ألوان متعددة',
        ],
    ];


    /*
    |--------------------------------------------------------------------------
    | دالة تحويل القيمة النظامية للعربي
    |--------------------------------------------------------------------------
    */

    $formatSystemValue = function ($field, $value) use ($systemValueLabels) {

        if ($value === null || $value === '') {
            return '—';
        }

        $stringValue = trim((string) $value);

        /*
         * إذا كانت القيمة مكتوبة بالعربي أصلاً،
         * نعرضها كما هي.
         */
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $stringValue)) {
            return $stringValue;
        }

        $key = strtolower($stringValue);

        return $systemValueLabels[$field][$key]
            ?? 'غير محدد';
    };


    /*
    |--------------------------------------------------------------------------
    | ترتيب الدفع
    |--------------------------------------------------------------------------
    */

    $paymentArrangementLabels = [
        'pay_now' => 'دفع فوري',
        'deposit' => 'دفع عربون',
        'partial_payment' => 'دفع جزئي',
        'pay_on_pickup' => 'الدفع عند الاستلام',
        'pending_verification' => 'بانتظار التحقق من الدفع',
    ];


    /*
    |--------------------------------------------------------------------------
    | نوع الصورة على الكيك
    |--------------------------------------------------------------------------
    */

    $coverTypes = [
        'none' => 'بدون صورة مطبوعة',
        'edible_sugar' => 'طباعة سكر قابلة للأكل',
        'removable_cardboard' => 'صورة كرتونية قابلة للإزالة',
        'sugar_print' => 'طباعة سكر قابلة للأكل',
        'photo_print' => 'صورة مطبوعة على الكيك',
        'custom' => 'تصميم خاص',
    ];


    /*
    |--------------------------------------------------------------------------
    | أنواع المرفقات
    |--------------------------------------------------------------------------
    */

    $attachmentTypes = [
        'reference_image' => 'صورة مرجعية',
        'customer_design' => 'تصميم العميل',
        'final_cake_image' => 'صورة الكيك النهائي',
        'other' => 'صورة أخرى',
    ];


    /*
    |--------------------------------------------------------------------------
    | حقول مواصفات الكيك
    |--------------------------------------------------------------------------
    */

    $cakeSpecFields = [
        'cake_type' => 'النوع',
        'cake_size' => 'الحجم',
        'layers_count' => 'عدد الطبقات',
        'cake_weight' => 'الوزن (كغم)',
        'flavor' => 'النكهة',
        'filling' => 'الحشو',
        'shape' => 'الشكل',
        'color' => 'اللون',
        'theme' => 'الثيم',
        'cake_text' => 'النص على الكيك',
        'persons_count' => 'عدد الأشخاص',
        'special_instructions' => 'تعليمات خاصة',
    ];

    /*
     * الحقول التي تعتبر قيمًا نظامية
     * ويجب ترجمتها للعربي.
     */
    $translatedSpecFields = [
        'cake_type',
        'cake_size',
        'flavor',
        'filling',
        'shape',
        'color',
    ];
@endphp


{{-- =========================================================
     رأس الصفحة
========================================================= --}}

@if($cancelledHasFinancialRecords)
    <div role="alert" style="margin-bottom:1rem;border:1px solid #e5bb6d;background:#fff9ed;border-radius:12px;padding:1rem;line-height:1.8">
        <strong>تنبيه محاسبي: طلب ملغي وله سجلات مالية</strong>
        <div>إلغاء الطلب لا يعني استرداد المبلغ المدفوع أو تسويته تلقائيًا. يجب مراجعة الدفعات والفاتورة المرتبطة بهذا الطلب عبر الإجراءات المالية المعتمدة قبل إغلاق ملف العميل.</div>
        @php
            $canReviewCakeFinance = auth()->user()->isAdmin() || auth()->user()->canAny([
                'payments.record', 'payments.verify', 'payments.correct', 'payments.refund',
                'financial.branch.view', 'financial.global.view', 'financial.collections.view',
            ]);
            $financialLocationAllowed = auth()->user()->isAdmin()
                || auth()->user()->can('financial.global.view')
                || (int) (auth()->user()->primaryLocation()?->id ?? 0) === (int) $cakeOrder->origin_branch_id;
        @endphp
        <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:.8rem">
            @if($canReviewCakeFinance && $financialLocationAllowed && $cakeOrder->payments->isNotEmpty())
                <a class="btn btn-gold btn-sm" href="{{ route('payments.index', [
                    'order_type' => 'special_cake_order',
                    'order_id' => $cakeOrder->id,
                ]) }}">عرض الحركات المالية لهذا الطلب</a>
                @if($cakeOrder->payments->contains(fn ($payment) => $payment->location_payment_account_id
                    || in_array($payment->paymentMethod?->type, ['bank_transfer', 'electronic_wallet'], true)))
                    <a class="btn btn-outline btn-sm" href="{{ route('payments.bank-sales', ['search' => $cakeOrder->order_number]) }}">عرض الحوالات المرتبطة</a>
                @endif
            @endif
            @if($cakeOrder->invoice && (auth()->user()->isAdmin() || auth()->user()->can('invoices.view')))
                <a class="btn btn-outline btn-sm" href="{{ route('invoices.show', $cakeOrder->invoice) }}">فتح الفاتورة المرتبطة</a>
            @endif
        </div>
    </div>
@endif

@if($cakeOrder->archived_at)
    <div role="status" style="margin-bottom:1rem;padding:1rem;background:#f1f5f9;border-radius:12px">
        <strong>هذا الطلب مؤرشف</strong> — يبقى ظاهرًا من الفواتير والحوالات المالية.
        @can('delete', $cakeOrder)
            <form method="POST" action="{{ route('cake-orders.restore-archive', $cakeOrder) }}" style="display:inline-block;margin-right:.8rem">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">استعادة من الأرشيف</button>
            </form>
        @endcan
    </div>
@endif

<div class="page-actions">

    <div class="page-actions-title">
        {{ $cakeOrder->order_number }}
    </div>


    <div class="action-btns">

        @if(in_array('ready', $allowed, true))
            <form method="POST" action="{{ route('cake-orders.transition', $cakeOrder) }}">
                @csrf
                <input type="hidden" name="to_status" value="ready">
                <button type="submit" class="btn btn-gold btn-sm"
                    data-confirm="سيتم اعتماد اكتمال تنفيذ الكيك وتجهيز الطلبية للتحميل." data-confirm-title="اعتماد تنفيذ الكيك" data-confirm-type="success">
                    تم التنفيذ
                </button>
            </form>
        @endif

        @if(count($allowed) > 0)

            <button
                class="btn btn-gold btn-sm"
                type="button"
                onclick="openStatusModal()"
            >
                خيارات أخرى
            </button>

        @endif


        @can('update', $cakeOrder)

            <a
                href="{{ route('cake-orders.edit', $cakeOrder) }}"
                class="btn btn-outline btn-sm"
            >
                تعديل الطلب
            </a>

        @endcan


        @if($cakeOrder->status === \App\Enums\CakeOrderStatus::Cancelled && ! $cakeOrder->archived_at)
            @can('delete', $cakeOrder)
                <form method="POST" action="{{ route('cake-orders.archive', $cakeOrder) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">أرشفة الطلب</button>
                </form>
            @endcan
        @endif

        @if(in_array($cakeOrder->status, [\App\Enums\CakeOrderStatus::Draft, \App\Enums\CakeOrderStatus::Cancelled], true) && ! $cakeOrder->archived_at)
            @can('delete', $cakeOrder)
                @php
                    $cakeDeleteBlocked = $cakeOrder->payments()->exists()
                        || $cakeOrder->invoice()->exists()
                        || $cakeOrder->branchShipment()->exists()
                        || $cakeOrder->receivingIssues()->exists();
                @endphp
                @if(! $cakeDeleteBlocked)
                    <form action="{{ route('cake-orders.destroy', $cakeOrder) }}" method="POST"
                          data-confirm="هل تريد حذف طلب الكيك؟ سيتم إخفاؤه من سجلات الطلبات، ولا يمكن التراجع عن هذا الإجراء من الصفحة."
                          data-confirm-title="تأكيد حذف طلب الكيك" data-confirm-type="danger" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">حذف طلب الكيك</button>
                    </form>
                @else
                    <span class="text-muted" style="font-size:.8rem">الحذف غير متاح لوجود سجلات مالية أو تسليم مرتبطة بالطلب. استخدم الأرشفة للطلب الملغي.</span>
                @endif
            @endcan
        @endif

        <a
            href="{{ route('cake-orders.index') }}"
            class="btn btn-ghost btn-sm"
        >
            رجوع
        </a>

    </div>

</div>

@if($cakeOrder->is_urgent)
    <div
        style="
            margin:0 0 1rem;
            padding:.85rem 1rem;
            display:flex;
            align-items:flex-start;
            gap:.7rem;
            border:1px solid #fdba74;
            border-radius:12px;
            color:#9a3412;
            background:#fff7ed;
        "
    >
        <strong style="white-space:nowrap">
            ⚠ طلب طارئ
        </strong>

        <span>
            {{ $cakeOrder->urgent_reason ?: 'تم اعتماد هذا الطلب كتسليم طارئ لنفس اليوم.' }}
        </span>
    </div>
@endif


{{-- =========================================================
     مسار العملية
     يظهر مرة واحدة للطلب كاملًا
========================================================= --}}

<x-workflow-toolbar
    type="special_cake"
    :record="$cakeOrder"
/>

<x-branch-delivery-card
    :source="$cakeOrder"
    :shipment="$cakeOrder->branchShipment?->loadMissing(['driver.employee','receivingInvoice'])"
    source-type="special-cake"
/>


{{-- =========================================================
     البيانات الأساسية
========================================================= --}}

<div class="dashboard-row">

    {{-- بيانات الطلب --}}
    <div class="card">

        <div class="card-header">

            <span class="card-title">
                بيانات الطلب
            </span>

        </div>


        <div class="card-body">

            <table class="data-table">

                <tr>
                    <td style="color:var(--text-muted)">
                        رقم الطلب
                    </td>

                    <td>
                        <strong>
                            {{ $cakeOrder->order_number }}
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        العميل
                    </td>

                    <td>
                        {{ $cakeOrder->customer?->name ?? '—' }}

                        @if($cakeOrder->customer?->phone)
                            ({{ $cakeOrder->customer->phone }})
                        @endif
                    </td>
                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        الفرع
                    </td>

                    <td>
                        {{ $cakeOrder->originBranch?->name ?? '—' }}
                    </td>
                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        تاريخ التسليم
                    </td>

                    <td>

                        <strong>
                            {{ $cakeOrder->required_date?->format('Y-m-d') ?? '—' }}
                        </strong>

                        @if($cakeOrder->required_time)
                            الساعة
                            {{ $cakeOrder->required_time }}
                        @endif

                    </td>
                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        الحالة
                    </td>

                    <td>
                        <span class="badge badge-pending">
                            {{ $statusLabel }}
                        </span>
                    </td>
                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        السعر الإجمالي
                    </td>

                    <td>
                        ₪{{ number_format((float) $cakeOrder->total_price, 2) }}
                    </td>
                </tr>


                @if(
                    $cakeOrder->discount_type
                    && $cakeOrder->discount_type !== 'none'
                )

                    <tr>

                        <td style="color:var(--text-muted)">
                            الخصم
                        </td>

                        <td style="color:#e67e22">

                            — ₪{{ number_format((float) $cakeOrder->discount_amount, 2) }}

                            @if($cakeOrder->discount_type === 'percentage')

                                ({{ $cakeOrder->discount_value }}٪)

                            @else

                                (مبلغ ثابت)

                            @endif

                        </td>

                    </tr>


                    <tr>

                        <td style="color:var(--text-muted)">
                            الصافي بعد الخصم
                        </td>

                        <td>

                            <strong style="color:var(--gold)">
                                ₪{{ number_format((float) ($cakeOrder->net_price ?? $cakeOrder->total_price), 2) }}
                            </strong>

                        </td>

                    </tr>

                @endif


                @if($cakeOrder->deposit_paid ?? false)

                    <tr>

                        <td style="color:var(--text-muted)">
                            العربون المدفوع
                        </td>

                        <td>
                            ₪{{ number_format((float) $cakeOrder->deposit_paid, 2) }}
                        </td>

                    </tr>

                @endif

            </table>

        </div>

    </div>


    {{-- =====================================================
         بيانات الدفع والصورة
    ====================================================== --}}

    <div class="card">

        <div class="card-header">

            <span class="card-title">
                بيانات الدفع والصورة
            </span>

        </div>


        <div class="card-body">

            <table class="data-table">

                <tr>

                    <td style="color:var(--text-muted)">
                        ترتيب الدفع
                    </td>

                    <td>

                        @if(
                            $cakeOrder->payment_arrangement
                            instanceof
                            \App\Enums\PaymentArrangement
                        )

                            {{ $cakeOrder->payment_arrangement->label() }}

                        @else

                            {{
                                $paymentArrangementLabels[
                                    (string) $cakeOrder->payment_arrangement
                                ]
                                ?? 'غير محدد'
                            }}

                        @endif

                    </td>

                </tr>


                <tr>

                    <td style="color:var(--text-muted)">
                        طريقة الدفع
                    </td>

                    <td>
                        {{
                            $latestPayment?->paymentMethod?->name_ar
                            ?? $latestPayment?->paymentMethod?->name
                            ?? '—'
                        }}
                    </td>

                </tr>


                <tr>

                    <td style="color:var(--text-muted)">
                        المبلغ المدفوع
                    </td>

                    <td>

                        <strong>
                            {{
                                $latestPayment
                                    ? '₪' . number_format((float) $latestPayment->amount, 2)
                                    : '—'
                            }}
                        </strong>

                    </td>

                </tr>


                <tr>

                    <td style="color:var(--text-muted)">
                        رقم عملية الدفع
                    </td>

                    <td>
                        {{
                            $latestPayment?->reference_number
                            ?? $cakeOrder->reference_number
                            ?? '—'
                        }}
                    </td>

                </tr>


                <tr>
                    <td style="color:var(--text-muted)">
                        إثبات الدفع
                    </td>
                    <td>
                        @php
                            $proofPayment = $cakeOrder->payments
                                ->sortByDesc('created_at')
                                ->first(fn ($payment) => filled($payment->payment_proof));
                        @endphp
                        @if($proofPayment)
                            @can('view', $proofPayment)
                                @php
                                    $proofUrl = route('payments.proof', $proofPayment);
                                    $proofExtension = strtolower(pathinfo((string) $proofPayment->payment_proof, PATHINFO_EXTENSION));
                                @endphp
                                @if(in_array($proofExtension, ['jpg', 'jpeg', 'png', 'webp'], true))
                                    <a href="{{ $proofUrl }}" target="_blank" rel="noopener" aria-label="عرض صورة إثبات الدفع">
                                        <img src="{{ $proofUrl }}" alt="صورة إثبات الدفع" loading="lazy"
                                             style="display:block;max-width:260px;max-height:220px;width:auto;height:auto;object-fit:contain;border:1px solid var(--border,#ddd);border-radius:10px;margin-bottom:8px">
                                    </a>
                                @endif
                                <a class="btn btn-outline btn-sm" href="{{ $proofUrl }}" target="_blank" rel="noopener">
                                    {{ $proofExtension === 'pdf' ? 'عرض إثبات الدفع PDF' : 'عرض إثبات الدفع بالحجم الكامل' }}
                                </a>
                            @else
                                <span class="text-muted">إثبات الدفع متاح للمستخدمين المخولين بعرض الدفعات فقط.</span>
                            @endcan
                        @else
                            <span class="text-muted">لم يتم إرفاق إثبات دفع لهذا الطلب.</span>
                        @endif
                    </td>
                </tr>

                <tr>

                    <td style="color:var(--text-muted)">
                        نوع الصورة على الكيك
                    </td>

                    <td>
                        {{
                            $coverTypes[
                                $cakeOrder->image_cover_type ?? 'none'
                            ]
                            ?? 'غير محدد'
                        }}
                    </td>

                </tr>


                <tr>

                    <td style="color:var(--text-muted)">
                        المصنع
                    </td>

                    <td>
                        {{ $cakeOrder->factory?->name ?? '—' }}
                    </td>

                </tr>


                <tr>

                    <td style="color:var(--text-muted)">
                        أُنشئ بواسطة
                    </td>

                    <td>
                        {{
                            $cakeOrder->creator?->employee?->full_name
                            ?? $cakeOrder->creator?->display_name
                            ?? $cakeOrder->creator?->name
                            ?? '—'
                        }}
                    </td>

                </tr>

            </table>

        </div>

    </div>

</div>


{{-- =========================================================
     مواصفات الكيك
========================================================= --}}

<div
    class="card"
    style="margin-top:1.5rem"
>

    <div class="card-header">

        <span class="card-title">
            مواصفات الكيك
        </span>

    </div>


    <div class="card-body">

        <table class="data-table">

            @foreach($cakeSpecFields as $field => $label)

                @if(
                    ($field === 'cake_size' && is_array($cakeOrder->cake_sizes) && count($cakeOrder->cake_sizes))
                    || ($cakeOrder->$field !== null && $cakeOrder->$field !== '')
                )

                    <tr>

                        <td
                            style="
                                color:var(--text-muted);
                                width:160px;
                            "
                        >
                            {{ $label }}
                        </td>


                        <td>

                            @if(in_array($field, $translatedSpecFields, true))

                                <strong>
                                    @if($field === 'cake_size' && is_array($cakeOrder->cake_sizes) && count($cakeOrder->cake_sizes))
                                        {{
                                            collect($cakeOrder->cake_sizes)
                                                ->map(fn ($value) => $formatSystemValue('cake_size', $value))
                                                ->implode('، ')
                                        }}
                                    @else
                                        {{
                                            $formatSystemValue(
                                                $field,
                                                $cakeOrder->$field
                                            )
                                        }}
                                    @endif
                                </strong>

                            @else

                                {{ $cakeOrder->$field }}

                            @endif

                        </td>

                    </tr>

                @endif

            @endforeach

        </table>

    </div>

</div>


{{-- =========================================================
     صور الطلب
========================================================= --}}

<div
    class="card"
    style="margin-top:1.5rem"
>

    <div class="card-header">

        <span class="card-title">
            صور الطلب
        </span>

    </div>


    <div class="card-body">

        <div
            style="
                display:flex;
                flex-wrap:wrap;
                gap:.75rem;
            "
        >

            @forelse($cakeOrder->attachments as $att)

                <div style="text-align:center">

                    <a
                        href="{{ asset('storage/' . $att->file_path) }}"
                        target="_blank"
                    >

                        <img
                            src="{{ asset('storage/' . $att->file_path) }}"
                            alt="صورة مرفقة للطلب"
                            style="
                                width:140px;
                                height:140px;
                                object-fit:cover;
                                border-radius:12px;
                                border:1px solid rgba(212,175,55,.45);
                            "
                        >

                    </a>


                    <div
                        style="
                            font-size:.7rem;
                            color:var(--text-muted);
                            margin-top:.3rem;
                        "
                    >
                        {{
                            $attachmentTypes[
                                $att->attachment_type
                            ]
                            ?? 'صورة أخرى'
                        }}
                    </div>

                </div>

            @empty

                <div
                    style="
                        width:100%;
                        padding:1.5rem;
                        text-align:center;
                        color:var(--text-muted);
                        border:1px dashed var(--border);
                        border-radius:12px;
                    "
                >
                    لا توجد صور مرفقة حتى الآن.
                </div>

            @endforelse

        </div>


        {{-- رفع صورة جديدة --}}
        <details style="margin-top:1rem">

            <summary
                style="
                    cursor:pointer;
                    color:var(--gold);
                    font-size:.9rem;
                "
            >
                + رفع صورة إضافية
            </summary>


            <form
                action="{{ route('cake-orders.attachment', $cakeOrder) }}"
                method="POST"
                enctype="multipart/form-data"
                style="
                    margin-top:.75rem;
                    display:grid;
                    gap:.75rem;
                    max-width:400px;
                "
            >

                @csrf


                <div class="form-group">

                    <label class="form-label">
                        نوع الصورة
                    </label>

                    <select
                        name="attachment_type"
                        class="form-select"
                    >

                        <option value="reference_image">
                            صورة مرجعية
                        </option>

                        <option value="customer_design">
                            تصميم العميل
                        </option>

                        <option value="final_cake_image">
                            صورة الكيك النهائي
                        </option>

                        <option value="other">
                            صورة أخرى
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        الملف
                    </label>

                    <input
                        type="file"
                        name="file"
                        class="form-input"
                        accept="image/*,application/pdf"
                        required
                    >

                </div>


                <div>

                    <button
                        class="btn btn-outline btn-sm"
                        type="submit"
                    >
                        رفع الصورة
                    </button>

                </div>

            </form>

        </details>

    </div>

</div>


{{-- =========================================================
     سجل الحالات
========================================================= --}}

@if($cakeOrder->statusHistories->count())

    <div
        class="card"
        style="margin-top:1.5rem"
    >

        <div class="card-header">

            <span class="card-title">
                سجل حالات الطلب
            </span>

        </div>


        <div class="table-wrap">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>التاريخ</th>
                        <th>الحالة السابقة</th>
                        <th>الحالة الجديدة</th>
                        <th>تم التحديث بواسطة</th>
                        <th>الملاحظة</th>
                    </tr>

                </thead>


                <tbody>

                    @foreach($cakeOrder->statusHistories as $h)

                        @php
                            $fromValue =
                                $h->from_status instanceof \BackedEnum
                                    ? $h->from_status->value
                                    : (string) $h->from_status;

                            $toValue =
                                $h->to_status instanceof \BackedEnum
                                    ? $h->to_status->value
                                    : (string) $h->to_status;
                        @endphp


                        <tr>

                            <td>
                                {{ $h->created_at?->format('Y-m-d H:i') }}
                            </td>


                            <td>
                                {{
                                    $fromValue
                                        ? ($statusLabels[$fromValue] ?? 'غير محدد')
                                        : '—'
                                }}
                            </td>


                            <td>

                                <strong>
                                    {{ $statusLabels[$toValue] ?? 'غير محدد' }}
                                </strong>

                            </td>


                            <td>
                                {{
                                    $h->changedBy?->employee?->full_name
                                    ?? $h->changedBy?->display_name
                                    ?? $h->changedBy?->name
                                    ?? '—'
                                }}
                            </td>


                            <td>
                                {{ $h->note ?: '—' }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

@endif


{{-- =========================================================
     التعليقات
========================================================= --}}

<div
    class="card"
    style="margin-top:1.5rem"
>

    <div class="card-header">

        <span class="card-title">
            الملاحظات والتعليقات
        </span>

    </div>


    <div class="card-body">

        @forelse($cakeOrder->comments as $comment)

            <div
                style="
                    padding:.75rem;
                    background:var(--off-white);
                    border-radius:var(--radius);
                    margin-bottom:.75rem;
                "
            >

                <div
                    style="
                        font-size:.75rem;
                        color:var(--text-muted);
                        margin-bottom:.25rem;
                    "
                >
                    {{
                        $comment->user?->employee?->full_name
                        ?? $comment->user?->display_name
                        ?? $comment->user?->name
                        ?? 'مستخدم'
                    }}

                    —

                    {{ $comment->created_at?->diffForHumans() }}
                </div>


                <div>
                    {{ $comment->comment }}
                </div>

            </div>

        @empty

            <p style="color:var(--text-muted)">
                لا توجد تعليقات حتى الآن.
            </p>

        @endforelse


        <form
            action="{{ route('cake-orders.comment', $cakeOrder) }}"
            method="POST"
            style="margin-top:1rem"
        >

            @csrf


            <div
                style="
                    display:flex;
                    gap:.75rem;
                "
            >

                <input
                    type="text"
                    name="comment"
                    class="form-input"
                    placeholder="إضافة ملاحظة..."
                    required
                >

                <button
                    class="btn btn-outline btn-sm"
                    type="submit"
                >
                    إرسال
                </button>

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     نافذة تحديث حالة الطلب
========================================================= --}}

@if(count($allowed) > 0)

<div
    id="statusUpdateModal"
    class="status-modal"
    aria-hidden="true"
    onclick="closeStatusModalFromBackdrop(event)"
>
    <div
        class="status-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="statusModalTitle"
    >
        <div class="status-modal-header">
            <div>
                <span class="status-modal-eyebrow">
                    تحديث العملية
                </span>

                <h3 id="statusModalTitle">
                    تحديث حالة الطلب
                </h3>

                <p>
                    طلب رقم
                    <strong>{{ $cakeOrder->order_number }}</strong>
                </p>
            </div>

            <button
                type="button"
                class="status-modal-close"
                onclick="closeStatusModal()"
                aria-label="إغلاق"
            >
                ×
            </button>
        </div>

        <div class="status-current-box">
            <span>
                الحالة الحالية
            </span>

            <strong>
                {{ $statusLabel }}
            </strong>
        </div>

        <form
            action="{{ route('cake-orders.transition', $cakeOrder) }}"
            method="POST"
        >
            @csrf

            <div class="status-modal-body">
                <div class="form-group">
                    <label class="form-label">
                        الحالة الجديدة
                    </label>

                    <select
                        name="to_status"
                        class="form-select"
                        required
                    >
                        @foreach($allowed as $s)
                            @php
                                $transitionValue =
                                    $s instanceof \BackedEnum
                                        ? $s->value
                                        : (string) $s;
                            @endphp

                            <option value="{{ $transitionValue }}">
                                {{ $statusLabels[$transitionValue] ?? 'غير محدد' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        ملاحظة
                    </label>

                    <textarea
                        name="note"
                        class="form-textarea"
                        rows="4"
                        placeholder="أضف ملاحظة عن تحديث الحالة إن وجدت..."
                    ></textarea>
                </div>
            </div>

            <div class="status-modal-footer">
                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeStatusModal()"
                >
                    إلغاء
                </button>

                <button
                    type="submit"
                    class="btn btn-gold"
                >
                    تأكيد تحديث الحالة
                </button>
            </div>
        </form>
    </div>
</div>

@endif


<style>

    /* =========================================================
       نافذة تحديث حالة الطلب
    ========================================================= */

    .status-modal {
        position:fixed;
        inset:0;
        z-index:99999;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:1.25rem;
        background:rgba(15,23,42,.55);
        backdrop-filter:blur(3px);
        opacity:0;
        visibility:hidden;
        pointer-events:none;
        transition:opacity .18s ease, visibility .18s ease;
    }

    .status-modal.is-open {
        opacity:1;
        visibility:visible;
        pointer-events:auto;
    }

    .status-modal-dialog {
        width:100%;
        max-width:520px;
        background:#fff;
        border:1px solid var(--border);
        border-radius:18px;
        box-shadow:0 24px 60px rgba(0,0,0,.22);
        overflow:auto;
        max-height:calc(100dvh - 2rem - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
        overscroll-behavior:contain;
        -webkit-overflow-scrolling:touch;
        transform:translateY(18px) scale(.98);
        transition:transform .18s ease;
    }

    .status-modal.is-open .status-modal-dialog {
        transform:translateY(0) scale(1);
    }

    .status-modal-header {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:1rem;
        padding:1.2rem 1.3rem;
        border-bottom:1px solid var(--border);
    }

    .status-modal-eyebrow {
        display:block;
        margin-bottom:.22rem;
        color:var(--gold);
        font-size:.68rem;
        font-weight:800;
    }

    .status-modal-header h3 {
        margin:0;
        color:var(--text);
        font-size:1.05rem;
        font-weight:900;
    }

    .status-modal-header p {
        margin:.3rem 0 0;
        color:var(--text-muted);
        font-size:.72rem;
    }

    .status-modal-header p strong {
        color:var(--gold);
    }

    .status-modal-close {
        width:34px;
        height:34px;
        flex:0 0 34px;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:0;
        border:1px solid var(--border);
        border-radius:50%;
        background:#fff;
        color:var(--text-muted);
        font-size:1.4rem;
        line-height:1;
        cursor:pointer;
        transition:.15s ease;
    }

    .status-modal-close:hover {
        color:#dc3545;
        border-color:rgba(220,53,69,.25);
        background:rgba(220,53,69,.06);
    }

    .status-current-box {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        margin:1rem 1.3rem 0;
        padding:.8rem .9rem;
        border:1px solid rgba(212,160,23,.22);
        border-radius:11px;
        background:rgba(212,160,23,.07);
    }

    .status-current-box span {
        color:var(--text-muted);
        font-size:.72rem;
    }

    .status-current-box strong {
        color:var(--gold);
        font-size:.8rem;
    }

    .status-modal-body {
        display:grid;
        gap:1rem;
        padding:1.2rem 1.3rem;
    }

    .status-modal-footer {
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:.65rem;
        padding:1rem 1.3rem;
        border-top:1px solid var(--border);
        background:var(--off-white);
    }

    .dashboard-row {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:1.5rem;
    }

    .dashboard-row .data-table td:first-child {
        width:42%;
    }

    .card {
        border-radius:14px;
    }

    .card-header {
        min-height:52px;
    }

    .data-table td {
        line-height:1.65;
    }

    .page-actions-title {
        color:var(--gold);
        font-weight:800;
    }

    .action-btns {
        display:flex;
        flex-wrap:wrap;
        gap:.5rem;
    }

    @media(max-width:800px) {

        .dashboard-row {
            grid-template-columns:1fr;
        }

        .page-actions {
            align-items:flex-start;
            flex-direction:column;
            gap:1rem;
        }

        .data-table {
            min-width:0;
        }

        .data-table td {
            padding:.65rem;
        }

        .data-table td:first-child {
            width:38%;
        }
    }

    @media(max-width:600px) {
        .status-modal {
            padding:max(.5rem, env(safe-area-inset-top, 0px))
                max(.5rem, env(safe-area-inset-right, 0px))
                max(.5rem, env(safe-area-inset-bottom, 0px))
                max(.5rem, env(safe-area-inset-left, 0px));
            align-items:flex-end;
        }

        .status-modal-dialog {
            max-width:none;
            width:100%;
            max-height:calc(100dvh - 1rem - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
            border-radius:18px 18px 12px 12px;
        }

        .status-modal-close {
            width:44px;
            height:44px;
            flex-basis:44px;
        }

        .status-modal select,
        .status-modal textarea {
            min-height:44px;
            font-size:16px !important;
        }

        .status-modal-footer {
            position:sticky;
            bottom:0;
        }

        .status-modal-footer {
            flex-direction:column-reverse;
        }

        .status-modal-footer .btn {
            width:100%;
        }
    }

</style>


<script>
    let statusModalTrigger = null;

    function openStatusModal() {
        const modal = document.getElementById('statusUpdateModal');

        if (!modal) {
            return;
        }

        statusModalTrigger = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const select = modal.querySelector('select[name="to_status"]');

        if (select) {
            setTimeout(() => select.focus(), 80);
        }
    }

    function closeStatusModal() {
        const modal = document.getElementById('statusUpdateModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (statusModalTrigger?.isConnected) statusModalTrigger.focus({preventScroll:true});
        statusModalTrigger = null;
    }

    function closeStatusModalFromBackdrop(event) {
        if (event.target.id === 'statusUpdateModal') {
            closeStatusModal();
        }
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeStatusModal();
        }
    });
</script>

@endsection
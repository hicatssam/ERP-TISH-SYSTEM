@extends('layouts.app')
@section('title', 'طلب معرض: ' . $showroomCakeRequest->request_number)

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | مسميات عربية لقيم الأصناف
    |--------------------------------------------------------------------------
    */

    $cakeTypes = [
        'chocolate' => 'شوكولاتة',
        'vanilla' => 'فانيلا',
        'red_velvet' => 'ريد فيلفيت',
        'caramel' => 'كراميل',
        'fruit' => 'فاكهة',
        'other' => 'أخرى',
    ];

    $cakeSizes = [
        'standard' => 'ستاندر',
        'small' => 'صغير',
        'medium' => 'متوسط',
        'large' => 'كبير',
        'x_large' => 'كبير جداً',
        'extra_large' => 'كبير جداً',
        'mini' => 'ميني',
    ];

    $flavors = [
        'chocolate' => 'شوكولاتة',
        'vanilla' => 'فانيلا',
        'strawberry' => 'فراولة',
        'red_velvet' => 'ريد فيلفيت',
        'caramel' => 'كراميل',
        'coffee' => 'قهوة',
        'oreo' => 'أوريو',
        'lotus' => 'لوتس',
        'lemon' => 'ليمون',
        'orange' => 'برتقال',
        'fruit' => 'فواكه',
        'mixed' => 'مشكل',
        'other' => 'أخرى',
    ];

    $shapes = [
        'round' => 'دائري',
        'slab' => 'بلاطة',
        'wedding_tiers' => 'طوابق أفراح',
        'standard_tiers' => 'طوابق ستاندر',
        'square' => 'مربع',
        'rectangle' => 'مستطيل',
        'heart' => 'قلب',
        'oval' => 'بيضاوي',
        'other' => 'أخرى',
    ];
@endphp


{{-- =========================================================
     رأس الصفحة
========================================================= --}}

<div class="page-actions">

    <div class="page-actions-title">
        {{ $showroomCakeRequest->request_number }}
    </div>

    <div class="action-btns">

        @if($canEditRequest)
            <a
                href="{{ route('showroom-cake-requests.edit', $showroomCakeRequest) }}"
                class="btn btn-outline btn-sm"
            >
                تعديل الطلب
            </a>
        @endif

        @if($canCancelRequest)
            <a href="#cancel-branch-request" class="btn btn-danger btn-sm">
                إلغاء الطلب
            </a>
        @endif

        @if(collect($allowedTransitions)->contains(\App\Enums\ShowroomCakeRequestStatus::Ready))
            <form method="POST" action="{{ route('showroom-cake-requests.status', $showroomCakeRequest) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="ready">
                <button type="submit" class="btn btn-gold btn-sm"
                    data-confirm="سيتم اعتماد تنفيذ طلب كيك الفرع وجاهزيته للتحميل." data-confirm-title="تم تنفيذ طلب كيك الفرع" data-confirm-type="success">
                    تم التنفيذ
                </button>
            </form>
        @endif

        @if(count($allowedTransitions) > 0)
            <button
                type="button"
                class="btn btn-gold btn-sm"
                onclick="openShowroomCakeStatusModal()"
            >
                خيارات أخرى
            </button>
        @endif

        <a
            href="{{ route('showroom-cake-requests.index') }}"
            class="btn btn-ghost btn-sm"
        >
            رجوع
        </a>

    </div>

</div>


@if($canCancelRequest)
    <details id="cancel-branch-request" class="card" style="margin:1rem 0">
        <summary style="padding:1rem;cursor:pointer;font-weight:800;color:#b91c1c">
            إلغاء طلب كيك الفرع — يجب توضيح السبب
        </summary>
        <div class="card-body">
            <p style="margin-bottom:.75rem;color:var(--text-muted)">
                سيصل إشعار بالإلغاء للمصنع والفرع. لا يمكن إلغاء طلب تم تجهيزه للشحن أو إكماله.
            </p>
            <form action="{{ route('showroom-cake-requests.cancel', $showroomCakeRequest) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="form-group">
                    <label class="form-label" for="branchCakeCancellationReason">سبب إلغاء الطلب *</label>
                    <textarea
                        id="branchCakeCancellationReason"
                        name="cancellation_reason"
                        class="form-textarea @error('cancellation_reason') is-invalid @enderror"
                        rows="3"
                        minlength="5"
                        maxlength="2000"
                        required
                        placeholder="اكتب سبب الإلغاء لتوثيقه وإبلاغ المصنع..."
                    >{{ old('cancellation_reason') }}</textarea>
                    @error('cancellation_reason')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-danger btn-sm" type="submit"
                        data-confirm="سيتم إلغاء الطلب مع حفظ سبب الإلغاء وسجل العملية." data-confirm-title="تأكيد إلغاء طلب الفرع" data-confirm-type="warning">
                    تأكيد إلغاء الطلب
                </button>
            </form>
        </div>
    </details>
@endif

{{-- =========================================================
     مسار حالة طلب كيك الفرع - يظهر مرة واحدة للطلب كاملًا
========================================================= --}}

<x-workflow-toolbar
    type="showroom_cake"
    :record="$showroomCakeRequest"
/>

<x-branch-delivery-card
    :source="$showroomCakeRequest"
    :shipment="$showroomCakeRequest->branchShipment?->loadMissing(['driver.employee','receivingInvoice'])"
    source-type="showroom-cake"
/>


{{-- =========================================================
     بيانات الطلب
========================================================= --}}

<div class="dashboard-row">

    <div class="card request-details-card">

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
                            {{ $showroomCakeRequest->request_number }}
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td style="color:var(--text-muted)">
                        المعرض / الفرع
                    </td>

                    <td>
                        {{ $showroomCakeRequest->requestingLocation?->name ?? '—' }}
                    </td>
                </tr>

                <tr>
                    <td style="color:var(--text-muted)">
                        المصنع
                    </td>

                    <td>
                        {{ $showroomCakeRequest->factoryLocation?->name ?? '—' }}
                    </td>
                </tr>

                <tr>
                    <td style="color:var(--text-muted)">
                        تاريخ الحاجة
                    </td>

                    <td>
                        {{ \App\Support\ArabicDate::date($showroomCakeRequest->needed_by) }}
                    </td>
                </tr>

                <tr>
                    <td style="color:var(--text-muted)">
                        الحالة
                    </td>

                    <td>
                        <span class="badge {{ $showroomCakeRequest->status->badgeClass() }}">
                            {{ \App\Support\BranchOrderMilestones::label($showroomCakeRequest) }}
                        </span>
                    </td>
                </tr>

                @if($showroomCakeRequest->status->workflowValue() === 'cancelled')
                    <tr>
                        <td style="color:var(--text-muted)">سبب الإلغاء</td>
                        <td style="white-space:pre-wrap">{{ $showroomCakeRequest->cancellation_reason ?: 'لم يسجل سبب للطلب القديم' }}</td>
                    </tr>
                    @if($showroomCakeRequest->cancelled_at)
                        <tr>
                            <td style="color:var(--text-muted)">تاريخ الإلغاء</td>
                            <td>{{ \App\Support\ArabicDate::compactDateTime($showroomCakeRequest->cancelled_at) }}</td>
                        </tr>
                    @endif
                @endif

                <tr>
                    <td style="color:var(--text-muted)">
                        أُنشئ بواسطة
                    </td>

                    <td>
                        {{
                            $showroomCakeRequest->creator?->employee?->full_name
                            ?? $showroomCakeRequest->creator?->name
                            ?? '—'
                        }}
                    </td>
                </tr>

                @if($showroomCakeRequest->submitted_at)
                    <tr>
                        <td style="color:var(--text-muted)">
                            تاريخ الإرسال
                        </td>

                        <td>
                            {{ \App\Support\ArabicDate::compactDateTime($showroomCakeRequest->submitted_at) }}
                        </td>
                    </tr>
                @endif

                @if($showroomCakeRequest->fulfilled_at)
                    <tr>
                        <td style="color:var(--text-muted)">
                            تاريخ التنفيذ
                        </td>

                        <td>
                            {{ \App\Support\ArabicDate::compactDateTime($showroomCakeRequest->fulfilled_at) }}
                        </td>
                    </tr>
                @endif

                @if($showroomCakeRequest->notes)
                    <tr>
                        <td style="color:var(--text-muted)">
                            ملاحظات
                        </td>

                        <td>
                            {{ $showroomCakeRequest->notes }}
                        </td>
                    </tr>
                @endif

                @if($showroomCakeRequest->factory_notes)
                    <tr>
                        <td style="color:var(--text-muted)">
                            ملاحظات المصنع
                        </td>

                        <td style="color:var(--gold)">
                            {{ $showroomCakeRequest->factory_notes }}
                        </td>
                    </tr>
                @endif

            </table>

        </div>

    </div>

</div>


{{-- =========================================================
     أصناف الكيك المطلوبة
========================================================= --}}

<div class="card" style="margin-top:1.5rem">

    <div class="card-header">
        <span class="card-title">
            أصناف الكيك المطلوبة
        </span>
    </div>

    <div class="table-wrap">

        <table class="data-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع الكيك</th>
                    <th>الحجم</th>
                    <th>النكهة</th>
                    <th>الشكل</th>
                    <th>الكمية</th>
                    <th>محجوز للعملاء</th>
                    <th>متاح للعرض</th>
                    <th>ملاحظة</th>
                </tr>
            </thead>

            <tbody>

                @forelse($showroomCakeRequest->items as $i => $item)

                    <tr>

                        <td>
                            {{ $i + 1 }}
                        </td>

                        <td>
                            {{
                                $cakeTypes[$item->cake_type]
                                ?? (
                                    preg_match('/[\x{0600}-\x{06FF}]/u', (string) $item->cake_type)
                                        ? $item->cake_type
                                        : 'غير محدد'
                                )
                            }}
                        </td>

                        <td>
                            {{
                                $cakeSizes[$item->cake_size]
                                ?? (
                                    preg_match('/[\x{0600}-\x{06FF}]/u', (string) $item->cake_size)
                                        ? $item->cake_size
                                        : 'غير محدد'
                                )
                            }}
                        </td>

                        <td>
                            {{
                                $flavors[$item->flavor]
                                ?? (
                                    preg_match('/[\x{0600}-\x{06FF}]/u', (string) $item->flavor)
                                        ? $item->flavor
                                        : 'غير محدد'
                                )
                            }}
                        </td>

                        <td>
                            {{
                                $shapes[$item->shape]
                                ?? (
                                    preg_match('/[\x{0600}-\x{06FF}]/u', (string) $item->shape)
                                        ? $item->shape
                                        : 'غير محدد'
                                )
                            }}
                        </td>

                        <td>
                            <strong>
                                {{ $item->quantity }}
                            </strong>
                        </td>

                        <td>
                            <div class="reservation-value">
                                @if((int) $item->reserved_quantity > 0)
                                    <strong style="color:#c2410c">
                                        {{ $item->reserved_quantity }}
                                    </strong>
                                @else
                                    <span style="color:var(--text-muted)">0</span>
                                @endif
                            </div>

                            @if($item->reservation_notes)
                                <div class="reservation-note-text">
                                    {{ $item->reservation_notes }}
                                </div>
                            @endif

                            @if(
                                $canManageReservations
                                && ! $showroomCakeRequest->status->isTerminal()
                            )
                                <details class="reservation-editor">
                                    <summary>
                                        تعديل الحجز
                                    </summary>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'showroom-cake-requests.items.reservation',
                                            [
                                                $showroomCakeRequest,
                                                $item,
                                            ]
                                        ) }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <label>
                                            الكمية المحجوزة
                                            <input
                                                type="number"
                                                name="reserved_quantity"
                                                min="0"
                                                max="{{ $item->quantity }}"
                                                value="{{ $item->reserved_quantity ?? 0 }}"
                                                class="form-input"
                                                required
                                            >
                                        </label>

                                        <label>
                                            تفاصيل الحجوزات
                                            <input
                                                type="text"
                                                name="reservation_notes"
                                                maxlength="1000"
                                                value="{{ $item->reservation_notes }}"
                                                class="form-input"
                                                placeholder="مثال: 3 لمحمد، 2 لسارة"
                                            >
                                        </label>

                                        <button
                                            type="submit"
                                            class="btn btn-gold btn-sm"
                                        >
                                            حفظ الحجز
                                        </button>
                                    </form>
                                </details>
                            @endif
                        </td>

                        <td>
                            <strong style="color:#15803d">
                                {{ $item->availableQuantity() }}
                            </strong>
                        </td>

                        <td>
                            {{ $item->notes ?? '—' }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="9">
                            <div class="empty-state-sm">
                                لا توجد أصناف.
                            </div>
                        </td>
                    </tr>

                @endforelse

            </tbody>

            <tfoot>
                <tr>
                    <td
                        colspan="5"
                        style="
                            text-align:left;
                            font-weight:600;
                            color:var(--text-muted);
                        "
                    >
                        الإجمالي:
                    </td>

                    <td>
                        <strong>
                            {{ $showroomCakeRequest->items->sum('quantity') }}
                        </strong>
                    </td>

                    <td>
                        <strong style="color:#c2410c">
                            {{ $showroomCakeRequest->items->sum('reserved_quantity') }}
                        </strong>
                    </td>

                    <td>
                        <strong style="color:#15803d">
                            {{
                                $showroomCakeRequest->items->sum(
                                    fn ($item) => $item->availableQuantity()
                                )
                            }}
                        </strong>
                    </td>

                    <td></td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>


{{-- =========================================================
     Popup تحديث حالة الطلب
========================================================= --}}

@if(count($allowedTransitions) > 0)

    <div
        id="showroomCakeStatusModal"
        class="status-modal"
        aria-hidden="true"
        onclick="closeShowroomCakeStatusModalFromBackdrop(event)"
    >

        <div
            class="status-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="showroomCakeStatusModalTitle"
        >

            <div class="status-modal-header">

                <div>

                    <span class="status-modal-eyebrow">
                        تحديث العملية
                    </span>

                    <h3 id="showroomCakeStatusModalTitle">
                        تحديث حالة طلب المعرض
                    </h3>

                    <p>
                        طلب رقم
                        <strong>
                            {{ $showroomCakeRequest->request_number }}
                        </strong>
                    </p>

                </div>


                <button
                    type="button"
                    class="status-modal-close"
                    onclick="closeShowroomCakeStatusModal()"
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
                    {{ \App\Support\BranchOrderMilestones::label($showroomCakeRequest) }}
                </strong>

            </div>


            <form
                action="{{ route('showroom-cake-requests.status', $showroomCakeRequest) }}"
                method="POST"
            >

                @csrf
                @method('PATCH')


                <div class="status-modal-body">

                    <div class="form-group">

                        <label class="form-label">
                            الحالة الجديدة
                        </label>

                        <select
                            name="status"
                            id="showroomCakeStatusChoice"
                            class="form-select"
                            required
                        >

                            @foreach($allowedTransitions as $nextStatus)

                                <option value="{{ $nextStatus->value }}">
                                    {{ $nextStatus->label() }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div id="showroomCakeStatusCancellationReason" class="form-group" hidden>
                        <label class="form-label" for="showroomCakeStatusCancelText">سبب الإلغاء *</label>
                        <textarea
                            name="cancellation_reason"
                            id="showroomCakeStatusCancelText"
                            class="form-textarea"
                            rows="3"
                            minlength="5"
                            maxlength="2000"
                            placeholder="يجب تسجيل سبب إلغاء الطلب..."
                        >{{ old('cancellation_reason') }}</textarea>
                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            ملاحظات المصنع
                        </label>

                        <textarea
                            name="factory_notes"
                            class="form-textarea"
                            rows="4"
                            placeholder="أضف ملاحظة للمعرض إن وجدت..."
                        >{{ old('factory_notes', $showroomCakeRequest->factory_notes) }}</textarea>

                    </div>

                </div>


                <div class="status-modal-footer">

                    <button
                        type="button"
                        class="btn btn-ghost"
                        onclick="closeShowroomCakeStatusModal()"
                    >
                        إلغاء
                    </button>


                    <button
                        class="btn btn-gold"
                        type="submit"
                    >
                        تأكيد تحديث الحالة
                    </button>

                </div>

            </form>

        </div>

    </div>

@endif


<style>

    .dashboard-row {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:1.5rem;
    }

    .request-details-card {
        grid-column:1/-1;
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


    .reservation-note-text {
        margin-top:.2rem;
        color:var(--text-muted);
        font-size:.68rem;
        line-height:1.55;
    }

    .reservation-editor {
        margin-top:.45rem;
    }

    .reservation-editor summary {
        color:var(--gold);
        font-size:.66rem;
        font-weight:800;
        cursor:pointer;
        user-select:none;
    }

    .reservation-editor form {
        display:grid;
        gap:.45rem;
        min-width:190px;
        margin-top:.45rem;
        padding:.55rem;
        border:1px solid var(--border);
        border-radius:10px;
        background:var(--off-white);
    }

    .reservation-editor label {
        display:grid;
        gap:.22rem;
        color:var(--text-muted);
        font-size:.62rem;
        font-weight:700;
    }

    .reservation-editor .form-input {
        min-height:34px;
        padding:.4rem .5rem;
        font-size:.68rem;
    }


    /* =========================================================
       Popup تحديث الحالة
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

        transition:
            opacity .18s ease,
            visibility .18s ease;
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
        max-height:calc(100dvh - 2rem - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px));
        overscroll-behavior:contain;

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
            padding:max(.5rem,env(safe-area-inset-top,0px)) max(.5rem,env(safe-area-inset-right,0px)) max(.5rem,env(safe-area-inset-bottom,0px)) max(.5rem,env(safe-area-inset-left,0px));
            align-items:flex-end;
        }
        .status-modal-dialog {
            max-width:none;
            max-height:calc(100dvh - 1rem - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px));
            border-radius:18px 18px 12px 12px;
        }
        .status-modal-close {min-width:44px;min-height:44px;flex-basis:44px;}
        .status-modal :is(input:not([type=checkbox]):not([type=radio]),select,textarea) {font-size:16px!important;min-height:44px;}
        .status-modal-footer {position:sticky;bottom:0;}

        .status-modal-footer {
            flex-direction:column-reverse;
        }

        .status-modal-footer .btn {
            width:100%;
        }

    }

</style>


@if(count($allowedTransitions) > 0)

<script>

    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('showroomCakeStatusChoice');
        const reasonGroup = document.getElementById('showroomCakeStatusCancellationReason');
        const reason = document.getElementById('showroomCakeStatusCancelText');

        function updateCancelReasonRequirement() {
            const isCancellation = select?.value === 'cancelled';
            if (reasonGroup) reasonGroup.hidden = !isCancellation;
            if (reason) reason.required = isCancellation;
        }

        select?.addEventListener('change', updateCancelReasonRequirement);
        updateCancelReasonRequirement();
    });

    function openShowroomCakeStatusModal() {

        const modal =
            document.getElementById(
                'showroomCakeStatusModal'
            );

        if (!modal) {
            return;
        }

        modal.classList.add('is-open');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow =
            'hidden';
    }


    function closeShowroomCakeStatusModal() {

        const modal =
            document.getElementById(
                'showroomCakeStatusModal'
            );

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow =
            '';
    }


    function closeShowroomCakeStatusModalFromBackdrop(event) {

        if (
            event.target.id ===
            'showroomCakeStatusModal'
        ) {
            closeShowroomCakeStatusModal();
        }

    }


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {
                closeShowroomCakeStatusModal();
            }

        }
    );

</script>

@endif

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.location.hash === '#cancel-branch-request') {
            const section = document.getElementById('cancel-branch-request');
            if (section) section.open = true;
        }
    });
</script>
@include('sales.partials.daily-raw-materials-summary', ['stockRequest' => $showroomCakeRequest->rawMaterialStockRequest])
@endsection

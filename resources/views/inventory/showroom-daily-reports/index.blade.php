@extends('layouts.app')

@section('title', 'تقرير المعرض اليومي')
@section('page-title', 'تقرير المعرض اليومي')

@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة لدى الفرع',
        'submitted' => 'بانتظار تدقيق المحاسب العام',
        'approved' => 'معتمد',
        'closed' => 'معتمد',
    ];

    $statusClasses = [
        'draft' => 'badge-warning',
        'submitted' => 'badge-warning',
        'approved' => 'badge-active',
        'closed' => 'badge-active',
    ];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">تقرير المعرض اليومي</h1>
        <p class="page-subheading">
            السابق والوارد والمبيعات تُقرأ تلقائيًا من النظام ولا يمكن تعديلها من التقرير.
            محاسب الفرع يدخل فقط الجرد الفعلي وبياناته اليومية، ثم يرسل التقرير للمحاسب العام للتدقيق.
        </p>
    </div>

    @if($report)
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a
                href="{{ route('showroom-daily-reports.print', $report) }}"
                target="_blank"
                class="btn btn-outline"
            >
                طباعة
            </a>

            <a
                href="{{ route('showroom-daily-reports.pdf', $report) }}"
                target="_blank"
                class="btn btn-outline"
            >
                PDF
            </a>

            <a
                href="{{ route('showroom-daily-reports.excel', $report) }}"
                class="btn btn-gold"
            >
                تنزيل Excel
            </a>
        </div>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>راجع البيانات:</strong>
        <ul style="margin:.45rem 0 0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($canReview)
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">
            <div>
                <div class="card-title">تقارير الفروع بانتظار تدقيق المحاسب العام</div>
                <small class="text-muted">
                    هذه التقارير محفوظة في النظام وتم إرسالها من الفروع للمراجعة.
                </small>
            </div>
            <span class="badge badge-warning">
                {{ $pendingReviewReports->count() }} تقرير
            </span>
        </div>

        <div class="card-body">
            @forelse($pendingReviewReports as $pendingReport)
                <a
                    href="{{ route('showroom-daily-reports.index', [
                        'location_id' => $pendingReport->location_id,
                        'date' => $pendingReport->business_date->format('Y-m-d'),
                    ]) }}"
                    class="showroom-review-queue-item"
                >
                    <div>
                        <strong>{{ $pendingReport->location?->name ?? '—' }}</strong>
                        <small>
                            {{ $pendingReport->business_date->format('Y-m-d') }}
                            @if($pendingReport->submittedBy)
                                · أرسله {{ $pendingReport->submittedBy->display_name }}
                            @endif
                        </small>
                    </div>

                    <span class="btn btn-outline btn-sm">فتح التقرير</span>
                </a>
            @empty
                <div class="empty-state-sm">
                    لا توجد تقارير مرسلة بانتظار المراجعة حاليًا.
                </div>
            @endforelse
        </div>
    </div>
@endif

<form
    method="GET"
    action="{{ route('showroom-daily-reports.index') }}"
    class="card showroom-filter-card"
>
    <div class="card-body showroom-filter-grid">
        <div class="form-group">
            <label class="form-label">الفرع</label>
            <select name="location_id" class="form-select">
                @foreach($locations as $location)
                    <option
                        value="{{ $location->id }}"
                        @selected((int) $locationId === (int) $location->id)
                    >
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">اليوم والتاريخ</label>
            <input
                type="date"
                name="date"
                class="form-input"
                value="{{ $date }}"
                max="{{ now()->toDateString() }}"
            >
        </div>

        <button type="submit" class="btn btn-gold">
            عرض التقرير
        </button>
    </div>
</form>

@if(!$report)
    <div class="card">
        <div class="card-body showroom-empty">
            <h3>لا يوجد تقرير لهذا اليوم</h3>

            @if($prior)
                <p>
                    آخر تقرير:
                    <strong>{{ $prior->business_date->format('Y-m-d') }}</strong>
                    —
                    <strong>{{ $statusLabels[$prior->status] ?? $prior->status }}</strong>
                </p>
            @else
                <p>
                    هذا أول تقرير للفرع. رصيد البداية سيُقرأ من سجل المخزون في النظام.
                </p>
            @endif

            @if($canManage)
                <form method="POST" action="{{ route('showroom-daily-reports.create') }}">
                    @csrf
                    <input type="hidden" name="location_id" value="{{ $locationId }}">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <button type="submit" class="btn btn-gold">
                        إنشاء تقرير هذا اليوم
                    </button>
                </form>
            @endif
        </div>
    </div>
@else
    @php
        $isDraft = $report->status === 'draft';
        $isSubmitted = $report->status === 'submitted';
        $isApproved = in_array($report->status, ['approved', 'closed'], true);

        $totals = [
            'opening' => $report->items->sum(fn($i) => (float) $i->opening_kg),
            'trays' => $report->items->sum(fn($i) => (float) $i->received_trays),
            'received' => $report->items->sum(fn($i) => (float) $i->received_kg),
            'sales' => $report->items->sum(fn($i) => (float) $i->sales_kg),
            'takeaway' => $report->items->sum(fn($i) => (float) $i->takeaway_sales_kg),
            'damaged' => $report->items->sum(fn($i) => (float) $i->damaged_kg),
            'other' => $report->items->sum(fn($i) => (float) $i->other_net_kg),
            'actual' => $report->items->sum(fn($i) => (float) ($i->actual_closing_kg ?? 0)),
            'variance' => $report->items->sum(fn($i) => (float) ($i->variance_kg ?? 0)),
        ];
    @endphp

    <div class="showroom-status-bar">
        <div>
            <strong>{{ $report->location->name }}</strong>
            <span>{{ $report->business_date->format('Y-m-d') }}</span>
        </div>

        <span class="badge {{ $statusClasses[$report->status] ?? 'badge-secondary' }}">
            {{ $statusLabels[$report->status] ?? $report->status }}
        </span>

        @if($report->submittedBy)
            <small>
                أرسله:
                {{ $report->submittedBy->display_name }}
                @if($report->submitted_at)
                    · {{ $report->submitted_at->format('Y-m-d H:i') }}
                @endif
            </small>
        @endif

        @if($report->reviewedBy)
            <small>
                راجعه:
                {{ $report->reviewedBy->display_name }}
                @if($report->reviewed_at)
                    · {{ $report->reviewed_at->format('Y-m-d H:i') }}
                @endif
            </small>
        @endif
    </div>

    @if($canReview)
        <div class="alert alert-info showroom-persisted-report" style="margin-bottom:1rem">
            <strong>هذا التقرير محفوظ داخل النظام.</strong>
            <div style="margin-top:.35rem;display:flex;gap:.8rem;flex-wrap:wrap">
                <span>رقم التقرير: #{{ $report->id }}</span>
                <span>الفرع: {{ $report->location->name }}</span>
                <span>التاريخ: {{ $report->business_date->format('Y-m-d') }}</span>
                @if($report->submittedBy)
                    <span>أرسله: {{ $report->submittedBy->display_name }}</span>
                @endif
                @if($report->submitted_at)
                    <span>وقت الإرسال: {{ $report->submitted_at->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        </div>
    @endif

    @if($report->review_notes)
        <div class="alert alert-info" style="margin-bottom:1rem">
            <strong>ملاحظة المحاسب العام:</strong>
            {{ $report->review_notes }}
        </div>
    @endif

    <div class="stats-grid showroom-summary-grid">
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['opening'], 3) }}</div><div class="stat-label">رصيد بداية اليوم / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['received'], 3) }}</div><div class="stat-label">الاستلام من الفواتير / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['sales'], 3) }}</div><div class="stat-label">المبيعات من النظام / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['actual'], 3) }}</div><div class="stat-label">جرد آخر النهار / كجم</div></div></div>
    </div>

    <div class="card receiving-card">
        <div class="card-header">
            <div>
                <div class="card-title">فواتير الاستلام المرتبطة بهذا اليوم</div>
                <small class="text-muted">
                    هذه الفواتير هي الاستلامات التي أكدها الفرع من مسؤول التحميل. لا يعاد إدخالها أو تعديلها من التقرير.
                </small>
            </div>
        </div>

        <div class="card-body">
            @forelse($receivingInvoices as $invoice)
                <div class="receiving-invoice-row">
                    <div>
                        <strong dir="ltr">{{ $invoice->invoice_number }}</strong>
                        <small>
                            من {{ $invoice->sendingLocation?->name ?? '—' }}
                            · استلمها {{ $invoice->receivedBy?->display_name ?? '—' }}
                            · {{ $invoice->issued_at?->format('H:i') ?? '—' }}
                        </small>
                    </div>

                    <div class="receiving-invoice-qty">
                        {{ number_format((float) $invoice->total_items_received, 3) }}
                    </div>

                    <a
                        href="{{ route('branch-receiving-invoices.show', $invoice) }}"
                        class="btn btn-outline btn-xs"
                        target="_blank"
                    >
                        فاتورة الاستلام
                    </a>
                </div>
            @empty
                <div class="empty-state-sm">
                    لا توجد فاتورة استلام مسجلة لهذا الفرع في هذا اليوم.
                </div>
            @endforelse
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('showroom-daily-reports.update', $report) }}"
    >
        @csrf
        @method('PUT')

        <div class="card showroom-report-card">
            <div class="card-header">
                <div>
                    <div class="card-title">كشف تفصيلي للحلويات</div>
                    <small class="text-muted">
                        البيانات القادمة من النظام للعرض فقط ولا يمكن تعديلها. محاسب الفرع يدخل المتبقي الفعلي آخر اليوم والملاحظة فقط.
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table showroom-report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>بيان المنتج</th>
                            <th>سابق / كجم</th>
                            <th>وارد سدر</th>
                            <th>وارد / كجم</th>
                            <th>مبيعات / كجم</th>
                            <th>Take Away / كجم</th>
                            <th>تالف / كجم</th>
                            <th>حركات أخرى صافي</th>
                            <th>المتبقي الفعلي آخر النهار</th>
                            <th>الفرق</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($report->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                <td class="showroom-product-name">
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->product?->sku)
                                        <small dir="ltr">{{ $item->product->sku }}</small>
                                    @endif
                                </td>

                                <td class="auto-cell">{{ number_format((float) $item->opening_kg, 3) }}</td>

                                <td class="auto-cell">
                                    <strong>{{ number_format((float) $item->received_trays, 2) }}</strong>
                                    <small class="system-source-note">من فاتورة الاستلام</small>
                                </td>

                                <td class="auto-cell">{{ number_format((float) $item->received_kg, 3) }}</td>
                                <td class="auto-cell">{{ number_format((float) $item->sales_kg, 3) }}</td>
                                <td class="auto-cell takeaway-cell">
                                    <strong>{{ number_format((float) $item->takeaway_sales_kg, 3) }}</strong>
                                    <small class="system-source-note">من طلبات Take Away</small>
                                </td>
                                <td class="auto-cell">{{ number_format((float) $item->damaged_kg, 3) }}</td>

                                <td class="auto-cell {{ abs((float) $item->other_net_kg) > 0.0005 ? 'has-variance' : '' }}">
                                    {{ number_format((float) $item->other_net_kg, 3) }}
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        name="items[{{ $item->id }}][actual_closing_kg]"
                                        value="{{ old("items.{$item->id}.actual_closing_kg", $item->actual_closing_kg) }}"
                                        class="showroom-num"
                                        placeholder="الجرد الفعلي"
                                        @disabled(!$isDraft || !$canManage)
                                    >
                                </td>

                                <td>
                                    <strong class="{{ abs((float) ($item->variance_kg ?? 0)) > 0.0005 ? 'has-variance' : '' }}">
                                        {{ $item->variance_kg === null ? '—' : number_format((float) $item->variance_kg, 3) }}
                                    </strong>
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        maxlength="500"
                                        name="items[{{ $item->id }}][note]"
                                        value="{{ old("items.{$item->id}.note", $item->note) }}"
                                        class="showroom-note"
                                        @disabled(!$isDraft || !$canManage)
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state-sm">
                                        لا توجد منتجات مفعلة لهذا الفرع.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="2">الإجمالي</th>
                            <th>{{ number_format($totals['opening'], 3) }}</th>
                            <th>{{ number_format($totals['trays'], 2) }}</th>
                            <th>{{ number_format($totals['received'], 3) }}</th>
                            <th>{{ number_format($totals['sales'], 3) }}</th>
                            <th>{{ number_format($totals['takeaway'], 3) }}</th>
                            <th>{{ number_format($totals['damaged'], 3) }}</th>
                            <th>{{ number_format($totals['other'], 3) }}</th>
                            <th>{{ number_format($totals['actual'], 3) }}</th>
                            <th>{{ number_format($totals['variance'], 3) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div
                class="showroom-bottom-scroll"
                id="showroomBottomScroll"
                aria-label="شريط تحريك الجدول"
            >
                <div class="showroom-bottom-scroll-inner"></div>
            </div>

            <div class="card-body showroom-accounting-section">
                <div class="showroom-accounting-head">
                    <div>
                        <strong>بيانات محاسب الفرع</strong>
                        <small>
                            بيانات النظام لا تُعدل هنا. هذه الحقول فقط إدخال محاسب الفرع، والإجماليات تُحسب تلقائيًا.
                        </small>
                    </div>
                    <span class="badge badge-blue">
                        {{ $report->returned_for_correction_at ? 'مفتوح للتصحيح' : 'إدخال يدوي' }}
                    </span>
                </div>

                @if($report->returned_for_correction_at && $isDraft)
                    <div class="alert alert-warning" style="margin-bottom:1rem">
                        <strong>أعاد المحاسب العام التقرير للتصحيح.</strong>
                        @if($report->review_notes)
                            <div style="margin-top:.35rem">
                                السبب: {{ $report->review_notes }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="showroom-accounting-block">
                    <div class="showroom-accounting-block-title">
                        <span>الحركة المالية اليومية</span>
                        <small>الإجمالي = المديونيات + الصواني/البكج + التحليات − الخصم − الدفع المسبق</small>
                    </div>

                    <div class="showroom-accounting-grid">
                        <div class="form-group">
                            <label class="form-label">المديونيات</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="debts_amount"
                                class="form-input js-primary-accounting"
                                value="{{ old('debts_amount', $report->debts_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">باقي صواني / بكج مغلف</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="trays_packages_balance"
                                class="form-input js-primary-accounting"
                                value="{{ old('trays_packages_balance', $report->trays_packages_balance) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">تحليات الزبائن</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="customer_treats_amount"
                                class="form-input js-primary-accounting"
                                value="{{ old('customer_treats_amount', $report->customer_treats_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">خصم الزبائن</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="customer_discount_amount"
                                class="form-input js-primary-accounting js-subtract"
                                value="{{ old('customer_discount_amount', $report->customer_discount_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">الدفع المسبق (حجوزات سابقة)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="previous_prepaid_amount"
                                class="form-input js-primary-accounting js-subtract"
                                value="{{ old('previous_prepaid_amount', $report->previous_prepaid_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>
                    </div>

                    <div class="showroom-accounting-total">
                        <span>الإجمالي</span>
                        <strong id="primaryAccountingTotal">
                            {{ number_format((float) $report->accounting_total_primary, 2) }}
                        </strong>
                    </div>
                </div>

                <div class="showroom-accounting-divider"></div>

                <div class="showroom-accounting-block">
                    <div class="showroom-accounting-block-title">
                        <span>الحجوزات والسداد</span>
                        <small>الإجمالي = الدفع الآجل + الحجوزات الخاصة − سداد المديونيات</small>
                    </div>

                    <div class="showroom-accounting-grid secondary">
                        <div class="form-group">
                            <label class="form-label">الدفع الآجل / الحجوزات الآجلة</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="deferred_payment_amount"
                                class="form-input js-secondary-accounting"
                                value="{{ old('deferred_payment_amount', $report->deferred_payment_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">الحجوزات الخاصة / حلويات + كيك</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="special_reservations_amount"
                                class="form-input js-secondary-accounting"
                                value="{{ old('special_reservations_amount', $report->special_reservations_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">سداد المديونيات</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="debt_repayment_amount"
                                class="form-input js-secondary-accounting js-subtract"
                                value="{{ old('debt_repayment_amount', $report->debt_repayment_amount) }}"
                                @disabled(!$isDraft || !$canManage)
                            >
                        </div>
                    </div>

                    <div class="showroom-accounting-total secondary-total">
                        <span>الإجمالي</span>
                        <strong id="secondaryAccountingTotal">
                            {{ number_format((float) $report->accounting_total_secondary, 2) }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">ملاحظات الفرع</label>
                    <textarea
                        name="notes"
                        class="form-input"
                        rows="3"
                        maxlength="2000"
                        @disabled(!$isDraft || !$canManage)
                    >{{ old('notes', $report->notes) }}</textarea>
                </div>
            </div>

            @if($isDraft && $canManage)
                <div class="card-footer showroom-actions">
                    <button type="submit" class="btn btn-outline">
                        حفظ الجرد
                    </button>
                </div>
            @endif
        </div>
    </form>

    @if($isDraft && $canSubmit)
        <div class="showroom-workflow-card branch-submit-card">
            <div>
                <strong>الخطوة الأخيرة للفرع</strong>
                <span>
                    بعد إدخال الجرد الفعلي وبيانات محاسب الفرع، أرسل التقرير للمحاسب العام.
                </span>
            </div>

            <form
                method="POST"
                action="{{ route('showroom-daily-reports.submit', $report) }}"
                onsubmit="return confirm('إرسال التقرير للمحاسب العام؟ بعد الإرسال لن يستطيع الفرع تعديله إلا إذا أعاده المحاسب للتصحيح.')"
            >
                @csrf
                <button type="submit" class="btn btn-gold">
                    إرسال للمحاسب العام
                </button>
            </form>
        </div>
    @endif

    @if(
        auth()->user()->isAdmin()
        || auth()->user()->can('showroom_cake_daily_reports.view')
    )
        @if(\Illuminate\Support\Facades\Route::has('showroom-cake-daily-reports.index'))
            <div class="showroom-workflow-card" style="margin-top:1rem">
                <div>
                    <strong>تقرير قسم الكيك</strong>
                    <span>
                        تقرير يومي منفصل خاص بطلبات كيك الفروع فقط.
                    </span>
                </div>

                <a
                    href="{{ route('showroom-cake-daily-reports.index', ['location_id' => $locationId, 'date' => $date]) }}"
                    class="btn btn-outline"
                >
                    فتح تقرير قسم الكيك
                </a>
            </div>
        @endif
    @endif

    @if($isSubmitted && $canReview)
        <div class="showroom-workflow-card accounting-review-card">
            <div class="accounting-review-head">
                <div>
                    <strong>تدقيق المحاسب العام</strong>
                    <span>
                        راجع فواتير الاستلام، الجرد الفعلي والفروقات قبل الاعتماد.
                    </span>
                </div>
            </div>

            <div class="accounting-review-actions">
                <form
                    method="POST"
                    action="{{ route('showroom-daily-reports.approve', $report) }}"
                    class="review-form"
                    onsubmit="return confirm('اعتماد التقرير وترحيل المتبقي لليوم التالي؟')"
                >
                    @csrf
                    <textarea
                        name="review_notes"
                        class="form-input"
                        rows="2"
                        maxlength="2000"
                        placeholder="ملاحظة المحاسب — اختيارية عند الاعتماد"
                    ></textarea>

                    <button type="submit" class="btn btn-success">
                        اعتماد التقرير
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('showroom-daily-reports.return', $report) }}"
                    class="review-form"
                >
                    @csrf
                    <textarea
                        name="review_notes"
                        class="form-input"
                        rows="2"
                        maxlength="2000"
                        required
                        placeholder="سبب الإرجاع للفرع للتصحيح"
                    ></textarea>

                    <button type="submit" class="btn btn-danger">
                        إرجاع للفرع
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($isApproved && $canReopen)
        <div class="showroom-close-panel">
            <form
                method="POST"
                action="{{ route('showroom-daily-reports.reopen', $report) }}"
                onsubmit="return confirm('إعادة فتح التقرير للفرع؟')"
            >
                @csrf
                <button
                    type="submit"
                    class="btn btn-outline"
                    @disabled($hasLater)
                    title="{{ $hasLater ? 'يوجد تقرير يوم لاحق يعتمد على هذا الرصيد' : '' }}"
                >
                    إعادة فتح للتصحيح
                </button>
            </form>
        </div>
    @endif
@endif
@endsection

@push('styles')
<style>
.showroom-filter-card{margin-bottom:1rem}
.showroom-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(190px,.7fr) auto;gap:.8rem;align-items:end}
.showroom-empty{text-align:center;padding:2rem}
.showroom-status-bar{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;margin:0 0 1rem;padding:.8rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:12px}
.showroom-status-bar > div{display:grid;gap:.15rem;margin-inline-end:auto}
.showroom-status-bar small{color:var(--text-muted)}
.showroom-summary-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:1rem}
.receiving-card{margin-bottom:1rem}
.receiving-invoice-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.8rem;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--border)}
.receiving-invoice-row:last-child{border-bottom:0}
.receiving-invoice-row > div:first-child{display:grid;gap:.18rem}
.receiving-invoice-row small{color:var(--text-muted);font-size:.7rem}
.receiving-invoice-qty{font-weight:900}
.showroom-report-card{overflow:visible}
.showroom-report-card .table-responsive{
    display:block;
    width:100%;
    max-width:100%;
    overflow-x:scroll!important;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:auto;
    scrollbar-gutter:stable;
    padding-bottom:.35rem;
}
.showroom-report-card .table-responsive::-webkit-scrollbar{height:12px}
.showroom-report-card .table-responsive::-webkit-scrollbar-track{
    background:var(--off-white);
    border-radius:999px;
}
.showroom-report-card .table-responsive::-webkit-scrollbar-thumb{
    background:#b8bec7;
    border:2px solid var(--off-white);
    border-radius:999px;
}
.showroom-report-card .table-responsive::-webkit-scrollbar-thumb:hover{
    background:#8e98a6;
}
.showroom-report-table{
    width:max-content;
    min-width:1700px;
}
.showroom-report-table th,.showroom-report-table td{text-align:center;vertical-align:middle;padding:.45rem}
.showroom-product-name{min-width:170px;text-align:right!important}
.showroom-product-name small{display:block;color:var(--text-muted);font-size:.62rem}
.auto-cell{background:var(--off-white);color:var(--text-muted)}
.system-source-note{display:block;margin-top:.18rem;color:#64748b;font-size:.58rem;font-weight:700}
.takeaway-cell{background:#eef7ff!important;color:#0f4c81!important}
.showroom-accounting-section{border-top:1px solid var(--border);background:color-mix(in srgb,var(--surface) 82%,#fff)}
.showroom-accounting-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:1rem}
.showroom-accounting-head > div{display:grid;gap:.22rem}
.showroom-accounting-head small{color:var(--text-muted);font-size:.7rem}
.showroom-accounting-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.8rem}
.showroom-accounting-grid.secondary{grid-template-columns:repeat(3,minmax(0,1fr))}
.showroom-accounting-block{display:grid;gap:.85rem}
.showroom-accounting-block-title{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-end;flex-wrap:wrap}
.showroom-accounting-block-title span{font-weight:900}
.showroom-accounting-block-title small{color:var(--text-muted);font-size:.68rem}
.showroom-accounting-divider{height:2px;background:linear-gradient(90deg,transparent,var(--border),transparent);margin:1.1rem 0}
.showroom-accounting-total{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.9rem 1rem;border:1px solid #f2c96d;background:#fff8df;border-radius:10px;font-weight:900}
.showroom-accounting-total strong{font-size:1.15rem;color:#8c6818}
.showroom-accounting-total.secondary-total{background:#eef7ee;border-color:#b7d7b0}
.showroom-accounting-total.secondary-total strong{color:#2f6f2f}
.showroom-review-queue-item{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem .9rem;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:inherit;margin-bottom:.55rem;background:var(--surface);transition:.15s ease}
.showroom-review-queue-item:last-child{margin-bottom:0}
.showroom-review-queue-item:hover{border-color:var(--gold);background:#fffaf0}
.showroom-review-queue-item>div{display:grid;gap:.18rem;min-width:0}
.showroom-review-queue-item small{color:var(--text-muted);font-size:.7rem}
@media(max-width:600px){.showroom-review-queue-item{align-items:stretch;flex-direction:column}.showroom-review-queue-item .btn{width:100%}}
.showroom-num,.showroom-note{width:100%;min-width:88px;padding:.42rem .45rem;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);font:inherit;text-align:center}
.showroom-note{min-width:140px;text-align:right}
.showroom-num:disabled,.showroom-note:disabled{background:var(--off-white);color:var(--text-muted)}
.has-variance{color:#b42318!important;font-weight:900}
.showroom-actions{display:flex;justify-content:flex-end}
.showroom-bottom-scroll{
    width:100%;
    overflow-x:scroll;
    overflow-y:hidden;
    height:18px;
    border-top:1px solid var(--border);
    border-bottom:1px solid var(--border);
    background:var(--off-white);
    scrollbar-width:auto;
    scrollbar-gutter:stable;
}
.showroom-bottom-scroll-inner{
    width:1700px;
    height:1px;
}
.showroom-bottom-scroll::-webkit-scrollbar{height:14px}
.showroom-bottom-scroll::-webkit-scrollbar-track{background:#e5e7eb}
.showroom-bottom-scroll::-webkit-scrollbar-thumb{
    background:#7f8a99;
    border:2px solid #e5e7eb;
    border-radius:999px;
}
.showroom-workflow-card{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-top:1rem;padding:1rem;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
.showroom-workflow-card > div:first-child{display:grid;gap:.2rem}
.showroom-workflow-card span{color:var(--text-muted);font-size:.72rem}
.accounting-review-card{display:block}
.accounting-review-actions{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem}
.review-form{display:grid;gap:.65rem}
.showroom-close-panel{display:flex;justify-content:flex-end;margin-top:1rem}
@media(max-width:900px){
    .showroom-filter-grid,.showroom-summary-grid,.accounting-review-actions,.showroom-accounting-grid,.showroom-accounting-grid.secondary{grid-template-columns:1fr}
    .showroom-workflow-card{align-items:stretch;flex-direction:column}
    .receiving-invoice-row{grid-template-columns:1fr}
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableScroller = document.querySelector(
        '.showroom-report-card .table-responsive'
    );
    const bottomScroller = document.getElementById(
        'showroomBottomScroll'
    );
    const inner = bottomScroller
        ? bottomScroller.querySelector('.showroom-bottom-scroll-inner')
        : null;

    if (!tableScroller || !bottomScroller || !inner) {
        return;
    }

    const syncWidth = function () {
        inner.style.width = tableScroller.scrollWidth + 'px';
    };

    let syncingTable = false;
    let syncingBottom = false;

    tableScroller.addEventListener('scroll', function () {
        if (syncingBottom) return;

        syncingTable = true;
        bottomScroller.scrollLeft = tableScroller.scrollLeft;
        syncingTable = false;
    });

    bottomScroller.addEventListener('scroll', function () {
        if (syncingTable) return;

        syncingBottom = true;
        tableScroller.scrollLeft = bottomScroller.scrollLeft;
        syncingBottom = false;
    });

    syncWidth();
    window.addEventListener('resize', syncWidth);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function numberValue(input) {
        const value = parseFloat(input?.value || '0');
        return Number.isFinite(value) ? value : 0;
    }

    function updateAccountingTotals() {
        let primary = 0;
        document.querySelectorAll('.js-primary-accounting').forEach(function (input) {
            const value = numberValue(input);
            primary += input.classList.contains('js-subtract') ? -value : value;
        });

        let secondary = 0;
        document.querySelectorAll('.js-secondary-accounting').forEach(function (input) {
            const value = numberValue(input);
            secondary += input.classList.contains('js-subtract') ? -value : value;
        });

        const primaryOutput = document.getElementById('primaryAccountingTotal');
        const secondaryOutput = document.getElementById('secondaryAccountingTotal');

        if (primaryOutput) primaryOutput.textContent = primary.toFixed(2);
        if (secondaryOutput) secondaryOutput.textContent = secondary.toFixed(2);
    }

    document.querySelectorAll(
        '.js-primary-accounting, .js-secondary-accounting'
    ).forEach(function (input) {
        input.addEventListener('input', updateAccountingTotals);
        input.addEventListener('change', updateAccountingTotals);
    });

    updateAccountingTotals();
});
</script>
@endpush

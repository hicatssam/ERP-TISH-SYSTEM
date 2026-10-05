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
            الاستلام والمبيعات تُقرأ تلقائيًا من النظام، ومسؤول الفرع يدخل فقط جرد آخر النهار وما يلزم من ملاحظات.
            بعد الإرسال يتولى المحاسب العام التدقيق والاعتماد.
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
            'damaged' => $report->items->sum(fn($i) => (float) $i->damaged_kg),
            'returned' => $report->items->sum(fn($i) => (float) $i->returned_kg),
            'other' => $report->items->sum(fn($i) => (float) $i->other_net_kg),
            'expected' => $report->items->sum(fn($i) => (float) $i->expected_closing_kg),
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
                    الوارد بالكيلو لا يُكتب مرة ثانية هنا؛ يتم أخذه من استلامات المخزون الموجودة أصلًا.
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
                        href="{{ route('stock-receiving-invoices.show', $invoice) }}"
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
                        الأعمدة الرمادية محسوبة تلقائيًا من المخزون. مسؤول الفرع يدخل فقط وارد السدر إن لزم، والمتبقي الفعلي آخر اليوم، والملاحظة.
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
                            <th>تالف / كجم</th>
                            <th>مرتجع / كجم</th>
                            <th>حركات أخرى صافي</th>
                            <th>رصيد النظام</th>
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

                                <td>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="items[{{ $item->id }}][received_trays]"
                                        value="{{ old("items.{$item->id}.received_trays", $item->received_trays) }}"
                                        class="showroom-num"
                                        @disabled(!$isDraft || !$canManage)
                                    >
                                </td>

                                <td class="auto-cell">{{ number_format((float) $item->received_kg, 3) }}</td>
                                <td class="auto-cell">{{ number_format((float) $item->sales_kg, 3) }}</td>
                                <td class="auto-cell">{{ number_format((float) $item->damaged_kg, 3) }}</td>
                                <td class="auto-cell">{{ number_format((float) $item->returned_kg, 3) }}</td>

                                <td class="auto-cell {{ abs((float) $item->other_net_kg) > 0.0005 ? 'has-variance' : '' }}">
                                    {{ number_format((float) $item->other_net_kg, 3) }}
                                </td>

                                <td class="auto-cell">
                                    <strong>{{ number_format((float) $item->expected_closing_kg, 3) }}</strong>
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
                                <td colspan="13">
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
                            <th>{{ number_format($totals['damaged'], 3) }}</th>
                            <th>{{ number_format($totals['returned'], 3) }}</th>
                            <th>{{ number_format($totals['other'], 3) }}</th>
                            <th>{{ number_format($totals['expected'], 3) }}</th>
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
                    بعد إدخال المتبقي الفعلي لكل صنف، أرسل التقرير للمحاسب العام.
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
                        راجع فواتير الاستلام، رصيد النظام، الجرد الفعلي والفروقات قبل الاعتماد.
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
    .showroom-filter-grid,.showroom-summary-grid,.accounting-review-actions{grid-template-columns:1fr}
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
@endpush

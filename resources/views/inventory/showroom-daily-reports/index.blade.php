@extends('layouts.app')

@section('title', 'تقرير المعرض اليومي')
@section('page-title', 'تقرير المعرض اليومي')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">تقرير المعرض اليومي</h1>
        <p class="page-subheading">
            رصيد سابق + وارد اليوم − المبيعات − التالف − المرتجع = الرصيد النظري،
            ثم يتم إدخال المتبقي الفعلي ليُرحّل لليوم التالي.
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
                    آخر تقرير مسجل:
                    <strong>{{ $prior->business_date->format('Y-m-d') }}</strong>
                    — حالته:
                    <strong>{{ $prior->status === 'closed' ? 'مقفل' : 'مسودة' }}</strong>
                </p>
            @else
                <p>
                    هذا أول تقرير للفرع؛ ستدخل رصيد البداية يدويًا مرة واحدة،
                    وبعد ذلك يترحل المتبقي تلقائيًا من اليوم السابق.
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
        $isClosed = $report->status === 'closed';
        $openingLocked = (bool) $prior;
        $totals = [
            'opening' => $report->items->sum(fn($i) => (float) $i->opening_kg),
            'trays' => $report->items->sum(fn($i) => (float) $i->received_trays),
            'received' => $report->items->sum(fn($i) => (float) $i->received_kg),
            'sales' => $report->items->sum(fn($i) => (float) $i->sales_kg),
            'damaged' => $report->items->sum(fn($i) => (float) $i->damaged_kg),
            'returned' => $report->items->sum(fn($i) => (float) $i->returned_kg),
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

        <span class="badge {{ $isClosed ? 'badge-active' : 'badge-warning' }}">
            {{ $isClosed ? 'مقفل' : 'مسودة' }}
        </span>

        @if($isClosed && $report->closedBy)
            <small>
                أغلقه {{ $report->closedBy->display_name }}
                · {{ $report->closed_at?->format('Y-m-d H:i') }}
            </small>
        @endif
    </div>

    <div class="stats-grid showroom-summary-grid">
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['opening'], 3) }}</div><div class="stat-label">الرصيد السابق / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['received'], 3) }}</div><div class="stat-label">وارد اليوم / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['sales'], 3) }}</div><div class="stat-label">مبيعات اليوم / كجم</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format($totals['actual'], 3) }}</div><div class="stat-label">المتبقي الفعلي / كجم</div></div></div>
    </div>

    <form
        method="POST"
        action="{{ route('showroom-daily-reports.update', $report) }}"
        id="showroomDailyReportForm"
    >
        @csrf
        @method('PUT')

        <div class="card showroom-report-card">
            <div class="card-header">
                <div>
                    <div class="card-title">كشف تفصيلي للحلويات</div>
                    <small class="text-muted">
                        المتبقي الفعلي هو الذي يترحل كرصد سابق لليوم التالي.
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
                            <th>رصيد نظري / كجم</th>
                            <th>المتبقي الفعلي / كجم</th>
                            <th>الفرق / كجم</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($report->items as $item)
                            <tr
                                class="showroom-item-row"
                                data-row-id="{{ $item->id }}"
                            >
                                <td>{{ $loop->iteration }}</td>
                                <td class="showroom-product-name">
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->product?->sku)
                                        <small dir="ltr">{{ $item->product->sku }}</small>
                                    @endif
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        name="items[{{ $item->id }}][opening_kg]"
                                        value="{{ old("items.{$item->id}.opening_kg", $item->opening_kg) }}"
                                        class="showroom-num opening"
                                        @disabled($isClosed || $openingLocked)
                                    >
                                    @if($openingLocked)
                                        <input
                                            type="hidden"
                                            name="items[{{ $item->id }}][opening_kg]"
                                            value="{{ $item->opening_kg }}"
                                        >
                                    @endif
                                </td>

                                @foreach([
                                    'received_trays' => ['0.01', 'trays'],
                                    'received_kg' => ['0.001', 'received'],
                                    'sales_kg' => ['0.001', 'sales'],
                                    'damaged_kg' => ['0.001', 'damaged'],
                                    'returned_kg' => ['0.001', 'returned'],
                                ] as $field => [$step, $class])
                                    <td>
                                        <input
                                            type="number"
                                            step="{{ $step }}"
                                            min="0"
                                            name="items[{{ $item->id }}][{{ $field }}]"
                                            value="{{ old("items.{$item->id}.{$field}", $item->{$field}) }}"
                                            class="showroom-num {{ $class }}"
                                            @disabled($isClosed || !$canManage)
                                        >
                                    </td>
                                @endforeach

                                <td>
                                    <strong class="expected">
                                        {{ number_format((float) $item->expected_closing_kg, 3) }}
                                    </strong>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        name="items[{{ $item->id }}][actual_closing_kg]"
                                        value="{{ old("items.{$item->id}.actual_closing_kg", $item->actual_closing_kg) }}"
                                        class="showroom-num actual"
                                        placeholder="الجرد الفعلي"
                                        @disabled($isClosed || !$canManage)
                                    >
                                </td>

                                <td>
                                    <strong class="variance {{ (float) ($item->variance_kg ?? 0) != 0 ? 'has-variance' : '' }}">
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
                                        @disabled($isClosed || !$canManage)
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
                            <th id="totalOpening">{{ number_format($totals['opening'], 3) }}</th>
                            <th id="totalTrays">{{ number_format($totals['trays'], 2) }}</th>
                            <th id="totalReceived">{{ number_format($totals['received'], 3) }}</th>
                            <th id="totalSales">{{ number_format($totals['sales'], 3) }}</th>
                            <th id="totalDamaged">{{ number_format($totals['damaged'], 3) }}</th>
                            <th id="totalReturned">{{ number_format($totals['returned'], 3) }}</th>
                            <th id="totalExpected">{{ number_format($totals['expected'], 3) }}</th>
                            <th id="totalActual">{{ number_format($totals['actual'], 3) }}</th>
                            <th id="totalVariance">{{ number_format($totals['variance'], 3) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">ملاحظات التقرير</label>
                    <textarea
                        name="notes"
                        class="form-input"
                        rows="3"
                        maxlength="2000"
                        @disabled($isClosed || !$canManage)
                    >{{ old('notes', $report->notes) }}</textarea>
                </div>
            </div>

            @if(!$isClosed && $canManage)
                <div class="card-footer showroom-actions">
                    <button type="submit" class="btn btn-gold">
                        حفظ التقرير
                    </button>
                </div>
            @endif
        </div>
    </form>

    <div class="showroom-close-panel">
        @if(!$isClosed && $canClose)
            <form
                method="POST"
                action="{{ route('showroom-daily-reports.close', $report) }}"
                onsubmit="return confirm('بعد الإقفال سيصبح المتبقي الفعلي رصيد بداية اليوم التالي. متابعة؟')"
            >
                @csrf
                <button type="submit" class="btn btn-success">
                    إقفال اليوم وترحيل الرصيد
                </button>
            </form>
        @elseif($isClosed && $canReopen)
            <form
                method="POST"
                action="{{ route('showroom-daily-reports.reopen', $report) }}"
                onsubmit="return confirm('إعادة فتح التقرير للتعديل؟')"
            >
                @csrf
                <button
                    type="submit"
                    class="btn btn-outline"
                    @disabled($hasLater)
                    title="{{ $hasLater ? 'يوجد تقرير يوم لاحق يعتمد على هذا الرصيد' : '' }}"
                >
                    إعادة فتح التقرير
                </button>
            </form>
        @endif
    </div>
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
.showroom-report-card{overflow:hidden}
.showroom-report-table{min-width:1500px}
.showroom-report-table th,.showroom-report-table td{text-align:center;vertical-align:middle;padding:.45rem}
.showroom-product-name{min-width:170px;text-align:right!important}
.showroom-product-name small{display:block;color:var(--text-muted);font-size:.62rem}
.showroom-num,.showroom-note{width:100%;min-width:88px;padding:.42rem .45rem;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);font:inherit;text-align:center}
.showroom-note{min-width:140px;text-align:right}
.showroom-num:disabled,.showroom-note:disabled{background:var(--off-white);color:var(--text-muted)}
.expected{color:#0b6fcb}
.variance.has-variance{color:#b42318}
.showroom-actions{display:flex;justify-content:flex-end}
.showroom-close-panel{display:flex;justify-content:flex-end;margin-top:1rem}
@media(max-width:900px){
    .showroom-filter-grid,.showroom-summary-grid{grid-template-columns:1fr}
}
</style>
@endpush

@push('scripts')
<script>
function showroomNumber(input) {
    const value = parseFloat(input?.value || '0');
    return Number.isFinite(value) ? value : 0;
}

function recalculateShowroomReport() {
    let totals = {
        opening: 0,
        trays: 0,
        received: 0,
        sales: 0,
        damaged: 0,
        returned: 0,
        expected: 0,
        actual: 0,
        variance: 0,
    };

    document.querySelectorAll('.showroom-item-row').forEach((row) => {
        const opening = showroomNumber(row.querySelector('.opening'));
        const trays = showroomNumber(row.querySelector('.trays'));
        const received = showroomNumber(row.querySelector('.received'));
        const sales = showroomNumber(row.querySelector('.sales'));
        const damaged = showroomNumber(row.querySelector('.damaged'));
        const returned = showroomNumber(row.querySelector('.returned'));
        const actualInput = row.querySelector('.actual');

        const expected = Math.max(
            0,
            opening + received - sales - damaged - returned
        );

        const hasActual = actualInput && actualInput.value !== '';
        const actual = hasActual ? showroomNumber(actualInput) : 0;
        const variance = hasActual ? actual - expected : null;

        row.querySelector('.expected').textContent = expected.toFixed(3);

        const varianceEl = row.querySelector('.variance');
        varianceEl.textContent = variance === null ? '—' : variance.toFixed(3);
        varianceEl.classList.toggle(
            'has-variance',
            variance !== null && Math.abs(variance) > 0.0005
        );

        totals.opening += opening;
        totals.trays += trays;
        totals.received += received;
        totals.sales += sales;
        totals.damaged += damaged;
        totals.returned += returned;
        totals.expected += expected;
        totals.actual += actual;
        totals.variance += variance ?? 0;
    });

    const fields = {
        totalOpening: [totals.opening, 3],
        totalTrays: [totals.trays, 2],
        totalReceived: [totals.received, 3],
        totalSales: [totals.sales, 3],
        totalDamaged: [totals.damaged, 3],
        totalReturned: [totals.returned, 3],
        totalExpected: [totals.expected, 3],
        totalActual: [totals.actual, 3],
        totalVariance: [totals.variance, 3],
    };

    Object.entries(fields).forEach(([id, [value, digits]]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value.toFixed(digits);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document
        .querySelectorAll('.showroom-item-row input')
        .forEach((input) => {
            input.addEventListener('input', recalculateShowroomReport);
        });

    recalculateShowroomReport();
});
</script>
@endpush

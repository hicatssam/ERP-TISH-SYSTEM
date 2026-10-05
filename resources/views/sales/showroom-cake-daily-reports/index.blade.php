@extends('layouts.app')

@section('title', 'تقرير قسم الكيك')
@section('page-title', 'تقرير قسم الكيك')

@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة لدى الفرع',
        'submitted' => 'بانتظار تدقيق المحاسب العام',
        'approved' => 'معتمد',
    ];

    $statusClasses = [
        'draft' => 'badge-warning',
        'submitted' => 'badge-warning',
        'approved' => 'badge-active',
    ];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">تقرير قسم الكيك</h1>
        <p class="page-subheading">
            هذا التقرير خاص بطلبات كيك الفروع فقط. الكيك الخاص بالزبائن لا يدخل في هذا التقرير.
        </p>
    </div>

    @if($report)
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('showroom-cake-daily-reports.print', $report) }}" target="_blank" class="btn btn-outline">طباعة</a>
            <a href="{{ route('showroom-cake-daily-reports.pdf', $report) }}" target="_blank" class="btn btn-outline">PDF</a>
        </div>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>راجع البيانات:</strong>
        <ul style="margin:.45rem 0 0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="GET" action="{{ route('showroom-cake-daily-reports.index') }}" class="card cake-report-filter">
    <div class="card-body cake-report-filter-grid">
        <div class="form-group">
            <label class="form-label">الفرع</label>
            <select name="location_id" class="form-select">
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int)$locationId === (int)$location->id)>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">اليوم والتاريخ</label>
            <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-input">
        </div>

        <button type="submit" class="btn btn-gold">عرض التقرير</button>
    </div>
</form>

@if(!$report)
    <div class="card">
        <div class="card-body cake-report-empty">
            <h3>لا يوجد تقرير قسم كيك لهذا اليوم</h3>

            @if($prior)
                <p>
                    آخر تقرير:
                    <strong>{{ $prior->business_date->format('Y-m-d') }}</strong>
                    — {{ $statusLabels[$prior->status] ?? $prior->status }}
                </p>
            @endif

            @if($canManage)
                <form method="POST" action="{{ route('showroom-cake-daily-reports.create') }}">
                    @csrf
                    <input type="hidden" name="location_id" value="{{ $locationId }}">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <button type="submit" class="btn btn-gold">إنشاء تقرير قسم الكيك</button>
                </form>
            @endif
        </div>
    </div>
@else
    @php
        $isDraft = $report->status === 'draft';
        $isSubmitted = $report->status === 'submitted';
        $isApproved = $report->status === 'approved';

        $totals = [
            'opening' => $visibleItems->sum('opening_qty'),
            'received' => $visibleItems->sum('received_qty'),
            'total' => $visibleItems->sum('total_qty'),
            'sold' => $visibleItems->sum('sold_qty'),
            'closing' => $visibleItems->sum(fn($item) => (int)($item->actual_closing_qty ?? 0)),
        ];
    @endphp

    <div class="cake-report-status">
        <div>
            <strong>{{ $report->location->name }}</strong>
            <span>{{ $report->business_date->format('Y-m-d') }}</span>
        </div>

        <span class="badge {{ $statusClasses[$report->status] ?? 'badge-secondary' }}">
            {{ $statusLabels[$report->status] ?? $report->status }}
        </span>

        @if($report->submittedBy)
            <small>
                أرسله {{ $report->submittedBy->display_name }}
                @if($report->submitted_at) · {{ $report->submitted_at->format('Y-m-d H:i') }} @endif
            </small>
        @endif

        @if($report->reviewedBy)
            <small>
                راجعه {{ $report->reviewedBy->display_name }}
                @if($report->reviewed_at) · {{ $report->reviewed_at->format('Y-m-d H:i') }} @endif
            </small>
        @endif
    </div>

    @if($report->review_notes)
        <div class="alert alert-info" style="margin-bottom:1rem">
            <strong>ملاحظة المحاسب العام:</strong> {{ $report->review_notes }}
        </div>
    @endif

    <div class="stats-grid cake-report-summary">
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $totals['opening'] }}</div><div class="stat-label">السابق</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $totals['received'] }}</div><div class="stat-label">الوارد من طلبات الفروع</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $totals['sold'] }}</div><div class="stat-label">المباع</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $totals['closing'] }}</div><div class="stat-label">المتبقي</div></div></div>
    </div>

    <div class="card cake-source-card">
        <div class="card-header">
            <div>
                <div class="card-title">طلبات كيك الفروع المستلمة اليوم</div>
                <small class="text-muted">
                    فقط الطلبات التي وصلت لحالة مكتمل للفرع تدخل في عمود الوارد. لا يتم قراءة أي سجل من الكيك الخاص.
                </small>
            </div>
        </div>

        <div class="card-body">
            @forelse($sourceRequests as $sourceRequest)
                <div class="cake-source-row">
                    <div>
                        <strong>{{ $sourceRequest->request_number }}</strong>
                        <small>
                            {{ $sourceRequest->factoryLocation?->name ?? 'المصنع' }}
                            · {{ $sourceRequest->fulfilled_at?->format('H:i') ?? '—' }}
                        </small>
                    </div>
                    <span>{{ $sourceRequest->items->sum('quantity') }} وحدة</span>
                    <a href="{{ route('showroom-cake-requests.show', $sourceRequest) }}" class="btn btn-outline btn-xs" target="_blank">
                        فتح الطلب
                    </a>
                </div>
            @empty
                <div class="empty-state-sm">لا توجد طلبات كيك فروع مكتملة لهذا اليوم.</div>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('showroom-cake-daily-reports.update', $report) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">قسم الكيك</div>
                    <small class="text-muted">
                        يظهر فقط الصنف المطلوب لهذا اليوم أو الصنف الذي لديه متبقي مرحّل من اليوم السابق. الوارد والمجموع محسوبة تلقائيًا، والفرع يدخل فقط المتبقي آخر النهار.
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table cake-report-table">
                    <thead>
                        <tr>
                            <th>مسلسل</th>
                            <th>اسم الصنف</th>
                            <th>سابق</th>
                            <th>وارد</th>
                            <th>مجموع</th>
                            <th>مباع</th>
                            <th>المتبقي / وحدة</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($visibleItems as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="cake-name"><strong>{{ $item->category_name }}</strong></td>
                                <td class="auto-cell">{{ $item->opening_qty }}</td>
                                <td class="auto-cell">{{ $item->received_qty }}</td>
                                <td class="auto-cell"><strong>{{ $item->total_qty }}</strong></td>
                                <td class="auto-cell">{{ $item->sold_qty }}</td>
                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        max="{{ $item->total_qty }}"
                                        name="items[{{ $item->id }}][actual_closing_qty]"
                                        value="{{ old("items.{$item->id}.actual_closing_qty", $item->actual_closing_qty) }}"
                                        class="cake-num"
                                        placeholder="المتبقي"
                                        @disabled(!$isDraft || !$canManage)
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        maxlength="500"
                                        name="items[{{ $item->id }}][note]"
                                        value="{{ old("items.{$item->id}.note", $item->note) }}"
                                        class="cake-note"
                                        @disabled(!$isDraft || !$canManage)
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty-state-sm">
                                    لا توجد أصناف مطلوبة لهذا اليوم ولا رصيد متبقٍ من اليوم السابق.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="2">الإجمالي</th>
                            <th>{{ $totals['opening'] }}</th>
                            <th>{{ $totals['received'] }}</th>
                            <th>{{ $totals['total'] }}</th>
                            <th>{{ $totals['sold'] }}</th>
                            <th>{{ $totals['closing'] }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">ملاحظات الفرع</label>
                    <textarea name="notes" rows="3" maxlength="2000" class="form-input" @disabled(!$isDraft || !$canManage)>{{ old('notes', $report->notes) }}</textarea>
                </div>
            </div>

            @if($isDraft && $canManage)
                <div class="card-footer cake-actions">
                    <button type="submit" class="btn btn-outline">حفظ الجرد</button>
                </div>
            @endif
        </div>
    </form>

    @if($isDraft && $canManage)
        <div class="cake-workflow">
            <div>
                <strong>إرسال للمحاسب العام</strong>
                <span>بعد التأكد من المتبقي لكل صنف، أرسل التقرير للتدقيق.</span>
            </div>
            <form method="POST" action="{{ route('showroom-cake-daily-reports.submit', $report) }}"
                  onsubmit="return confirm('إرسال تقرير قسم الكيك للمحاسب العام؟')">
                @csrf
                <button type="submit" class="btn btn-gold">إرسال للمحاسب العام</button>
            </form>
        </div>
    @endif

    @if($isSubmitted && $canReview)
        <div class="cake-review-card">
            <div>
                <strong>تدقيق المحاسب العام</strong>
                <span>راجع الوارد من طلبات كيك الفروع، المباع، والمتبقي قبل الاعتماد.</span>
            </div>

            <div class="cake-review-grid">
                <form method="POST" action="{{ route('showroom-cake-daily-reports.approve', $report) }}" class="cake-review-form">
                    @csrf
                    <textarea name="review_notes" class="form-input" rows="2" maxlength="2000" placeholder="ملاحظة اختيارية"></textarea>
                    <button type="submit" class="btn btn-success">اعتماد التقرير</button>
                </form>

                <form method="POST" action="{{ route('showroom-cake-daily-reports.return', $report) }}" class="cake-review-form">
                    @csrf
                    <textarea name="review_notes" class="form-input" rows="2" maxlength="2000" required placeholder="سبب الإرجاع للفرع"></textarea>
                    <button type="submit" class="btn btn-danger">إرجاع للفرع</button>
                </form>
            </div>
        </div>
    @endif

    @if($isApproved && $canReview)
        <div class="cake-actions" style="margin-top:1rem">
            <form method="POST" action="{{ route('showroom-cake-daily-reports.reopen', $report) }}"
                  onsubmit="return confirm('إعادة فتح تقرير قسم الكيك للتصحيح؟')">
                @csrf
                <button type="submit" class="btn btn-outline">إعادة فتح للتصحيح</button>
            </form>
        </div>
    @endif
@endif
@endsection

@push('styles')
<style>
.cake-report-filter{margin-bottom:1rem}
.cake-report-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(190px,.7fr) auto;gap:.8rem;align-items:end}
.cake-report-empty{text-align:center;padding:2rem}
.cake-report-status{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;padding:.8rem 1rem;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
.cake-report-status>div{display:grid;gap:.15rem;margin-inline-end:auto}
.cake-report-status small{color:var(--text-muted)}
.cake-report-summary{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:1rem}
.cake-source-card{margin-bottom:1rem}
.cake-source-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.8rem;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--border)}
.cake-source-row:last-child{border-bottom:0}
.cake-source-row>div{display:grid;gap:.15rem}
.cake-source-row small{color:var(--text-muted)}
.cake-report-table{min-width:920px}
.cake-report-table th,.cake-report-table td{text-align:center;vertical-align:middle}
.cake-name{text-align:right!important;min-width:170px}
.auto-cell{background:var(--off-white);color:var(--text-muted)}
.cake-num,.cake-note{width:100%;min-width:90px;padding:.45rem;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);font:inherit;text-align:center}
.cake-note{min-width:150px;text-align:right}
.cake-actions{display:flex;justify-content:flex-end}
.cake-workflow,.cake-review-card{margin-top:1rem;padding:1rem;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
.cake-workflow{display:flex;justify-content:space-between;align-items:center;gap:1rem}
.cake-workflow>div,.cake-review-card>div:first-child{display:grid;gap:.2rem}
.cake-workflow span,.cake-review-card span{color:var(--text-muted);font-size:.72rem}
.cake-review-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem}
.cake-review-form{display:grid;gap:.6rem}
@media(max-width:900px){
    .cake-report-filter-grid,.cake-report-summary,.cake-review-grid{grid-template-columns:1fr}
    .cake-workflow{flex-direction:column;align-items:stretch}
    .cake-source-row{grid-template-columns:1fr}
}
</style>
@endpush

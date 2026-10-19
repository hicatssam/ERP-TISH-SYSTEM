@extends('layouts.app')
@section('title', 'خطة تجهيز طلبات الفروع')
@section('page-title', 'خطة تجهيز الغد')
@section('content')
@php
    $params = array_filter(['date' => $date, 'factory_id' => $factoryId], fn ($v) => $v !== null && $v !== '');
@endphp
<style>
    .prep-page{display:grid;gap:1rem}
    .prep-hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
    .prep-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}
    .prep-kpi{border:1px solid var(--border);border-radius:14px;padding:1rem;background:var(--card-bg,#fff)}
    .prep-kpi strong{font-size:1.65rem;display:block;margin-top:.3rem}
    .prep-kpi span{color:var(--text-muted);font-size:.83rem}
    .prep-branch-pills{display:flex;gap:.3rem;flex-wrap:wrap}
    .prep-branch-pill{background:var(--off-white,#f8fafc);border:1px solid var(--border);border-radius:8px;padding:.3rem .55rem;font-size:.8rem}
    .prep-empty{padding:1.5rem;text-align:center;color:var(--text-muted)}
    .prep-filters{display:flex;gap:.75rem;align-items:end;flex-wrap:wrap}
    .prep-filters label{display:grid;gap:.35rem}
    .prep-qty{font-weight:800;white-space:nowrap}
    .prep-details summary{cursor:pointer;font-weight:700;padding:1rem}
    @media(max-width:800px){.prep-kpis{grid-template-columns:repeat(2,1fr)}.prep-filters label{flex:1 1 140px}}
    @media(max-width:480px){.prep-hero a{flex:1;text-align:center}.prep-kpis{grid-template-columns:1fr 1fr}}
</style>
<div class="prep-page">
    <div class="prep-hero">
        <div>
            <h1 class="page-heading">خطة تجهيز طلبات الفروع — {{ $date }}</h1>
            <p class="page-subheading">كشف تشغيل المصنع ليوم الحاجة المحدد، من جميع الفروع؛ يفصل المطلوب تحضيره عن الأصناف الجاهزة، ولا يحتسب الطلبات المكتملة أو الملغاة.</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a class="btn btn-outline" href="{{ route('reports.tomorrow-preparation.print', $params) }}" target="_blank" rel="noopener">طباعة</a>
            <a class="btn btn-outline" href="{{ route('reports.tomorrow-preparation.pdf', $params) }}">PDF</a>
            <a class="btn btn-gold" href="{{ route('reports.tomorrow-preparation.excel', $params) }}">Excel</a>
        </div>
    </div>

    <form class="card" method="GET" action="{{ route('reports.tomorrow-preparation.index') }}">
        <div class="card-body prep-filters">
            <label><span class="form-label">التجهيز لتاريخ</span>
                <input class="form-input" type="date" name="date" value="{{ $date }}" required>
            </label>
            @if($canChooseFactory)
                <label><span class="form-label">المصنع</span>
                    <select class="form-select" name="factory_id">
                        <option value="">جميع المصانع</option>
                        @foreach($factories as $factory)
                            <option value="{{ $factory->id }}" @selected((int)$factoryId === (int)$factory->id)>{{ $factory->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button type="submit" class="btn btn-gold">عرض الخطة</button>
            <a class="btn btn-ghost" href="{{ route('reports.tomorrow-preparation.index', ['date' => now()->addDay()->toDateString(), 'factory_id' => $factoryId]) }}">تجهيز الغد</a>
        </div>
    </form>

    <div class="prep-kpis">
        <div class="prep-kpi"><span>طلبات الحلويات</span><strong>{{ $summary['sweets_requests'] }}</strong></div>
        <div class="prep-kpi"><span>طلبات الكيك</span><strong>{{ $summary['cake_requests'] }}</strong></div>
        <div class="prep-kpi"><span>فروع أرسلت طلبات</span><strong>{{ $summary['branches_responded'] }}</strong></div>
        <div class="prep-kpi"><span>فروع لم ترسل</span><strong>{{ $summary['branches_missing'] }}</strong></div>
    </div>

    @if($summary['undated_count'])
        <div class="alert alert-warning" role="alert">
            <strong>{{ $summary['undated_count'] }} طلب غير محدد بتاريخ حاجة.</strong>
            وُجدت طلبات أُنشئت في اليوم السابق لهذا التاريخ بدون تاريخ حاجة. لم نضمّ كمياتها للخطة تفاديًا لتجهيزها في يوم خاطئ؛ راجع تفاصيلها أسفل الصفحة.
        </div>
    @endif

    <section class="card">
        <div class="card-header"><span class="card-title">حالة طلبات الفروع — {{ $scopeLabel }}</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>الفرع</th><th>طلبات الحلويات</th><th>طلبات الكيك</th><th>حالة الفرع</th></tr></thead>
                <tbody>
                @forelse($branches as $branch)
                    <tr>
                        <td><strong>{{ $branch['name'] }}</strong></td>
                        <td>{{ $branch['sweets_count'] }}</td>
                        <td>{{ $branch['cakes_count'] }}</td>
                        <td>{{ $branch['missing'] ? 'لم يصل طلب' : 'وصل طلب' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">لم تُعرّف فروع نشطة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach([['title' => 'حلويات الفروع — خطة التصنيع', 'rows' => $sweetRows], ['title' => 'كيك الفروع — خطة التصنيع', 'rows' => $cakeRows]] as $section)
        <section class="card">
            <div class="card-header">
                <div><span class="card-title">{{ $section['title'] }}</span>
                    <p class="form-help">الكميات مُجمّعة لكل صنف ووحدة قياس؛ «جاهز» لا يدخل ضمن الكمية المتبقية للتحضير.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>الصنف / المواصفات</th><th>الوحدة</th><th>المطلوب تحضيره</th><th>جاهز</th><th>إجمالي الطلبات</th><th>محجوز للعملاء</th><th>تفصيل الفروع</th></tr></thead>
                    <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td>{{ $row['unit'] }}</td>
                            <td class="prep-qty">{{ number_format($row['to_prepare'], 3) }}</td>
                            <td>{{ number_format($row['ready'], 3) }}</td>
                            <td><strong>{{ number_format($row['quantity'], 3) }}</strong></td>
                            <td>{{ number_format($row['reserved'], 3) }}</td>
                            <td>
                                <div class="prep-branch-pills">
                                    @foreach($row['branches'] as $branch)
                                        <span class="prep-branch-pill">{{ $branch['name'] }}: {{ number_format($branch['quantity'], 3) }}</span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="prep-empty">لا توجد أصناف لهذا القسم في التاريخ المختار.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <details class="card prep-details" open>
        <summary>تفاصيل الطلبات والملاحظات — {{ $details->count() }} بند</summary>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>رقم الطلب</th><th>القسم</th><th>الفرع</th><th>الصنف</th><th>الكمية</th><th>محجوز</th><th>الحالة</th><th>ملاحظات التحضير</th></tr></thead>
                <tbody>
                @forelse($details as $line)
                    <tr>
                        <td>
                            @if(auth()->user()->isAdmin() || auth()->user()->can('showroom_sweets_requests.view') || auth()->user()->can('showroom_cake_requests.view'))
                                <a href="{{ $line['kind'] === 'cake' ? route('showroom-cake-requests.show', $line['request_id']) : route('showroom-sweets-requests.show', $line['request_id']) }}">{{ $line['request_number'] }}</a>
                            @else
                                {{ $line['request_number'] }}
                            @endif
                        </td>
                        <td>{{ $line['section'] }}</td>
                        <td>{{ $line['branch'] }}</td>
                        <td>{{ $line['label'] }}</td>
                        <td>{{ number_format($line['quantity'], 3) }} {{ $line['unit'] }}</td>
                        <td>{{ number_format($line['reserved'], 3) }}</td>
                        <td>{{ $line['status'] }}</td>
                        <td>{{ $line['notes'] ?: ($line['request_notes'] ?: '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="prep-empty">لا توجد طلبات مطلوبة بهذا التاريخ.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </details>

    @if($summary['undated_count'])
        <section class="card">
            <div class="card-header"><span class="card-title">طلبات قديمة بحاجة تحديد تاريخ الحاجة — غير محتسبة</span></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>الرقم</th><th>القسم</th><th>الفرع</th><th>الأصناف والكميات</th><th>تاريخ الإرسال</th></tr></thead>
                    <tbody>
                    @foreach([['kind' => 'حلويات', 'items' => $undatedSweets], ['kind' => 'كيك', 'items' => $undatedCakes]] as $section)
                        @foreach($section['items'] as $request)
                            <tr><td>{{ $request->request_number }}</td><td>{{ $section['kind'] }}</td>
                                <td>{{ $request->requestingLocation?->name ?? '—' }}</td>
                                <td>
                                    @foreach($request->items as $item)
                                        @if($section['kind'] === 'حلويات')
                                            {{ $item->displayProductName() }}: {{ number_format((float)$item->quantity, 3) }} {{ $item->requested_unit }}
                                        @else
                                            {{ \App\Support\CakeProductionLabels::type($item->cake_type) }} ({{ \App\Support\CakeProductionLabels::size($item->cake_size) }}): {{ number_format((float)$item->quantity, 0) }} حبة
                                        @endif
                                        @unless($loop->last) · @endunless
                                    @endforeach
                                </td>
                                <td>{{ $request->created_at?->format('Y-m-d H:i') }}</td></tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <p class="page-subheading">هذا كشف تخطيط وتشغيل فقط، ولا ينشئ أوامر إنتاج أو حركات مخزون أو قيودًا مالية تلقائيًا. الكميات المطلوبة تخص تاريخ الحاجة المختار.</p>
</div>
@endsection

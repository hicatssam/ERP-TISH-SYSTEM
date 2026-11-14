@extends('layouts.app')
@section('title', 'مركز الرقابة المالية')
@section('page-title', 'مركز الرقابة المالية')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">مركز الرقابة المالية</h1>
        <p class="page-subheading">كشف استثناءات الترحيل والقيود والتكاليف قبل تسليم TISH — قراءة فقط دون أي تعديل أو ترحيل تلقائي.</p>
    </div>
    <div class="page-header-actions">
        @can('financial.dashboard.view')
            <a href="{{ route('financial.dashboard') }}" class="btn btn-outline btn-sm">لوحة المالية</a>
        @endcan
        <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline btn-sm">سجل الترحيلات</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" action="{{ route('financial.audit.index') }}" class="filter-grid">
            @if($locations->isNotEmpty())
                <div class="filter-group">
                    <label class="filter-label" for="audit_location">الفرع (يشمل التاريخي للمراجعة)</label>
                    <select id="audit_location" name="location_id" class="form-input" required>
                        <option value="">اختر الفرع</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" @selected((int)$locationId === (int)$loc->id)>
                                {{ $loc->name }}{{ $loc->is_active ? '' : ' — تاريخي/معطّل' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-group"><label class="filter-label" for="audit_from">من</label>
                <input id="audit_from" class="form-input" type="date" name="date_from" value="{{ $from }}" required></div>
            <div class="filter-group"><label class="filter-label" for="audit_to">إلى</label>
                <input id="audit_to" class="form-input" type="date" name="date_to" value="{{ $to }}" required></div>
            <div class="filter-group" style="justify-content:flex-end"><label class="filter-label">&nbsp;</label><button class="btn btn-primary">فحص البيانات</button></div>
        </form>
    </div>
</div>

@if($report === null)
    <div class="alert alert-info">اختر فرعًا لعرض نتائج التدقيق. لا يتم خلط حركات الفروع تلقائيًا.</div>
@else
    @php
        $missing = (int) $report['missing_total'];
        $unbalanced = count($report['unbalanced_journals']);
        $missingCosts = (int) $report['missing_cost_items'];
    @endphp

    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:1rem">
        <div class="stat-card"><div class="stat-info"><div class="stat-label">مصادر بلا قيد مرتبط</div><div class="stat-value">{{ number_format($missing) }}</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-label">قيود غير متوازنة (ضمن النتائج المعروضة)</div><div class="stat-value">{{ number_format($unbalanced) }}</div></div></div>
        <div class="stat-card"><div class="stat-info"><div class="stat-label">بنود طلبات مؤكدة بلا تكلفة</div><div class="stat-value">{{ number_format($missingCosts) }}</div></div></div>
    </div>

    @if($report['truncated'])
        <div class="alert alert-warning" style="margin-bottom:1rem">
            عُرضت أول 200 حركة فقط من المصادر غير المرتبطة بقيد. العدد الإجمالي ظاهر أعلاه، ويجب تضييق الفترة لفحص بقية السجلات.
        </div>
    @endif

    @if($missing || $unbalanced || $missingCosts)
        <div class="alert alert-warning" style="margin-bottom:1rem">
            توجد استثناءات تحتاج مراجعة. قد تكون بعض السجلات تاريخية أو نتجت قبل تفعيل الترحيل المحاسبي؛ لا يتم إنشاء قيود أو تصحيح تكاليف تلقائيًا.
        </div>
    @else
        <div class="alert alert-success" style="margin-bottom:1rem">
            لم يرصد الفحص استثناءات ضمن المؤشرات المدعومة والفترة والفرع المحددين. هذا ليس تدقيقًا محاسبيًا شاملًا لكل الفواتير والمدفوعات.
        </div>
    @endif

    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">مصادر العمليات التي لم يُعثر لها على قيد</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>المصدر</th><th>عدد الاستثناءات</th></tr></thead>
                <tbody>
                    @foreach($report['summary'] as $source => $stats)
                        <tr><td dir="ltr">{{ $source }}</td><td>{{ !empty($stats['missing_table']) ? 'الجدول غير موجود' : number_format((int)$stats['unlinked']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">تفاصيل المصادر بلا قيد (حتى 200 سجل)</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>المصدر</th><th>رقم السجل</th><th>التاريخ</th><th>المبلغ الأساسي</th><th>حالة الفترة</th><th>الإجراء الموصى به</th></tr></thead>
                <tbody>
                    @forelse($report['missing'] as $row)
                        <tr>
                            <td dir="ltr">{{ $row['source'] }}</td>
                            <td>{{ $row['id'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['base_amount'] }}</td>
                            <td>{{ $row['period_status'] }}</td>
                            <td>
                                @switch($row['action'])
                                    @case('open_period_and_review') مراجعة الفترة والمصدر @break
                                    @case('review_source_before_posting') مراجعة المصدر قبل الترحيل @break
                                    @default معالجة محاسبية معتمدة لفترة مغلقة
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد مصادر بلا قيد في الفترة المحددة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">القيود غير المتوازنة (حتى 200 قيد)</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>رقم القيد</th><th>التاريخ</th><th>المدين</th><th>الدائن</th></tr></thead>
                <tbody>
                    @forelse($report['unbalanced_journals'] as $journal)
                        <tr><td>{{ $journal['number'] }}</td><td>{{ $journal['entry_date'] }}</td><td>{{ $journal['total_debit'] }}</td><td>{{ $journal['total_credit'] }}</td></tr>
                    @empty
                        <tr><td colspan="4">لم يظهر قيد غير متوازن ضمن نطاق الفحص.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

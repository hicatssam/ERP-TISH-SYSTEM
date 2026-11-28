@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')
@section('paper_orientation','landscape')
@section('document_title','تقرير صلاحية المخزون')
@section('document_number','EXP-'.now()->format('Ymd-His'))
@section('document_date',now()->format('d/m/Y H:i'))
@section('document_subtitle','متابعة الدفعات والكميات المتبقية حسب الفلاتر المختارة')
@section('signature_right','مسؤول المخزون')
@section('signature_left','اعتماد الإدارة')
@push('print_styles')
<style>
.expiry-report-summary{width:100%;border-collapse:separate;border-spacing:5px;margin:0 0 12px;table-layout:fixed}
.expiry-report-summary td{width:25%;border:1px solid #ded5d9;background:#faf7f8;padding:9px;text-align:right;vertical-align:top}
.expiry-report-summary small{display:block;color:#665660;font-size:8pt}
.expiry-report-summary strong{display:block;font-size:17pt;font-weight:bold;color:#321926;margin-top:3px}
.expiry-report-summary .expiry-alert strong{color:#9f1239}
.expiry-report-note{font-size:8pt;color:#665660;margin:6px 0 12px}
.expiry-report-table{table-layout:fixed;width:100%}
.expiry-report-table thead th{background:#851338!important;color:white!important;font-size:9pt!important;padding:7px 6px!important}
.expiry-report-table tbody td{font-size:9pt!important;padding:6px!important;vertical-align:middle;word-wrap:break-word}
.expiry-report-table .expiry-report-product{font-weight:bold;color:#321926}
.expiry-report-table .expiry-report-quantity{font-weight:bold;font-size:10pt!important;color:#321926}
.expiry-report-table .expiry-report-code{font-size:7.5pt!important;word-wrap:break-word}
.expiry-report-table .expiry-report-sub{display:block;font-size:7.5pt;color:#675964;margin-top:2px}
.expiry-report-table .expiry-critical{color:#9f1239;font-weight:bold}
.expiry-report-table .expiry-near{color:#8a5108;font-weight:bold}
.expiry-report-table .expiry-normal{color:#41594d}
.expiry-report-table tr{page-break-inside:avoid}
.expiry-report-table thead{display:table-header-group}
.expiry-report-table .expiry-empty{text-align:center}
</style>
@endpush
@section('print_content')
@php
    $counts = $reportCounts ?? [
        'total' => $rows->count(),
        'expired' => $rows->filter(fn ($r) => $r->days_left < 0)->count(),
        'today' => $rows->filter(fn ($r) => (int) $r->days_left === 0)->count(),
        'soon' => $rows->filter(fn ($r) => $r->days_left > 0 && $r->days_left <= 7)->count(),
    ];
@endphp
<table class="expiry-report-summary" cellpadding="0" cellspacing="5">
    <tr>
        <td><small>إجمالي الدفعات المطابقة</small><strong>{{ $counts['total'] }}</strong></td>
        <td class="expiry-alert"><small>منتهية الصلاحية</small><strong>{{ $counts['expired'] }}</strong></td>
        <td class="expiry-alert"><small>تنتهي اليوم</small><strong>{{ $counts['today'] }}</strong></td>
        <td><small>تنتهي خلال 1–7 أيام</small><strong>{{ $counts['soon'] }}</strong></td>
    </tr>
</table>
@if($truncated ?? false)
    <p class="print-soft-box">يعرض ملف PDF أول {{ $cap }} دفعة من أصل {{ $total }}. الإحصائيات تشمل كامل النتائج المطابقة. استخدم الطباعة الكاملة أو ضيّق الفلاتر.</p>
@endif
<p class="expiry-report-note">الدفعات مرتبة حسب أقرب تاريخ صلاحية. الكميات المعروضة بوحدات القياس المناسبة دون تعديل الأرصدة.</p>
<div class="print-section">
    <table class="print-table expiry-report-table">
        <thead>
            <tr>
                <th style="width:20%">المنتج</th>
                <th style="width:20%">رقم الدفعة</th>
                <th style="width:17%">الموقع</th>
                <th style="width:13%">الكمية المتبقية</th>
                <th style="width:12%">الإنتاج</th>
                <th style="width:18%">الصلاحية والحالة</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            @php
                $days = (int) $row->days_left;
                $statusText = match(true) {
                    $days < 0 => 'منتهية منذ '.abs($days).' يوم',
                    $days === 0 => 'تنتهي اليوم',
                    $days === 1 => 'تنتهي غدًا',
                    $days === 2 => 'تنتهي بعد يومين',
                    $days <= 10 => 'متبقي '.$days.' أيام',
                    default => 'متبقي '.$days.' يومًا',
                };
                $statusClass = $days <= 1 ? 'expiry-critical' : ($days <= 7 ? 'expiry-near' : 'expiry-normal');
                $quantity = \App\Support\InventoryQuantityDisplay::text($row);
            @endphp
            <tr>
                <td class="expiry-report-product">{{ $row->product_name_ar ?: ($row->product_name ?: 'منتج غير محدد') }}</td>
                <td class="expiry-report-code" dir="ltr">{{ $row->batch_number ?: ('#'.$row->id) }}</td>
                <td>{{ $row->location_name ?: '—' }}</td>
                <td class="expiry-report-quantity">{{ $quantity }}</td>
                <td dir="ltr">{{ $row->manufacturing_date ? \Illuminate\Support\Carbon::parse($row->manufacturing_date)->format('d/m/Y') : '—' }}</td>
                <td><span class="{{ $statusClass }}">{{ $statusText }}</span><span class="expiry-report-sub" dir="ltr">{{ \Illuminate\Support\Carbon::parse($row->expiry_date)->format('d/m/Y') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="expiry-empty">لا توجد دفعات مطابقة للفلاتر المختارة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

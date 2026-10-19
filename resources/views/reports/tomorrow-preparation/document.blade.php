@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')
@section('paper_orientation', 'landscape')
@section('document_title', 'خطة تجهيز طلبات الفروع')
@section('document_number', 'PREP-' . str_replace('-', '', $date))
@section('document_date', $date)
@section('document_subtitle', $scopeLabel . ' — تاريخ الحاجة ' . $date)
@section('document_meta', 'حلويات: ' . $summary['sweets_requests'] . ' طلب · كيك: ' . $summary['cake_requests'] . ' طلب · الفروع المرسلة: ' . $summary['branches_responded'])
@section('signature_right', 'مسؤول الإنتاج')
@section('signature_left', 'مدير المصنع')
@push('print_styles')
<style>
    .prep-print-table th,.prep-print-table td{font-size:7pt!important;padding:4px 5px!important;vertical-align:middle}
    .prep-print-number{font-weight:bold}
    .prep-print-branches{font-size:6.8pt;line-height:1.6}
    .prep-print-hint{font-size:8pt;margin:6px 0 10px}
    .prep-print-table tr{page-break-inside:avoid}
</style>
@endpush
@section('print_content')
    <div class="print-section">
        <div class="print-section-title">متابعة الفروع</div>
        <table class="print-table prep-print-table">
            <thead><tr><th>الفرع</th><th>طلبات الحلويات</th><th>طلبات الكيك</th><th>حالة الإرسال</th></tr></thead>
            <tbody>
            @foreach($branches as $branch)
                <tr><td>{{ $branch['name'] }}</td><td>{{ $branch['sweets_count'] }}</td><td>{{ $branch['cakes_count'] }}</td>
                <td>{{ $branch['missing'] ? 'لم يصل طلب' : 'وصل طلب' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @foreach([['title' => 'حلويات الفروع — المطلوب تحضيره', 'rows' => $sweetRows], ['title' => 'كيك الفروع — المطلوب تحضيره', 'rows' => $cakeRows]] as $section)
        <div class="print-section">
            <div class="print-section-title">{{ $section['title'] }}</div>
            <table class="print-table prep-print-table">
                <thead><tr><th>#</th><th>الصنف / المواصفات</th><th>الوحدة</th><th>للتحضير</th><th>جاهز</th><th>الإجمالي</th><th>محجوز</th><th>تفصيل الفروع</th></tr></thead>
                <tbody>
                @forelse($section['rows'] as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align:right">{{ $row['label'] }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="prep-print-number">{{ number_format($row['to_prepare'], 3) }}</td>
                        <td>{{ number_format($row['ready'], 3) }}</td>
                        <td class="prep-print-number">{{ number_format($row['quantity'], 3) }}</td>
                        <td>{{ number_format($row['reserved'], 3) }}</td>
                        <td class="prep-print-branches">
                            @foreach($row['branches'] as $branch)
                                {{ $branch['name'] }}: {{ number_format($branch['quantity'], 3) }}@unless($loop->last) ؛ @endunless
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">لا توجد أصناف لهذا القسم.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="print-section">
        <div class="print-section-title">تفاصيل الطلبات وملاحظات التجهيز</div>
        <table class="print-table prep-print-table">
            <thead><tr><th>رقم الطلب</th><th>القسم</th><th>الفرع</th><th>الصنف</th><th>الكمية</th><th>محجوز</th><th>الحالة</th><th>ملاحظات</th></tr></thead>
            <tbody>
            @forelse($details as $line)
                <tr>
                    <td>{{ $line['request_number'] }}</td><td>{{ $line['section'] }}</td>
                    <td>{{ $line['branch'] }}</td><td style="text-align:right">{{ $line['label'] }}</td>
                    <td>{{ number_format($line['quantity'], 3) }} {{ $line['unit'] }}</td>
                    <td>{{ number_format($line['reserved'], 3) }}</td><td>{{ $line['status'] }}</td>
                    <td>{{ $line['notes'] ?: ($line['request_notes'] ?: '—') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">لا توجد طلبات للتجهيز بهذا التاريخ.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($summary['undated_count'])
        <div class="print-soft-box" style="margin-top:10px;text-align:right">
            <strong>تنبيه: {{ $summary['undated_count'] }} طلب بلا تاريخ حاجة لم يدخل ضمن الإجماليات.</strong>
            @foreach([['title' => 'حلويات', 'items' => $undatedSweets], ['title' => 'كيك', 'items' => $undatedCakes]] as $section)
                @foreach($section['items'] as $request)
                    {{ $section['title'] }} — {{ $request->request_number }} ({{ $request->requestingLocation?->name ?? '—' }}) :
                    @foreach($request->items as $item)
                        {{ $section['title'] === 'حلويات' ? $item->displayProductName() : \App\Support\CakeProductionLabels::type($item->cake_type) }}
                        {{ number_format((float)$item->quantity, 3) }}{{ $section['title'] === 'حلويات' ? ' '.$item->requested_unit : ' حبة' }}
                        @unless($loop->last) ، @endunless
                    @endforeach ؛
                @endforeach
            @endforeach
        </div>
    @endif
    <div class="prep-print-hint">هذا التقرير للمتابعة والتحضير، وليس فاتورة بيع أو حركة مخزون. الجاهز معروض منفصلًا ولا يضاف إلى «المطلوب تحضيره».</div>
@endsection

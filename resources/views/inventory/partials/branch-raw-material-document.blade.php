@php
    $rawMaterialReport = $rawMaterialReport ?? app(\App\Services\Inventory\BranchRawMaterialReportService::class)
        ->forDay((int) $report->location_id, $report->business_date->toDateString());
@endphp
<section class="print-section" style="margin-top:12px">
    <div class="print-section-title">حركة المواد الخام للفرع (تسوية تشغيلية مستقلة عن المبيعات)</div>
    <table class="print-table" cellpadding="0" cellspacing="0">
        <thead><tr><th>طلبات خام</th><th>إرساليات</th><th>استلامات</th><th>قيمة المستلم</th><th>قيمة التالف</th><th>قيمة النقص</th></tr></thead>
        <tbody><tr>
            <td>{{ $rawMaterialReport['requests']->count() }}</td>
            <td>{{ $rawMaterialReport['totals']['requests_shipped'] }}</td>
            <td>{{ $rawMaterialReport['totals']['receipts'] }}</td>
            <td>{{ number_format($rawMaterialReport['totals']['received_value'], 2) }}</td>
            <td>{{ number_format($rawMaterialReport['totals']['damaged_value'], 2) }}</td>
            <td>{{ number_format($rawMaterialReport['totals']['shortage_value'], 2) }}</td>
        </tr></tbody>
    </table>
    <table class="print-table" cellpadding="0" cellspacing="0" style="margin-top:5px">
        <thead><tr><th>رقم التحويل</th><th>المادة الخام</th><th>المرسل</th><th>المستلم</th><th>التالف</th><th>النقص</th><th>القيمة</th></tr></thead>
        <tbody>
            @forelse($rawMaterialReport['receipts'] as $row)
                <tr>
                    <td>{{ $row['transfer']->transfer_number }}</td>
                    <td>{{ $row['product']?->name_ar ?: ($row['product']?->name ?? '—') }}</td>
                    <td>{{ number_format($row['sent'], 3) }}</td>
                    <td>{{ number_format($row['received'], 3) }}</td>
                    <td>{{ number_format($row['damaged'], 3) }}</td>
                    <td>{{ number_format($row['shortage'], 3) }}</td>
                    <td>{{ number_format($row['value'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">لا توجد استلامات مواد خام مسجلة بهذا التاريخ.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if(($rawMaterialReport['totals']['missing_cost_count'] ?? 0) > 0)
        <div class="print-soft-box" style="margin-top:5px">تنبيه: هناك {{ $rawMaterialReport['totals']['missing_cost_count'] }} بند مواد خام مستلمة بدون تكلفة وحدة موجبة، وإجمالي القيمة المعروض غير مكتمل.</div>
    @endif
    <div class="print-soft-box" style="margin-top:5px">هذه حركة مخزون داخلية بالتكلفة المرجعية المسجلة، وليست إيرادًا أو مصروفًا أو قيدًا في دفتر الأستاذ.</div>
</section>

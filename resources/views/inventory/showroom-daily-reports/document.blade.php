@php
    $items = $report->items;
    $totals = [
        'opening' => $items->sum(fn($i) => (float) $i->opening_kg),
        'trays' => $items->sum(fn($i) => (float) $i->received_trays),
        'received' => $items->sum(fn($i) => (float) $i->received_kg),
        'sales' => $items->sum(fn($i) => (float) $i->sales_kg),
        'sales_value' => $items->sum(fn($i) => $i->sales_value ?? 0),
        'damaged' => $items->sum(fn($i) => (float) $i->damaged_kg),
        'other' => $items->sum(fn($i) => (float) $i->other_net_kg),
        'actual' => $items->sum(fn($i) => (float) ($i->actual_closing_kg ?? 0)),
        'variance' => $items->sum(fn($i) => (float) ($i->variance_kg ?? 0)),
    ];
@endphp

@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('paper_orientation', 'landscape')
@section('document_title', 'كشف تفصيلي للحلويات')
@section('document_number', 'SDR-' . $report->id)
@section('document_date', $report->business_date->format('Y-m-d'))
@section('document_subtitle', $report->location->name)
@section(
    'document_meta',
    'الحالة: ' . match ($report->status) {
        'draft' => 'مسودة لدى الفرع',
        'submitted' => 'بانتظار تدقيق المحاسب العام',
        'approved', 'closed' => 'معتمد',
        default => $report->status,
    }
)
@section('signature_right', 'مسؤول المعرض')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .showroom-doc-summary{margin-bottom:9px;table-layout:fixed}
    .showroom-doc-summary td{padding:6px;border:1px solid #dfe4e9;background:#f8fafc;text-align:center}
    .showroom-doc-summary .showroom-label{display:block;color:#6b7280;font-size:7.2pt}
    .showroom-doc-summary .showroom-value{display:block;margin-top:2px;font-size:9pt}
    .showroom-doc-table th,.showroom-doc-table td{font-size:7.5pt!important;padding:3.5px!important}
    .variance-bad{color:#b42318;font-weight:bold}
    .showroom-accounting-doc{margin-top:9px;table-layout:fixed}
    .showroom-accounting-doc td{padding:5px;border:1px solid #dfe4e9;text-align:center}
    .showroom-accounting-doc .showroom-label{display:block;color:#6b7280;font-size:7.2pt}
    .showroom-accounting-doc .showroom-value{display:block;margin-top:2px;font-size:8pt}
</style>
@endpush

@section('print_content')
    <table class="showroom-doc-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="showroom-label">الرصيد السابق / كجم</div><div class="showroom-value" style="font-weight:bold">{{ number_format($totals['opening'], 3) }}</div></td>
            <td><div class="showroom-label">الوارد / كجم</div><div class="showroom-value" style="font-weight:bold">{{ number_format($totals['received'], 3) }}</div></td>
            <td><div class="showroom-label">المبيعات / كجم</div><div class="showroom-value" style="font-weight:bold">{{ number_format($totals['sales'], 3) }}</div></td>
            <td><div class="showroom-label">المتبقي الفعلي / كجم</div><div class="showroom-value" style="font-weight:bold">{{ number_format($totals['actual'], 3) }}</div></td>
        </tr>
    </table>

    <div class="print-section">
        <p style="font-size:8pt">قيمة المبيعات = المبيعات بالكيلو × سعر الكيلو المحفوظ، قبل الخصومات.
            @if($items->contains('selling_price_is_reference', true))
                أسعار التقارير السابقة مرجعية حسب تسعير الفرع وقت التحديث.
            @endif
            @if($items->contains(fn($item) => $item->selling_price_per_kg === null))
                الأصناف بلا سعر لا تدخل في إجمالي قيمة المبيعات.
            @endif
        </p>
        <table class="print-table showroom-doc-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>مسلسل</th>
                    <th>بيان المنتج</th>
                    <th>سابق / كجم</th>
                    <th>وارد سدر</th>
                    <th>وارد / كجم</th>
                    <th>مبيعات / كجم</th>
                    <th>سعر الكيلو</th>
                    <th>قيمة المبيعات</th>
                    <th>تالف / كجم</th>
                    <th>حركات أخرى صافي</th>
                    <th>المتبقي الفعلي</th>
                    <th>الفرق</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align:right">{{ $item->product_name }}</td>
                        <td>{{ number_format((float) $item->opening_kg, 3) }}</td>
                        <td>{{ number_format((float) $item->received_trays, 2) }}</td>
                        <td>{{ number_format((float) $item->received_kg, 3) }}</td>
                        <td>{{ number_format((float) $item->sales_kg, 3) }}</td>
                        <td>{{ $item->selling_price_per_kg === null ? '—' : number_format((float) $item->selling_price_per_kg, 2) }}{{ $item->selling_price_is_reference ? ' (مرجعي)' : '' }}</td>
                        <td>{{ $item->sales_value === null ? '—' : number_format($item->sales_value, 2) }}</td>
                        <td>{{ number_format((float) $item->damaged_kg, 3) }}</td>
                        <td>{{ number_format((float) $item->other_net_kg, 3) }}</td>
                        <td>{{ $item->actual_closing_kg === null ? '—' : number_format((float) $item->actual_closing_kg, 3) }}</td>
                        <td class="{{ abs((float) ($item->variance_kg ?? 0)) > 0.0005 ? 'variance-bad' : '' }}">
                            {{ $item->variance_kg === null ? '—' : number_format((float) $item->variance_kg, 3) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">الإجمالي</th>
                    <th>{{ number_format($totals['opening'], 3) }}</th>
                    <th>{{ number_format($totals['trays'], 2) }}</th>
                    <th>{{ number_format($totals['received'], 3) }}</th>
                    <th>{{ number_format($totals['sales'], 3) }}</th>
                    <th>—</th>
                    <th>{{ number_format($totals['sales_value'], 2) }}</th>
                    <th>{{ number_format($totals['damaged'], 3) }}</th>
                    <th>{{ number_format($totals['other'], 3) }}</th>
                    <th>{{ number_format($totals['actual'], 3) }}</th>
                    <th>{{ number_format($totals['variance'], 3) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <table class="showroom-accounting-doc" cellpadding="0" cellspacing="0">
        <tr>
            <td colspan="5" style="font-weight:bold;background:#f5edd8">الحركة المالية اليومية</td>
        </tr>
        <tr>
            <td><div class="showroom-label">المديونيات</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->debts_amount, 2) }}</div></td>
            <td><div class="showroom-label">باقي صواني / بكج مغلف</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->trays_packages_balance, 2) }}</div></td>
            <td><div class="showroom-label">تحليات الزبائن</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->customer_treats_amount, 2) }}</div></td>
            <td><div class="showroom-label">خصم الزبائن</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->customer_discount_amount, 2) }}</div></td>
            <td><div class="showroom-label">الدفع المسبق (حجوزات سابقة)</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->previous_prepaid_amount, 2) }}</div></td>
        </tr>
        <tr>
            <td colspan="4" style="text-align:right;font-weight:bold">الإجمالي</td>
            <td style="background:#fff8df"><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->accounting_total_primary, 2) }}</div></td>
        </tr>
    </table>

    <table class="showroom-accounting-doc" cellpadding="0" cellspacing="0" style="margin-top:8px">
        <tr>
            <td colspan="4" style="font-weight:bold;background:#eef7ee">الحجوزات والسداد</td>
        </tr>
        <tr>
            <td><div class="showroom-label">رصيد باكيج / أمس</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->previous_package_balance, 2) }}</div></td>
            <td><div class="showroom-label">الدفع الآجل / الحجوزات الآجلة</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->deferred_payment_amount, 2) }}</div></td>
            <td><div class="showroom-label">الحجوزات الخاصة / حلويات + كيك</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->special_reservations_amount, 2) }}</div></td>
            <td><div class="showroom-label">سداد المديونيات</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->debt_repayment_amount, 2) }}</div></td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:right;font-weight:bold">الإجمالي</td>
            <td style="background:#eef7ee"><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->accounting_total_secondary, 2) }}</div></td>
        </tr>
    </table>

    <table class="showroom-accounting-doc" cellpadding="0" cellspacing="0" style="margin-top:8px">
        <tr>
            <td colspan="4" style="font-weight:bold;background:#fff5d9">الإجمالي المالي للتقرير</td>
        </tr>
        <tr>
            <td><div class="showroom-label">إجمالي بيع التقرير</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->report_sales_total, 2) }}</div></td>
            <td><div class="showroom-label">إجمالي السداد والحجوزات</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->accounting_total_secondary, 2) }}</div></td>
            <td><div class="showroom-label">إجمالي الخصومات</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->customer_discount_amount, 2) }}</div></td>
            <td style="background:#fff5d9"><div class="showroom-label">الإجمالي المالي</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->financial_report_total, 2) }}</div></td>
        </tr>
    </table>

    <table class="showroom-accounting-doc" cellpadding="0" cellspacing="0" style="margin-top:8px">
        <tr>
            <td colspan="3" style="font-weight:bold;background:#eaf6ea">إجمالي العجز</td>
        </tr>
        <tr>
            <td><div class="showroom-label">إجمالي رصيد الكاشير</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->cashier_balance_total, 2) }}</div></td>
            <td><div class="showroom-label">الإجمالي المالي للتقرير</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->financial_report_total, 2) }}</div></td>
            <td style="background:#eaf6ea"><div class="showroom-label">إجمالي العجز</div><div class="showroom-value" style="font-weight:bold">{{ number_format((float) $report->net_booking_total, 2) }}</div></td>
        </tr>
    </table>

    @if($report->notes)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            <strong>ملاحظات:</strong>
            {{ $report->notes }}
        </div>
    @endif

    @if($report->submittedBy)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            أرسل التقرير من الفرع:
            {{ $report->submittedBy?->display_name ?? '—' }}
            ·
            {{ $report->submitted_at?->format('Y-m-d H:i') ?? '—' }}
        </div>
    @endif

    @if($report->reviewedBy)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            تدقيق المحاسب العام:
            {{ $report->reviewedBy?->display_name ?? '—' }}
            ·
            {{ $report->reviewed_at?->format('Y-m-d H:i') ?? '—' }}
            @if($report->review_notes)
                <br>
                ملاحظة: {{ $report->review_notes }}
            @endif
        </div>
    @endif
@include('inventory.partials.branch-raw-material-document')
@endsection



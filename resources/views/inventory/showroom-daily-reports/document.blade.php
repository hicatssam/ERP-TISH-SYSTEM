@php
    $items = $report->items;
    $totals = [
        'opening' => $items->sum(fn($i) => (float) $i->opening_kg),
        'trays' => $items->sum(fn($i) => (float) $i->received_trays),
        'received' => $items->sum(fn($i) => (float) $i->received_kg),
        'sales' => $items->sum(fn($i) => (float) $i->sales_kg),
        'takeaway' => $items->sum(fn($i) => (float) $i->takeaway_sales_kg),
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
    .showroom-doc-summary span{display:block;color:#6b7280;font-size:6.5pt}
    .showroom-doc-summary strong{display:block;margin-top:2px;font-size:9pt}
    .showroom-doc-table th,.showroom-doc-table td{font-size:6.2pt!important;padding:3.5px!important}
    .variance-bad{color:#b42318;font-weight:bold}
    .showroom-accounting-doc{margin-top:9px;table-layout:fixed}
    .showroom-accounting-doc td{padding:5px;border:1px solid #dfe4e9;text-align:center}
    .showroom-accounting-doc span{display:block;color:#6b7280;font-size:6.2pt}
    .showroom-accounting-doc strong{display:block;margin-top:2px;font-size:8pt}
</style>
@endpush

@section('print_content')
    <table class="showroom-doc-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td><span>الرصيد السابق / كجم</span><strong>{{ number_format($totals['opening'], 3) }}</strong></td>
            <td><span>الوارد / كجم</span><strong>{{ number_format($totals['received'], 3) }}</strong></td>
            <td><span>المبيعات / كجم</span><strong>{{ number_format($totals['sales'], 3) }}</strong></td>
            <td><span>Take Away / كجم</span><strong>{{ number_format($totals['takeaway'], 3) }}</strong></td>
            <td><span>المتبقي الفعلي / كجم</span><strong>{{ number_format($totals['actual'], 3) }}</strong></td>
        </tr>
    </table>

    <div class="print-section">
        <table class="print-table showroom-doc-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>مسلسل</th>
                    <th>بيان المنتج</th>
                    <th>سابق / كجم</th>
                    <th>وارد سدر</th>
                    <th>وارد / كجم</th>
                    <th>مبيعات / كجم</th>
                    <th>Take Away / كجم</th>
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
                        <td>{{ number_format((float) $item->takeaway_sales_kg, 3) }}</td>
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
                    <th>{{ number_format($totals['takeaway'], 3) }}</th>
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
            <td><span>المديونيات</span><strong>{{ number_format((float) $report->debts_amount, 2) }}</strong></td>
            <td><span>باقي صواني / بكج مغلف</span><strong>{{ number_format((float) $report->trays_packages_balance, 2) }}</strong></td>
            <td><span>تحليات الزبائن</span><strong>{{ number_format((float) $report->customer_treats_amount, 2) }}</strong></td>
            <td><span>خصم الزبائن</span><strong>{{ number_format((float) $report->customer_discount_amount, 2) }}</strong></td>
            <td><span>الدفع المسبق (حجوزات سابقة)</span><strong>{{ number_format((float) $report->previous_prepaid_amount, 2) }}</strong></td>
        </tr>
        <tr>
            <td colspan="4" style="text-align:right;font-weight:bold">الإجمالي</td>
            <td style="background:#fff8df"><strong>{{ number_format((float) $report->accounting_total_primary, 2) }}</strong></td>
        </tr>
    </table>

    <table class="showroom-accounting-doc" cellpadding="0" cellspacing="0" style="margin-top:8px">
        <tr>
            <td colspan="3" style="font-weight:bold;background:#eef7ee">الحجوزات والسداد</td>
        </tr>
        <tr>
            <td><span>الدفع الآجل / الحجوزات الآجلة</span><strong>{{ number_format((float) $report->deferred_payment_amount, 2) }}</strong></td>
            <td><span>الحجوزات الخاصة / حلويات + كيك</span><strong>{{ number_format((float) $report->special_reservations_amount, 2) }}</strong></td>
            <td><span>سداد المديونيات</span><strong>{{ number_format((float) $report->debt_repayment_amount, 2) }}</strong></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:right;font-weight:bold">الإجمالي</td>
            <td style="background:#eef7ee"><strong>{{ number_format((float) $report->accounting_total_secondary, 2) }}</strong></td>
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
@endsection

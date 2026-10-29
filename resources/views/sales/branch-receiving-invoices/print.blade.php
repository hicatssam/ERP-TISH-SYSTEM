@php
    $receivingTheme = app(\App\Services\PrintThemeService::class)->settings();
@endphp

@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', 'فاتورة استلام فرع')
@section('document_number', $invoice->invoice_number)
@section('document_date', $invoice->issued_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'))
@section('document_subtitle', ($invoice->sendingLocation?->name ?? '—') . ' ← ' . ($invoice->receivingLocation?->name ?? '—'))
@section('document_meta', $sourceLabel . ': ' . $sourceNumber)
@section('signature_right', 'توقيع المستلم')
@section('signature_left', 'توقيع الموصّل')

@push('print_styles')
<style>
    .receiving-info{width:100%;margin:12px 0;table-layout:fixed;border-spacing:5px 0}
    .receiving-info>tbody>tr>td{width:50%;padding:9px;vertical-align:top;background:#f8fafc}
    .receiving-info-label{display:block;margin-bottom:4px;color:{{ $receivingTheme['primary_color'] }};font-size:8pt;font-weight:800}
    .receiving-info-value{color:{{ $receivingTheme['secondary_color'] }};font-size:9pt;font-weight:800}
    .receiving-summary{width:100%;margin:0 0 12px;table-layout:fixed;border-spacing:5px 0}
    .receiving-summary td{width:33.33%;padding:8px;background:#f8fafc;text-align:center}
    .receiving-summary span{display:block;color:#6b7280;font-size:7.5pt}
    .receiving-summary strong{display:block;margin-top:3px;color:{{ $receivingTheme['secondary_color'] }};font-size:11pt}
    .receiving-difference{color:#b42318;font-weight:800}
    .receiving-match{color:#08783e;font-weight:800}
</style>
@endpush

@section('print_content')
@if($invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT)
<div class="print-soft-box" style="margin-bottom:9px;text-align:right"><strong>أُغلقت الطلبية بنقص — السبب:</strong> {{ $invoice->shipment->closure_reason }} · بواسطة {{ $invoice->shipment->closedBy?->display_name ?? '—' }} · {{ $invoice->shipment->closed_at?->format('Y-m-d H:i') }}</div>
@endif
<table class="receiving-info" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <span class="receiving-info-label">موقع الإرسال</span>
            <span class="receiving-info-value">{{ $invoice->sendingLocation?->name ?? '—' }}</span>
        </td>
        <td>
            <span class="receiving-info-label">موقع الاستلام</span>
            <span class="receiving-info-value">{{ $invoice->receivingLocation?->name ?? '—' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="receiving-info-label">الطلب الأصلي</span>
            <span class="receiving-info-value">{{ $sourceLabel }} — {{ $sourceNumber }}</span>
        </td>
        <td>
            <span class="receiving-info-label">الموصّل</span>
            <span class="receiving-info-value">{{ $invoice->driver?->display_name ?? '—' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="receiving-info-label">المستلم في الفرع</span>
            <span class="receiving-info-value">{{ $invoice->receivedBy?->display_name ?? '—' }}</span>
        </td>
        <td>
            <span class="receiving-info-label">وقت الاستلام</span>
            <span class="receiving-info-value">{{ $invoice->issued_at?->format('Y-m-d H:i') ?? '—' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="receiving-info-label">وقت التحميل المخطط</span>
            <span class="receiving-info-value">{{ $invoice->shipment?->planned_loading_at?->format('Y-m-d H:i') ?? '—' }}</span>
        </td>
        <td>
            <span class="receiving-info-label">وقت الاستلام المخطط</span>
            <span class="receiving-info-value">{{ $invoice->shipment?->planned_receiving_at?->format('Y-m-d H:i') ?? '—' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="receiving-info-label">{{ $invoice->shipment?->multi_trip_enabled ? 'آخر تحميل فعلي' : 'التحميل الفعلي' }}</span>
            <span class="receiving-info-value">{{ $invoice->shipment?->loaded_at?->format('Y-m-d H:i') ?? '—' }}</span>
        </td>
        <td>
            <span class="receiving-info-label">الاستلام الفعلي</span>
            <span class="receiving-info-value">{{ $invoice->shipment?->received_at?->format('Y-m-d H:i') ?? '—' }}</span>
        </td>
    </tr>
</table>

<table class="receiving-summary" cellpadding="0" cellspacing="0">
    <tr>
        <td><span>{{ $invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'إجمالي المطلوب' : 'إجمالي المرسل' }}</span><strong>{{ number_format((float)$invoice->total_items_sent, 3) }}</strong></td>
        <td><span>إجمالي المستلم</span><strong>{{ number_format((float)$invoice->total_items_received, 3) }}</strong></td>
        <td><span>الفرق</span><strong>{{ number_format((float)$invoice->total_difference, 3) }}</strong></td>
    </tr>
</table>

<div class="print-section">
    <div class="print-section-title">تفاصيل الطلبية</div>
    <table class="print-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>#</th><th>الصنف</th><th>الوحدة</th>
                <th>{{ $invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'المطلوب' : 'المرسل' }}</th><th>المستلم</th><th>الفرق</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                @php $difference = (float)($item->difference_quantity ?? 0); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->displayDescription() }}</td>
                    <td>{{ $item->displayUnit() ?? '—' }}</td>
                    <td>{{ number_format((float)$item->sent_quantity, 3) }}</td>
                    <td>{{ number_format((float)($item->received_quantity ?? 0), 3) }}</td>
                    <td class="{{ abs($difference) < .0005 ? 'receiving-match' : 'receiving-difference' }}">
                        {{ abs($difference) < .0005 ? 'مطابق' : number_format($difference, 3) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="print-empty">لا توجد بنود مسجلة.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($invoice->notes)
    <div class="print-soft-box"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
@endif
@endsection

@push('print_scripts')
@unless($pdfMode ?? false)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endunless
@endpush

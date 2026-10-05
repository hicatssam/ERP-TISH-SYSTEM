@extends('layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', $voucher->type === 'receipt' ? 'سند قبض' : 'سند صرف')
@section('document_number', $voucher->number)
@section('document_date', $voucher->voucher_date?->format('Y-m-d') ?? now()->format('Y-m-d'))
@section('document_subtitle', ($voucher->location?->name ?? '—') . ' · ' . ($voucher->party_name ?? '—'))
@section('signature_right', $voucher->type === 'receipt' ? 'توقيع المستلم' : 'توقيع المستفيد')
@section('signature_left', 'اعتماد المالية')

@php
    $statusLabels = [
        'draft' => 'مسودة',
        'posted' => 'مرحّل',
        'reversed' => 'معكوس',
        'cancelled' => 'ملغى',
    ];
    $currency = $voucher->currency?->symbol ?: ($voucher->currency?->code ?? '');
@endphp

@section('print_content')
    <section class="print-section">
        <table class="print-summary" cellpadding="0" cellspacing="0">
            <tr>
                <td><div class="print-summary-label">الطرف</div><strong class="print-summary-value">{{ $voucher->party_name }}</strong></td>
                <td><div class="print-summary-label">المبلغ</div><strong class="print-summary-value">{{ number_format((float) $voucher->amount, 2) }} {{ $currency }}</strong></td>
                <td><div class="print-summary-label">الحالة</div><strong class="print-summary-value">{{ $statusLabels[$voucher->status] ?? $voucher->status }}</strong></td>
                <td><div class="print-summary-label">طريقة الدفع</div><strong class="print-summary-value">{{ $voucher->paymentMethod?->name_ar ?: $voucher->paymentMethod?->name ?: '—' }}</strong></td>
            </tr>
        </table>
    </section>

    <section class="print-section">
        <div class="print-section-title">تفاصيل السند</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr><th style="width:28%">المرجع الخارجي</th><td>{{ $voucher->external_reference ?: '—' }}</td></tr>
                <tr><th>حساب الخزينة</th><td>{{ $voucher->treasuryAccount?->code }} · {{ $voucher->treasuryAccount?->name }}</td></tr>
                <tr><th>الحساب المقابل</th><td>{{ $voucher->counterAccount?->code }} · {{ $voucher->counterAccount?->name }}</td></tr>
                <tr><th>البيان</th><td>{{ $voucher->description ?: '—' }}</td></tr>
                <tr><th>أنشأه</th><td>{{ $voucher->creator?->display_name ?? '—' }}</td></tr>
                <tr><th>رحّله</th><td>{{ $voucher->poster?->display_name ?? '—' }} @if($voucher->posted_at) · {{ $voucher->posted_at->format('Y-m-d H:i') }} @endif</td></tr>
            </tbody>
        </table>
    </section>

    @if($voucher->journal)
        <section class="print-section">
            <div class="print-section-title">القيد المحاسبي · {{ $voucher->journal->number }}</div>
            <table class="print-table" cellpadding="0" cellspacing="0">
                <thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead>
                <tbody>
                    @foreach($voucher->journal->lines as $line)
                        <tr>
                            <td>{{ $line->account?->code }} · {{ $line->account?->name }}</td>
                            <td>{{ number_format((float) $line->debit, 2) }}</td>
                            <td>{{ number_format((float) $line->credit, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr><th>الإجمالي</th><th>{{ number_format((float) $voucher->journal->total_debit, 2) }}</th><th>{{ number_format((float) $voucher->journal->total_credit, 2) }}</th></tr>
                </tbody>
            </table>
        </section>
    @endif
@endsection

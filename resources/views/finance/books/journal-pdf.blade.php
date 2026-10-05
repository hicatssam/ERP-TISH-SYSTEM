@extends('layouts.pdf')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', 'قيد محاسبي')
@section('document_number', $journal->number)
@section('document_date', $journal->entry_date?->format('Y-m-d') ?? now()->format('Y-m-d'))
@section('document_subtitle', ($journal->location?->name ?? '—') . ' · ' . ($journal->period?->name ?? '—'))
@section('signature_right', 'إعداد القيد')
@section('signature_left', 'اعتماد المالية')

@php
    $kindLabels = [
        'manual' => 'تسوية يدوية',
        'receipt' => 'سند قبض',
        'payment' => 'سند صرف',
        'reversal' => 'قيد عكسي',
    ];
@endphp

@section('print_content')
    <section class="print-section">
        <table class="print-summary" cellpadding="0" cellspacing="0">
            <tr>
                <td><div class="print-summary-label">النوع</div><strong class="print-summary-value">{{ $kindLabels[$journal->kind] ?? $journal->kind }}</strong></td>
                <td><div class="print-summary-label">العملة</div><strong class="print-summary-value">{{ $journal->currency_code }}</strong></td>
                <td><div class="print-summary-label">إجمالي المدين</div><strong class="print-summary-value">{{ number_format((float) $journal->total_debit, 2) }}</strong></td>
                <td><div class="print-summary-label">إجمالي الدائن</div><strong class="print-summary-value">{{ number_format((float) $journal->total_credit, 2) }}</strong></td>
            </tr>
        </table>
    </section>

    <section class="print-section">
        <div class="print-section-title">{{ $journal->description }}</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead><tr><th>رمز الحساب</th><th>الحساب</th><th>شرح السطر</th><th>مدين</th><th>دائن</th></tr></thead>
            <tbody>
                @foreach($journal->lines as $line)
                    <tr>
                        <td>{{ $line->account?->code }}</td>
                        <td>{{ $line->account?->name }}</td>
                        <td>{{ $line->memo ?? '—' }}</td>
                        <td>{{ number_format((float) $line->debit, 2) }}</td>
                        <td>{{ number_format((float) $line->credit, 2) }}</td>
                    </tr>
                @endforeach
                <tr><th colspan="3">الإجمالي</th><th>{{ number_format((float) $journal->total_debit, 2) }}</th><th>{{ number_format((float) $journal->total_credit, 2) }}</th></tr>
            </tbody>
        </table>
    </section>

    <section class="print-section">
        <table class="print-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr><th style="width:28%">سجله</th><td>{{ $journal->creator?->display_name ?? '—' }}</td></tr>
                <tr><th>وقت الترحيل</th><td>{{ $journal->posted_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                @if($journal->original)<tr><th>يعكس القيد</th><td>{{ $journal->original->number }}</td></tr>@endif
                @if($journal->reversal)<tr><th>تم عكسه عبر</th><td>{{ $journal->reversal->number }}</td></tr>@endif
            </tbody>
        </table>
    </section>
@endsection

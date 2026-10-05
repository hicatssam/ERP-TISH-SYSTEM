@extends('layouts.pdf')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('paper_orientation', 'landscape')
@section('document_title', 'سجل الترحيلات المالية')
@section('document_number', '')
@section('document_date', now()->format('Y-m-d H:i'))
@section('document_subtitle', 'الفترة: ' . $from . ' — ' . $to)
@section('document_meta', 'عدد الحركات: ' . number_format($total))
@section('signature_right', 'إعداد التقرير')
@section('signature_left', 'اعتماد الإدارة')

@section('print_content')
    @if($summary->isNotEmpty())
        <section class="print-section">
            <div class="print-section-title">ملخص الفترة</div>
            <table class="print-table" cellpadding="0" cellspacing="0">
                <thead><tr><th>نوع الحركة</th><th>العملة</th><th>عدد الحركات</th><th>الإجمالي</th></tr></thead>
                <tbody>
                    @foreach($summary as $item)
                        <tr>
                            <td>{{ $item->entry_type?->label() ?? '—' }}</td>
                            <td>{{ $item->currency_code }}</td>
                            <td>{{ number_format((int) $item->entries_count) }}</td>
                            <td>{{ number_format((float) $item->amount_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="print-section">
        <div class="print-section-title">الحركات</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr><th>التاريخ</th><th>الفرع</th><th>النوع</th><th>البيان</th><th>المصدر</th><th>الفترة</th><th>المبلغ</th><th>سجّلها</th></tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $entry->location?->name ?? '—' }}</td>
                        <td>{{ $entry->entry_type?->label() ?? '—' }}</td>
                        <td>{{ $entry->description ?: '—' }}</td>
                        <td>{{ $entry->reference_type ? $entry->reference_type . ' #' . $entry->reference_id : '—' }}</td>
                        <td>{{ $entry->period ? $entry->period->year . '/' . str_pad($entry->period->month, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                        <td>{{ number_format((float) $entry->amount, 2) }} {{ $entry->currency_code }}</td>
                        <td>{{ $entry->createdBy?->display_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="print-empty">لا توجد حركات في هذه الفترة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if($truncated)
        <div class="print-soft-box" style="margin-top:10px">
            يعرض ملف PDF أول {{ number_format($cap) }} حركة من أصل {{ number_format($total) }} حركة. قلّل نطاق التاريخ للحصول على تقرير كامل.
        </div>
    @endif
@endsection

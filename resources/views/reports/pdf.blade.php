@php
    $reportPrintTheme = app(\App\Services\PrintThemeService::class)->settings();
    $summaryRows = collect($summary ?? []);
    $formattedRows = collect($formattedRows ?? []);
    $dateFromFormatted = !empty($dateFrom)
        ? \App\Support\ArabicDate::date($dateFrom, false)
        : '—';
    $dateToFormatted = !empty($dateTo)
        ? \App\Support\ArabicDate::date($dateTo, false)
        : '—';
@endphp

@extends('layouts.pdf')

@section('paper_orientation', 'landscape')
@section('document_title', $title ?? 'تقرير')
@section('document_number', '')
@section('document_date', \App\Support\ArabicDate::dateTime(now(), false))
@section('document_subtitle', 'الفترة: ' . $dateFromFormatted . ' — ' . $dateToFormatted . (($type ?? '') === 'cake-production' ? (' | ' . (($cakeMode ?? 'all') === 'plan' ? 'خطة العمل المفتوحة' : 'سجل الطلبات الكامل')) : ''))
@section('document_meta', 'عدد السجلات: ' . number_format((int) ($total ?? $formattedRows->count())))
@section('signature_right', 'إعداد التقرير')
@section('signature_left', 'اعتماد الإدارة')

@push('print_styles')
<style>
    .report-summary-table {
        width: 100%;
        margin-bottom: 8px;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .report-summary-table td {
        width: 25%;
        padding: 7px 6px;
        border: 1px solid #dfe4e9;
        background: #f8fafc;
        text-align: center;
        vertical-align: middle;
    }

    .report-summary-label {
        color: #6b7280;
        font-size: 6.6pt;
    }

    .report-summary-value {
        margin-top: 2px;
        color: {{ $reportPrintTheme['secondary_color'] ?? '#111827' }};
        font-size: 9.5pt;
        font-weight: bold;
    }

    .report-warning {
        margin-top: 8px;
        padding: 7px;
        border: 1px solid #e4c379;
        background: #fff9e8;
        color: #805410;
        font-size: 6.8pt;
    }
</style>
@endpush

@section('print_content')
    @if($summaryRows->isNotEmpty())
        <div class="print-section">
            @foreach($summaryRows->chunk(4) as $summaryChunk)
                <table class="report-summary-table" cellpadding="0" cellspacing="0">
                    <tr>
                        @foreach($summaryChunk as $label => $value)
                            <td>
                                <div class="report-summary-label">{{ $label }}</div>
                                <div class="report-summary-value">{{ $value }}</div>
                            </td>
                        @endforeach

                        @for($i = $summaryChunk->count(); $i < 4; $i++)
                            <td>&nbsp;</td>
                        @endfor
                    </tr>
                </table>
            @endforeach
        </div>
    @endif

    @if($type === 'cake-production')
        <div class="print-section" style="font-size:9pt;color:#374151;margin:6px 0 12px">
            {{ ($cakeMode ?? 'all') === 'plan' ? 'خطة العمل المفتوحة: الطلبات الجارية وليست رصيد القطع غير المصنعة.' : 'جميع الطلبات خلال الفترة، بما فيها الطلبات الجاهزة والمكتملة.' }}
            المحجوز ضمن كميات الفروع، والطلبات الخاصة متعددة الطبقات تُحسب طلبًا واحدًا. القيم غير المحددة تتطلب مراجعة.
        </div>
    @endif

    <div class="print-section">
        @if($formattedRows->isNotEmpty())
            @if($type === 'cake-production')
                @include('reports.partials.cake-production-groups', ['cakeRows' => $rows, 'cakeMode' => $cakeMode ?? 'all', 'cakePrint' => true])
            @else
            <table class="print-table" cellpadding="0" cellspacing="0">
                @if(!empty($columns))
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody>
                    @foreach($formattedRows as $row)
                        <tr>
                            @foreach($row as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @else
            <div class="print-soft-box print-empty">لا توجد بيانات للفترة المحددة.</div>
        @endif
    </div>

    @if($truncated ?? false)
        <div class="report-warning">
            <strong>تنبيه:</strong>
            يعرض التقرير أول {{ number_format((int) ($cap ?? 0)) }}
            سجل فقط من أصل {{ number_format((int) ($total ?? 0)) }} سجل.
            للحصول على كامل البيانات استخدم تصدير Excel أو قلّل نطاق التاريخ.
        </div>
    @endif



@endsection

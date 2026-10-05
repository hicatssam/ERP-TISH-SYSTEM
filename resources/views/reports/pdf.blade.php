@php
    $printTheme = app(\App\Services\PrintThemeService::class)->settings();
    $printedAt = now();
    $summaryRows = collect($summary ?? []);
    $formattedRows = collect($formattedRows ?? []);
    $columns = $columns ?? [];
    $currencyCode = $printTheme['currency_code'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'تقرير مالي' }}</title>
    <style>
        @page { margin: 10mm; }
        body {
            direction: rtl;
            font-family: dejavusans;
            font-size: 9pt;
            color: {{ $printTheme['text_color'] ?? '#1f2937' }};
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .header {
            border-bottom: 2px solid {{ $printTheme['primary_color'] ?? '#d6a925' }};
            margin-bottom: 10px;
        }
        .header td { vertical-align: top; padding: 0 0 8px 0; }
        .brand { width: 33%; text-align: right; }
        .title { width: 34%; text-align: center; }
        .meta { width: 33%; text-align: left; }
        .logo {
            max-width: {{ (int) ($printTheme['logo_size'] ?? 90) }}px;
            max-height: 58px;
        }
        .business-name {
            font-size: 12pt;
            font-weight: bold;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
            margin-top: 3px;
        }
        .business-sub {
            font-size: 7pt;
            color: #6b7280;
            line-height: 1.5;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: bold;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
            margin-top: 6px;
        }
        .doc-subtitle {
            margin-top: 4px;
            font-size: 8pt;
            color: #6b7280;
        }
        .meta-line {
            font-size: 7.5pt;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .summary {
            margin: 6px 0 10px 0;
        }
        .summary td {
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            padding: 7px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .summary-label {
            font-size: 7pt;
            color: #6b7280;
        }
        .summary-value {
            font-size: 10pt;
            font-weight: bold;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
            margin-top: 2px;
        }
        .report-table {
            margin-top: 4px;
        }
        .report-table th {
            background: {{ $printTheme['secondary_color'] ?? '#111827' }};
            color: #ffffff;
            border: 1px solid {{ $printTheme['secondary_color'] ?? '#111827' }};
            padding: 6px 5px;
            font-size: 7.2pt;
            text-align: center;
            font-weight: bold;
        }
        .report-table td {
            border: 1px solid #dfe4e9;
            padding: 5px 4px;
            font-size: 7pt;
            text-align: center;
            vertical-align: middle;
        }
        .report-table tr:nth-child(even) td {
            background: #fafafa;
        }
        .empty {
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            text-align: center;
            padding: 14px;
            color: #6b7280;
        }
        .warning {
            margin-top: 8px;
            border: 1px solid #e4c379;
            background: #fff9e8;
            color: #805410;
            padding: 7px;
            font-size: 7pt;
        }
        .signatures {
            margin-top: 18px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 18px;
        }
        .signature-image {
            max-height: 42px;
            max-width: 110px;
            margin-bottom: 4px;
        }
        .signature-line {
            border-top: 1px solid #9ca3af;
            margin: 0 auto 4px;
            width: 75%;
        }
        .signature-label {
            font-size: 7pt;
            color: #6b7280;
        }
        .stamp {
            text-align: center;
            margin-top: 8px;
        }
        .stamp img {
            max-width: 85px;
            max-height: 85px;
        }
        .footer {
            margin-top: 12px;
            border-top: 1px solid {{ $printTheme['primary_color'] ?? '#d6a925' }};
        }
        .footer td {
            padding-top: 5px;
            font-size: 6.5pt;
            color: #7a8490;
        }
    </style>
</head>
<body>
    <table class="header" cellpadding="0" cellspacing="0">
        <tr>
            <td class="brand">
                @if(!empty($printTheme['show_logo']) && !empty($printTheme['logo_src']))
                    <img class="logo" src="{{ $printTheme['logo_src'] }}" alt="">
                @endif

                @if(!empty($printTheme['show_business_info']))
                    <div class="business-name">{{ $printTheme['business_name'] ?? config('app.name') }}</div>

                    @if(!empty($printTheme['business_name_en']))
                        <div class="business-sub" dir="ltr">{{ $printTheme['business_name_en'] }}</div>
                    @endif

                    @if(!empty($printTheme['business_phone']))
                        <div class="business-sub" dir="ltr">{{ $printTheme['business_phone'] }}</div>
                    @endif

                    @if(!empty($printTheme['business_address']))
                        <div class="business-sub">{{ $printTheme['business_address'] }}</div>
                    @endif
                @endif
            </td>

            <td class="title">
                <div class="doc-title">{{ $title ?? 'تقرير مالي' }}</div>
                <div class="doc-subtitle">
                    {{ \App\Support\ArabicDate::date($dateFrom, false) }}
                    —
                    {{ \App\Support\ArabicDate::date($dateTo, false) }}
                </div>
            </td>

            <td class="meta">
                <div class="meta-line">تاريخ الطباعة: {{ \App\Support\ArabicDate::dateTime($printedAt, false) }}</div>
                <div class="meta-line">عدد السجلات: {{ number_format((int) ($total ?? $formattedRows->count())) }}</div>
                @if($currencyCode !== '')
                    <div class="meta-line">العملة: {{ $currencyCode }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if($summaryRows->isNotEmpty())
        @foreach($summaryRows->chunk(4) as $chunk)
            <table class="summary" cellpadding="0" cellspacing="0">
                <tr>
                    @foreach($chunk as $label => $value)
                        <td>
                            <div class="summary-label">{{ $label }}</div>
                            <div class="summary-value">{{ $value }}</div>
                        </td>
                    @endforeach
                    @for($i = $chunk->count(); $i < 4; $i++)
                        <td>&nbsp;</td>
                    @endfor
                </tr>
            </table>
        @endforeach
    @endif

    @if($formattedRows->isNotEmpty())
        <table class="report-table" cellpadding="0" cellspacing="0">
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
    @else
        <div class="empty">لا توجد بيانات للفترة المحددة.</div>
    @endif

    @if($truncated ?? false)
        <div class="warning">
            يعرض التقرير أول {{ number_format((int) ($cap ?? 0)) }}
            سجل فقط من أصل {{ number_format((int) ($total ?? 0)) }} سجل.
        </div>
    @endif

    @if(!empty($printTheme['show_signatures']))
        <table class="signatures" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div style="height:42px"></div>
                    <div class="signature-line"></div>
                    <div class="signature-label">إعداد التقرير</div>
                </td>
                <td>
                    @if(!empty($printTheme['signature_src']))
                        <img class="signature-image" src="{{ $printTheme['signature_src'] }}" alt="">
                    @else
                        <div style="height:42px"></div>
                    @endif
                    <div class="signature-line"></div>
                    <div class="signature-label">اعتماد الإدارة</div>
                </td>
            </tr>
        </table>
    @endif

    @if(!empty($printTheme['show_stamp']) && !empty($printTheme['stamp_src']))
        <div class="stamp">
            <img src="{{ $printTheme['stamp_src'] }}" alt="">
        </div>
    @endif

    @if(!empty($printTheme['show_footer']))
        <table class="footer" cellpadding="0" cellspacing="0">
            <tr>
                <td style="text-align:right">
                    {{ $printTheme['business_name'] ?? config('app.name') }}
                    @if(!empty($printTheme['footer_text']))
                        · {{ $printTheme['footer_text'] }}
                    @endif
                </td>
                <td style="text-align:left" dir="ltr">{{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    @endif
</body>
</html>

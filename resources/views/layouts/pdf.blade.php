@php
    $printService = app(\App\Services\PrintThemeService::class);
    $printTheme = array_replace(
        $printService->settings(),
        array_intersect_key(
            $printThemeOverrides ?? [],
            array_flip(['business_name', 'business_name_en', 'logo_src'])
        )
    );

    $documentTitle = trim($__env->yieldContent('document_title', 'مستند'));
    $documentNumber = trim($__env->yieldContent('document_number', ''));
    $documentDate = trim($__env->yieldContent('document_date', ''));
    $documentSubtitle = trim($__env->yieldContent('document_subtitle', ''));
    $documentMeta = trim($__env->yieldContent('document_meta', ''));
    $orientation = trim($__env->yieldContent('paper_orientation', 'portrait')) === 'landscape'
        ? 'landscape'
        : 'portrait';

    $showSignatures = trim($__env->yieldContent(
        'show_signatures',
        !empty($printTheme['show_signatures']) ? '1' : '0'
    )) === '1';

    $showStamp = trim($__env->yieldContent(
        'show_stamp',
        !empty($printTheme['show_stamp']) ? '1' : '0'
    )) === '1';

    $signatureRight = trim($__env->yieldContent('signature_right', 'توقيع المستلم'));
    $signatureLeft = trim($__env->yieldContent('signature_left', 'اعتماد الإدارة'));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>
        @page {
            size: A4 {{ $orientation }};
            margin: 10mm;
        }

        body {
            direction: rtl;
            margin: 0;
            padding: 0;
            font-family: dejavusans;
            font-size: 8.5pt;
            color: {{ $printTheme['text_color'] ?? '#1f2937' }};
            background: #ffffff;
        }

        * { box-sizing: border-box; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header {
            margin-bottom: 10px;
            border-bottom: 2px solid {{ $printTheme['primary_color'] ?? '#d6a925' }};
        }

        .pdf-header td {
            padding: 0 0 8px;
            vertical-align: top;
        }

        .pdf-brand-cell { width: 33%; text-align: right; }
        .pdf-title-cell { width: 34%; text-align: center; }
        .pdf-meta-cell { width: 33%; text-align: left; }

        .print-logo {
            max-width: {{ (int) ($printTheme['logo_size'] ?? 90) }}px;
            max-height: 58px;
        }

        .print-business-name {
            margin-top: 3px;
            font-size: 11pt;
            font-weight: bold;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
        }

        .print-business-name-en,
        .print-business-tagline,
        .print-business-info,
        .pdf-meta-row,
        .pdf-subtitle {
            color: #6b7280;
            font-size: 6.8pt;
            line-height: 1.5;
        }

        .pdf-document-title {
            margin-top: 5px;
            font-size: 15pt;
            font-weight: bold;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
        }

        .pdf-subtitle { margin-top: 4px; }
        .pdf-meta-row { margin-bottom: 2px; }

        .print-section {
            margin-top: 9px;
            page-break-inside: auto;
        }

        .print-section-title {
            margin-bottom: 5px;
            padding: 5px 7px;
            border-right: 3px solid {{ $printTheme['primary_color'] ?? '#d6a925' }};
            background: #f8fafc;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
            font-size: 8pt;
            font-weight: bold;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
        }

        .print-table th {
            padding: 5px 4px;
            border: 1px solid {{ $printTheme['secondary_color'] ?? '#111827' }};
            background: {{ $printTheme['secondary_color'] ?? '#111827' }};
            color: #ffffff;
            text-align: center;
            font-size: 6.8pt;
            font-weight: bold;
        }

        .print-table td {
            padding: 4px;
            border: 1px solid #dfe4e9;
            text-align: center;
            vertical-align: middle;
            font-size: 6.7pt;
        }

        .print-summary {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .print-summary td {
            padding: 7px 6px;
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            text-align: center;
            vertical-align: middle;
        }

        .print-summary-label {
            color: #6b7280;
            font-size: 6.6pt;
        }

        .print-summary-value {
            display: block;
            margin-top: 2px;
            color: {{ $printTheme['secondary_color'] ?? '#111827' }};
            font-size: 9.5pt;
            font-weight: bold;
        }

        .print-soft-box,
        .print-empty {
            padding: 8px;
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            color: #6b7280;
            text-align: center;
            font-size: 7pt;
        }

        .pdf-signatures {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .pdf-signatures td {
            width: 50%;
            padding: 0 18px;
            text-align: center;
            vertical-align: bottom;
        }

        .pdf-signature-image {
            max-width: 110px;
            max-height: 42px;
            margin-bottom: 4px;
        }

        .pdf-signature-space { height: 42px; }

        .pdf-signature-line {
            width: 75%;
            margin: 0 auto 4px;
            border-top: 1px solid #9ca3af;
        }

        .pdf-signature-label {
            color: #6b7280;
            font-size: 6.8pt;
        }

        .pdf-stamp {
            margin-top: 7px;
            text-align: center;
            page-break-inside: avoid;
        }

        .pdf-stamp img {
            max-width: 85px;
            max-height: 85px;
        }

        .pdf-footer {
            margin-top: 12px;
            border-top: 1px solid {{ $printTheme['primary_color'] ?? '#d6a925' }};
            page-break-inside: avoid;
        }

        .pdf-footer td {
            padding-top: 5px;
            color: #7a8490;
            font-size: 6.2pt;
        }
    </style>

    @stack('print_styles')
</head>
<body>
    <table class="pdf-header" cellpadding="0" cellspacing="0">
        <tr>
            <td class="pdf-brand-cell">
                @if(!empty($printTheme['show_logo']) && !empty($printTheme['logo_src']))
                    <img
                        src="{{ $printTheme['logo_src'] }}"
                        class="print-logo"
                        alt="{{ $printTheme['business_name'] ?? '' }}"
                    >
                @endif

                @if(!empty($printTheme['show_business_info']))
                    <div class="print-business-name">{{ $printTheme['business_name'] ?? config('app.name') }}</div>

                    @if(!empty($printTheme['business_name_en']))
                        <div class="print-business-name-en" dir="ltr">{{ $printTheme['business_name_en'] }}</div>
                    @endif

                    @if(!empty($printTheme['business_tagline']))
                        <div class="print-business-tagline">{{ $printTheme['business_tagline'] }}</div>
                    @endif

                    <div class="print-business-info">
                        @if(!empty($printTheme['business_phone']))
                            <div dir="ltr">{{ $printTheme['business_phone'] }}</div>
                        @endif
                        @if(!empty($printTheme['business_address']))
                            <div>{{ $printTheme['business_address'] }}</div>
                        @endif
                    </div>
                @endif
            </td>

            <td class="pdf-title-cell">
                <div class="pdf-document-title">{{ $documentTitle }}</div>
                @if($documentSubtitle !== '')
                    <div class="pdf-subtitle">{{ $documentSubtitle }}</div>
                @endif
            </td>

            <td class="pdf-meta-cell">
                @if($documentNumber !== '' && !empty($printTheme['show_document_number']))
                    <div class="pdf-meta-row">رقم المستند: <strong dir="ltr">{{ $documentNumber }}</strong></div>
                @endif

                @if($documentDate !== '')
                    <div class="pdf-meta-row">التاريخ: <strong dir="ltr">{{ $documentDate }}</strong></div>
                @endif

                @if($documentMeta !== '')
                    <div class="pdf-meta-row">{{ $documentMeta }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="print-content">
        @yield('print_content')
    </div>

    @if($showSignatures && ($signatureRight !== '' || $signatureLeft !== ''))
        <table class="pdf-signatures" cellpadding="0" cellspacing="0">
            <tr>
                @if($signatureRight !== '')
                    <td>
                        <div class="pdf-signature-space"></div>
                        <div class="pdf-signature-line"></div>
                        <div class="pdf-signature-label">{{ $signatureRight }}</div>
                    </td>
                @endif

                @if($signatureLeft !== '')
                    <td>
                        @if(!empty($printTheme['signature_src']))
                            <img src="{{ $printTheme['signature_src'] }}" class="pdf-signature-image" alt="">
                        @else
                            <div class="pdf-signature-space"></div>
                        @endif
                        <div class="pdf-signature-line"></div>
                        <div class="pdf-signature-label">{{ $signatureLeft }}</div>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    @if($showStamp && !empty($printTheme['stamp_src']))
        <div class="pdf-stamp">
            <img src="{{ $printTheme['stamp_src'] }}" alt="">
        </div>
    @endif

    @if(!empty($printTheme['show_footer']))
        <table class="pdf-footer" cellpadding="0" cellspacing="0">
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

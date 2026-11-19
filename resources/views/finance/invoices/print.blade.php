@php
    $invoicePrintService = app(\App\Services\PrintThemeService::class);
    $invoicePrintTheme = $invoicePrintService->settings();
    // Use the actual dashboard branding on customer invoices, preserving
    // other documents' independently configured print-logo preferences.
    $customerInvoiceLogo = $invoicePrintService->customerInvoiceLogoSource();
    if ($customerInvoiceLogo) {
        $printThemeOverrides = array_replace($printThemeOverrides ?? [], [
            'logo_src' => $customerInvoiceLogo,
        ]);
    }
    $invoicePrintTheme = array_replace(
        $invoicePrintTheme,
        array_intersect_key(
            $printThemeOverrides ?? [],
            array_flip(['business_name', 'business_name_en', 'logo_src'])
        )
    );

    $publicOrder = $order ?? null;
    $sourceOrder = $sourceOrder ?? $publicOrder;
    $invoiceServiceType = $sourceOrder?->restaurant_service_type;
    $invoiceServiceCode = $invoiceServiceType instanceof \BackedEnum
        ? (string) $invoiceServiceType->value : (string) ($invoiceServiceType ?? '');
    $invoiceServiceLabel = match ($invoiceServiceCode) {
        'dine_in' => 'داخل المطعم',
        'takeaway' => 'سفري',
        'delivery' => 'توصيل',
        default => null,
    };
    $invoiceTableName = $sourceOrder?->restaurantTable?->displayName();
    $invoiceForThermal = ($invoicePrintTheme['paper_size'] ?? 'A4') === '80mm';
    $paymentMethod = (string) ($invoice->payment_method ?? '');
    $paymentMethodLabel = match ($paymentMethod) {
        'cash' => 'نقدي',
        'card', 'credit_card' => 'بطاقة',
        'bank', 'bank_transfer' => 'تحويل بنكي',
        'wallet', 'e_wallet' => 'محفظة إلكترونية',
        'mixed' => 'دفع مختلط',
        default => '',
    };
    $customerName = $invoice->customer?->name
        ?? $publicOrder?->customer?->name
        ?? $publicOrder?->guest_name
        ?? 'عميل نقدي';
    $customerPhone = $invoice->customer?->phone
        ?? $publicOrder?->customer?->phone
        ?? $publicOrder?->guest_phone;

    $invoiceRows = $invoice->items->map(fn ($item) => [
        'description' => $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف'),
        'quantity' => (float) $item->quantity,
        'unit_price' => (float) $item->unit_price,
        'line_total' => (float) $item->line_total,
    ]);
    if ($invoiceRows->isEmpty() && $publicOrder) {
        $invoiceRows = $publicOrder->items->map(fn ($item) => [
            'description' => $item->product_name ?: 'صنف',
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
        ]);
    }

    $currencySymbol =
        $invoice->currency?->symbol
        ?? $invoicePrintTheme['currency_symbol']
        ?? '₪';

    $currencyCode =
        $invoice->currency?->code
        ?? $invoicePrintTheme['currency_code']
        ?? 'ILS';

    $paymentStatusValue = $invoice->paymentStatusValue();

    // The stamp is purely visual: use the invoice's existing payment status.
    // Keep the original status badges, rows, totals and their coordinates intact.
    $invoicePaymentPending = $sourceOrder?->payment_arrangement;
    $invoicePaymentPending = $invoicePaymentPending instanceof \BackedEnum
        ? (string) $invoicePaymentPending->value : (string) ($invoicePaymentPending ?? '');
    $invoiceIsPendingVerification = $invoicePaymentPending === 'pending_verification'
        && $paymentStatusValue !== 'paid';
    $paymentStampClass = match ($paymentStatusValue) {
        'paid' => 'invoice-watermark-paid',
        'partially_paid', 'partial' => 'invoice-watermark-partial',
        default => $invoiceIsPendingVerification ? 'invoice-watermark-partial' : 'invoice-watermark-unpaid',
    };
    $paymentStampLabel = match ($paymentStatusValue) {
        'paid' => 'مدفوعة',
        'partially_paid', 'partial' => 'مدفوعة جزئيًا',
        default => $invoiceIsPendingVerification ? 'بانتظار التحقق' : 'غير مدفوعة',
    };

    $paymentStatusClass = match ($paymentStatusValue) {
        'paid' => 'invoice-status-paid',
        'partially_paid', 'partial' => 'invoice-status-partial',
        default => 'invoice-status-unpaid',
    };

    $invoiceStatusClass = $invoice->isActive()
        ? 'invoice-status-active'
        : 'invoice-status-inactive';

    $issuedAt = $invoice->issued_at ?? now();

    $documentNumber =
        $invoice->invoice_number
        ?? $invoice->number
        ?? ('INV-' . $invoice->id);

    $documentSubtitle =
        $customerName
        . ' · '
        . $invoice->paymentStatusLabel();
@endphp

@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', $invoice->invoiceTypeValue() === 'special_cake' ? 'فاتورة طلب كيك' : 'فاتورة بيع')
@section('document_number', $documentNumber)
@section('document_date', $issuedAt?->format('Y-m-d') ?? now()->format('Y-m-d'))
@section('document_subtitle', $documentSubtitle)

@section(
    'document_meta',
    'الفرع: ' . ($invoice->location?->name ?? '—')
)

{{-- فواتير البيع لا تحتاج اعتمادًا إداريًا أو ختمًا رسميًا. --}}
@section('show_signatures', '0')
@section('show_stamp', '0')

@if($publicOrder)
    @section('print_close_url', route('customer-menu.track', ['token' => $publicOrder->public_token]))
    @section('print_toolbar')
        <a class="print-screen-btn" href="{{ route('customer-menu.track', ['token' => $publicOrder->public_token]) }}">تتبع الطلب</a>
        <a class="print-screen-btn" href="{{ route('customer-menu.show', $publicOrder->location->code) }}">المنيو</a>
    @endsection
@endif

@push('print_styles')
<style>
    /*
     * Bold semi-transparent rubber-stamp overlay. Absolute positioning deliberately
     * takes no space, so the original invoice header, tables and totals never
     * move. Limited to this invoice view; shared print layouts are untouched.
     */
    .print-content {
        position: relative;
    }

    .invoice-payment-watermark {
        position: absolute;
        top: 245px;
        left: 17%;
        width: 78%;
        height: 150px;
        z-index: 5;
        pointer-events: none;
        text-align: center;
        direction: rtl;
        -webkit-transform: rotate(-17deg);
        transform: rotate(-17deg);
        opacity: .82;
    }

    .invoice-payment-watermark-inner {
        display: block;
        width: 100%;
        height: 100%;
        border: 9px double #b42318;
        border-radius: 18px;
        padding: 20px 4px;
        font-size: 43pt;
        font-weight: 900;
        line-height: 1.5;
        white-space: nowrap;
    }

    .invoice-watermark-paid .invoice-payment-watermark-inner {
        color: #08783e;
        border-color: #08783e;
    }
    .invoice-watermark-unpaid .invoice-payment-watermark-inner {
        color: #b42318;
        border-color: #b42318;
    }
    .invoice-watermark-partial .invoice-payment-watermark-inner {
        color: #b16a00;
        border-color: #b16a00;
    }

    @media print {
        .invoice-payment-watermark {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }

    @media (max-width: 600px) {
        .invoice-payment-watermark {
            top: 235px;
            left: 10%;
            width: 84%;
            height: 118px;
        }
        .invoice-payment-watermark-inner {
            border-width: 6px;
            font-size: 26pt;
            padding: 16px 2px;
        }
    }

    .invoice-statuses {
        margin-top: 8px;
        text-align: left;
    }

    .invoice-status {
        display: inline-block;
        margin-right: 5px;
        padding: 3px 8px;
        font-size: 7.5pt;
        font-weight: 800;
    }

    .invoice-status-active,
    .invoice-status-paid {
        color: #08783e;
        background: #edf8f1;
    }

    .invoice-status-inactive,
    .invoice-status-unpaid {
        color: #b42318;
        background: #fff3f1;
    }

    .invoice-status-partial {
        color: #946716;
        background: #fff8e6;
    }

    .invoice-party-table {
        width: 100%;
        margin-top: 12px;
        table-layout: fixed;
    }

    .invoice-party-table > tbody > tr > td {
        width: 50%;
        padding: 0 4px;
        vertical-align: top;
    }

    .invoice-party-box {
        min-height: 108px;
        padding: 10px 11px;
        background: #f8fafc;
    }

    .invoice-party-label {
        margin-bottom: 7px;
        color: {{ $invoicePrintTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }

    .invoice-party-name {
        margin-bottom: 4px;
        color: {{ $invoicePrintTheme['secondary_color'] }};
        font-size: 12pt;
        font-weight: 900;
    }

    .invoice-detail-table {
        width: 100%;
    }

    .invoice-detail-table td {
        width: 50%;
        padding: 3px 0 6px;
        vertical-align: top;
    }

    .invoice-detail-label {
        display: block;
        color: #6b7280;
        font-size: 7pt;
    }

    .invoice-detail-value {
        display: block;
        color: {{ $invoicePrintTheme['secondary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }

    .invoice-items .description {
        text-align: right;
    }

    .invoice-items .qty {
        width: 13%;
        text-align: center;
    }

    .invoice-items .price,
    .invoice-items .total {
        width: 20%;
        text-align: center;
    }

    .invoice-bottom-table {
        width: 100%;
        margin-top: 14px;
        table-layout: fixed;
    }

    .invoice-bottom-table > tbody > tr > td {
        vertical-align: top;
    }

    .invoice-notes-column {
        width: 56%;
        padding-left: 7px;
    }

    .invoice-totals-column {
        width: 44%;
        padding-right: 7px;
    }

    .invoice-notes {
        min-height: 122px;
        padding: 10px;
        background: #ffffff;
    }

    .invoice-note-title {
        margin-bottom: 6px;
        color: {{ $invoicePrintTheme['primary_color'] }};
        font-size: 8pt;
        font-weight: 800;
    }

    .invoice-thanks {
        margin-top: 12px;
        color: #6b7280;
        font-size: 7.5pt;
        line-height: 1.65;
    }

    /* TISH Customer Invoice 2026 — real ERP identity, print/PDF-safe styling.
       The 80mm variant intentionally uses a simpler thermal composition. */
    .invoice-brand-ribbon {
        width: 62%;
        margin: 3px auto 17px;
        padding: 7px 18px 9px;
        border-radius: 13px;
        background: {{ $invoicePrintTheme['primary_color'] }};
        color: #fff;
        text-align: center;
        font-size: 20pt;
        font-weight: 900;
        line-height: 1.35;
        page-break-inside: avoid;
    }
    .print-header-table {border-bottom:0!important;margin-bottom:9px!important}
    .print-logo {max-width:170px;max-height:86px}
    .print-business-name {font-size:16pt;font-weight:900}
    .print-document-title {font-size:20pt;font-weight:900}
    .invoice-party-table {border-spacing:10px 0;border-collapse:separate;margin:8px 0 18px}
    .invoice-party-table > tbody > tr > td {padding:0;vertical-align:top}
    .invoice-party-box {
        padding:0 0 10px;
        min-height:145px;
        border:1px solid #d7d7d7;
        border-radius:12px;
        overflow:hidden;
        background:#fff;
    }
    .invoice-party-label {
        margin:0 0 11px;
        padding:8px 12px;
        color:#fff;
        background: {{ $invoicePrintTheme['primary_color'] }};
        font-size:11pt;
        font-weight:900;
    }
    .invoice-party-name {padding:0 12px;font-size:12pt}
    .invoice-party-box > .print-muted {padding:2px 12px}
    .invoice-detail-table {width:calc(100% - 20px);margin:0 10px}
    .invoice-detail-table tr {border-bottom:1px solid #e6e7e8}
    .invoice-detail-table td {padding:5px 4px}
    .invoice-detail-label {color:#686c76;font-size:7.5pt}
    .invoice-detail-value {font-size:8.5pt}
    .print-section-title {
        font-size:11pt;
        margin:0 0 7px;
        padding:5px 0;
        color: {{ $invoicePrintTheme['secondary_color'] }};
        border-bottom:2px solid {{ $invoicePrintTheme['primary_color'] }};
    }
    .invoice-items {border-radius:8px;border-collapse:collapse;overflow:hidden}
    .invoice-items th {
        background: {{ $invoicePrintTheme['primary_color'] }}!important;
        color:#fff!important;
        font-size:9pt;
        padding:10px 6px;
    }
    .invoice-items td {font-size:8.7pt;padding:10px 6px}
    .invoice-items .description {font-weight:800}
    .invoice-items .invoice-item-number {width:6%;text-align:center;font-weight:900}
    .invoice-bottom-table {margin-top:21px;border-collapse:separate;border-spacing:10px 0}
    .invoice-bottom-table > tbody > tr > td {padding:0;vertical-align:top}
    .invoice-notes-column {width:50%}
    .invoice-totals-column {width:50%}
    .invoice-notes {border:1px solid #d7d7d7;border-radius:10px;min-height:155px;padding:0 12px 12px;overflow:hidden}
    .invoice-note-title {
        background: {{ $invoicePrintTheme['primary_color'] }};
        color:#fff;
        font-size:10.5pt;
        font-weight:900;
        padding:8px 12px;
        margin:0 -12px 14px;
    }
    .invoice-thanks {font-size:8pt;margin-top:15px}
    .invoice-bottom-table .print-total-box {width:100%;border-radius:10px;overflow:hidden}
    .invoice-bottom-table .print-total-box td {font-size:8.5pt;padding:9px 11px}
    .invoice-bottom-table .print-total-box .grand td {font-size:12pt;padding:11px}
    .invoice-payment-watermark {
        top:43%;left:10%;width:80%;height:130px;
        transform:rotate(-17deg);
        -webkit-transform:rotate(-17deg);
        opacity:.20;
        z-index:2;
    }
    .invoice-payment-watermark-inner {
        border:8px double currentColor;
        font-size:43pt;
        border-radius:15px;
        padding:17px 3px;
        line-height:1.5;
    }
    .invoice-watermark-paid {color:#168744}
    .invoice-watermark-unpaid {color:#c52036}
    .invoice-watermark-partial {color:#9b6814}
    .invoice-watermark-paid .invoice-payment-watermark-inner,
    .invoice-watermark-unpaid .invoice-payment-watermark-inner,
    .invoice-watermark-partial .invoice-payment-watermark-inner {border-color:currentColor;color:inherit}
    @if($invoiceForThermal)
    .invoice-brand-ribbon{width:100%;border-radius:0;font-size:13pt;margin:0 0 8px;padding:5px}
    .print-header-table{margin-bottom:5px!important}
    .print-logo{max-height:46px;max-width:72px}
    .print-business-name{font-size:10pt}
    .invoice-party-table,.invoice-bottom-table{border-spacing:0;margin:8px 0}
    .invoice-party-table > tbody > tr > td,.invoice-bottom-table > tbody > tr > td{
        display:block;width:100%;padding:0 0 8px
    }
    .invoice-party-box{min-height:0;border-radius:3px;padding-bottom:6px}
    .invoice-party-label{font-size:8pt;padding:5px 8px;margin-bottom:5px}
    .invoice-party-name{font-size:9pt;padding:0 8px}
    .invoice-detail-table{width:calc(100% - 12px);margin:0 6px}
    .invoice-detail-table td{padding:3px 2px}
    .invoice-detail-label{font-size:6.4pt}
    .invoice-detail-value{font-size:7pt}
    .invoice-items th,.invoice-items td{font-size:6.1pt;padding:5px 2px}
    .invoice-items .qty{width:10%}
    .invoice-items .price,.invoice-items .total{width:22%}
    .invoice-items .invoice-item-number{width:6%}
    .invoice-notes{min-height:0;border-radius:3px}
    .invoice-note-title{font-size:8pt}
    .invoice-bottom-table .print-total-box td{font-size:7pt;padding:5px}
    .invoice-bottom-table .print-total-box .grand td{font-size:8pt}
    .invoice-payment-watermark{top:34%;left:8%;width:85%;height:75px;opacity:.14}
    .invoice-payment-watermark-inner{font-size:18pt;border-width:4px;padding:10px 2px}
    @endif
    @media print {
       .invoice-brand-ribbon,.invoice-party-label,.invoice-note-title,
       .invoice-items th,.invoice-bottom-table .print-total-box .grand td {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
       }
       .invoice-party-box,.invoice-notes,.invoice-bottom-table,
       .invoice-party-table {break-inside:avoid;page-break-inside:avoid}
    }

    /* TISH invoice polish V2: reference-style bold stamp and stacked details.
       Scoped to invoices: no modifications to shared ERP reports or kitchen tickets. */
    .invoice-detail-table {width:calc(100% - 20px);margin:0 10px;border-collapse:collapse;table-layout:fixed}
    .invoice-detail-table tr {border-bottom:1px solid #ebebee}
    .invoice-detail-table td {width:50%;padding:9px 6px 10px;vertical-align:top;text-align:right}
    .invoice-detail-label {
        display:block;margin-bottom:4px;color:#717681;font-size:7.5pt;
        font-weight:700;line-height:1.4;direction:rtl;text-align:right
    }
    .invoice-detail-value,
    .invoice-detail-value[dir="ltr"] {
        display:block;color:#1f2937;font-size:9pt;font-weight:900;
        line-height:1.5;text-align:right;unicode-bidi:plaintext;
    }
    .invoice-party-box {min-height:150px;border:1px solid #dedee2;border-radius:12px;background:#fff}
    .invoice-party-label {
        margin:0 0 12px;padding:9px 14px;color:#fff;
        background:{{ $invoicePrintTheme['primary_color'] }};
        font-size:11pt;font-weight:900;line-height:1.4
    }
    .invoice-party-name {padding:0 12px;font-size:12pt;font-weight:900;margin-bottom:8px;line-height:1.5}
    .invoice-party-box>.print-muted {display:block;padding:3px 12px;color:#4b5563;font-size:8.5pt;line-height:1.7;text-align:right}
    .invoice-payment-watermark {
        position:absolute;top:39%;left:8%;width:84%;height:150px;z-index:3;
        pointer-events:none;text-align:center;direction:rtl;
        transform:rotate(-17deg);-webkit-transform:rotate(-17deg);opacity:.65
    }
    .invoice-payment-watermark-inner {
        display:block;width:100%;height:100%;border:10px double currentColor;
        border-radius:18px;padding:18px 6px;font-size:54pt;font-weight:900;
        line-height:1.45;white-space:nowrap;letter-spacing:0;background:transparent
    }
    .invoice-watermark-paid {color:#19a84a}
    .invoice-watermark-unpaid {color:#d91f2f}
    .invoice-watermark-partial {color:#c48a14}
    .invoice-watermark-paid .invoice-payment-watermark-inner,
    .invoice-watermark-unpaid .invoice-payment-watermark-inner,
    .invoice-watermark-partial .invoice-payment-watermark-inner {
        color:inherit;border-color:currentColor
    }
    @if($invoiceForThermal)
    .invoice-detail-table {width:calc(100% - 12px);margin:0 6px}
    .invoice-detail-table td {padding:4px 3px 6px}
    .invoice-detail-label {font-size:6.4pt;margin-bottom:2px}
    .invoice-detail-value,.invoice-detail-value[dir="ltr"] {font-size:7pt}
    .invoice-party-box {min-height:0;border-radius:3px}
    .invoice-party-label {font-size:8pt;padding:5px 8px;margin-bottom:5px}
    .invoice-party-name {font-size:9pt;padding:0 8px;margin-bottom:3px}
    .invoice-party-box>.print-muted {font-size:7pt;padding:2px 8px}
    .invoice-payment-watermark {top:33%;left:8%;width:84%;height:72px;opacity:.24}
    .invoice-payment-watermark-inner {
        font-size:20pt;border-width:4px;border-radius:10px;padding:10px 2px
    }
    @endif
    @media print {
        .invoice-payment-watermark{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .invoice-detail-table td{break-inside:avoid;page-break-inside:avoid}
    }
</style>
@endpush

@section('print_content')
    <div class="invoice-brand-ribbon" aria-hidden="true">فاتورة بيع</div>
    <div class="invoice-payment-watermark {{ $paymentStampClass }}" aria-label="حالة السداد: {{ $paymentStampLabel }}">
        <div class="invoice-payment-watermark-inner">{{ $paymentStampLabel }}</div>
    </div>

    <div class="invoice-statuses">
        <div class="invoice-status {{ $invoiceStatusClass }}">
            {{ $invoice->statusLabel() }}
        </div>

        <div class="invoice-status {{ $paymentStatusClass }}">
            {{ $invoice->paymentStatusLabel() }}
        </div>
    </div>

    <table
        class="invoice-party-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td>
                <div class="invoice-party-box">
                    <div class="invoice-party-label">
                        <span aria-hidden="true">♙</span> بيانات العميل
                    </div>

                    <div class="invoice-party-name">
                        {{ $customerName }}
                    </div>

                    @if($invoiceServiceLabel)
                        <div class="print-muted">نوع الطلب: <strong>{{ $invoiceServiceLabel }}</strong></div>
                    @endif
                    @if($invoiceTableName)
                        <div class="print-muted">رقم الطاولة: <strong>{{ $invoiceTableName }}</strong></div>
                    @endif

                    @if($customerPhone)
                        <div class="print-muted">
                            الهاتف:
                            <strong dir="ltr">
                                {{ $customerPhone }}
                            </strong>
                        </div>
                    @endif

                    @if($publicOrder?->delivery_address)
                        <div class="print-muted">{{ $publicOrder->delivery_address }}</div>
                    @endif

                    @if($invoice->notes)
                        <div
                            class="print-muted"
                            style="margin-top:5px"
                        >
                            {{ $invoice->notes }}
                        </div>
                    @endif
                </div>
            </td>

            <td>
                <div class="invoice-party-box">
                    <div class="invoice-party-label">
                        <span aria-hidden="true">▤</span> بيانات الفاتورة
                    </div>

                    <table
                        class="invoice-detail-table"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        <tr>
                            <td>
                                <span class="invoice-detail-label">رقم الفاتورة</span>
                                <span class="invoice-detail-value" dir="ltr">{{ $documentNumber }}</span>
                            </td>
                            <td>
                                <span class="invoice-detail-label">تاريخ الفاتورة</span>
                                <span class="invoice-detail-value" dir="ltr">{{ $issuedAt?->format('Y-m-d') }}</span>
                            </td>
                        </tr>
                        @if($sourceOrder)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">رقم الطلب</span>
                                    <span class="invoice-detail-value">{{ $sourceOrder->order_number }}</span>
                                </td>
                                <td>
                                    <span class="invoice-detail-label">وقت الإصدار</span>
                                    <span class="invoice-detail-value" dir="ltr">{{ $issuedAt?->format('H:i') }}</span>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    الفرع
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->location?->name ?? '—' }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    هاتف الفرع
                                </span>

                                <span
                                    class="invoice-detail-value"
                                    dir="ltr"
                                >
                                    {{ $invoice->location?->phone ?? '—' }}
                                </span>
                            </td>
                        </tr>

                        @if($paymentMethodLabel !== '' || $sourceOrder?->salesChannel)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">طريقة الدفع</span>
                                    <span class="invoice-detail-value">{{ $paymentMethodLabel ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="invoice-detail-label">قناة البيع</span>
                                    <span class="invoice-detail-value">{{ $sourceOrder?->salesChannel?->name_ar ?? $sourceOrder?->salesChannel?->name ?? '—' }}</span>
                                </td>
                            </tr>
                        @endif

                        @if(! $publicOrder && $invoice->issuedBy)
                            <tr>
                                <td>
                                    <span class="invoice-detail-label">الموظف / الكاشير</span>
                                    <span class="invoice-detail-value">{{ $invoice->issuedBy->employee?->full_name ?? $invoice->issuedBy->display_name ?? '—' }}</span>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td>
                                <span class="invoice-detail-label">
                                    حالة الفاتورة
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->statusLabel() }}
                                </span>
                            </td>

                            <td>
                                <span class="invoice-detail-label">
                                    حالة السداد
                                </span>

                                <span class="invoice-detail-value">
                                    {{ $invoice->paymentStatusLabel() }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                @if($invoice->due_at)
                                    <span class="invoice-detail-label">
                                        تاريخ الاستحقاق
                                    </span>

                                    <span
                                        class="invoice-detail-value"
                                        dir="ltr"
                                    >
                                        {{ $invoice->due_at->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span class="invoice-detail-label">وقت الإصدار</span>
                                    <span class="invoice-detail-value" dir="ltr">{{ $issuedAt?->format('H:i') }}</span>
                                @endif
                            </td>
                            <td>
                                    <span class="invoice-detail-label">
                                        العملة
                                    </span>

                                    <span class="invoice-detail-value">
                                        {{ $currencyCode }}
                                    </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="print-section">
        <div class="print-section-title">
            تفاصيل الأصناف
        </div>

        <table
            class="print-table invoice-items"
            cellpadding="0"
            cellspacing="0"
        >
            <thead>
                <tr>
                    <th class="invoice-item-number">#</th>
                    <th class="description">الصنف</th>
                    <th class="qty">الكمية</th>
                    <th class="price">سعر الوحدة</th>
                    <th class="total">الإجمالي</th>
                </tr>
            </thead>

            <tbody>
                @forelse($invoiceRows as $item)
                    <tr>
                        <td class="invoice-item-number">{{ $loop->iteration }}</td>
                        <td class="description">
                            {{ $item['description'] }}
                        </td>

                        <td style="text-align:center">
                            {{ number_format(
                                $item['quantity'],
                                3
                            ) }}
                        </td>

                        <td
                            class="print-money"
                            style="text-align:center"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                $item['unit_price'],
                                2
                            ) }}
                        </td>

                        <td
                            class="print-money print-primary"
                            style="text-align:center"
                        >
                            {{ $currencySymbol }}
                            {{ number_format(
                                $item['line_total'],
                                2
                            ) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="5"
                            class="print-empty"
                        >
                            لا توجد عناصر في هذه الفاتورة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table
        class="invoice-bottom-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            <td class="invoice-notes-column">
                <div class="invoice-notes">
                    <div class="invoice-note-title">
                        ملاحظات
                    </div>

                    <div>
                        {{ $invoice->notes
                            ?: (
                                $invoicePrintTheme['footer_text']
                                ?: 'شكرًا لاختياركم.'
                            ) }}
                    </div>

                    <div class="invoice-thanks">
                        <div>
                            شكرًا لاختياركم
                            {{ $invoicePrintTheme['business_name'] }}.
                        </div>

                        @if($invoicePrintTheme['business_name_en'])
                            <div dir="ltr">
                                Thank you for choosing
                                {{ $invoicePrintTheme['business_name_en'] }}.
                            </div>
                        @endif
                    </div>
                </div>
            </td>

            <td class="invoice-totals-column">
                <table
                    class="print-total-box"
                    style="width:100%;margin-top:0"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        <td>المجموع الفرعي</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->subtotal,
                                2
                            ) }}
                        </td>
                    </tr>

                    @if((float) $invoice->discount_amount > 0)
                        <tr>
                            <td>الخصم</td>
                            <td class="print-debit">
                                -
                                {{ $currencySymbol }}
                                {{ number_format(
                                    (float) $invoice->discount_amount,
                                    2
                                ) }}
                            </td>
                        </tr>
                    @endif

                    @if(
                        isset($invoice->tax_amount)
                        && (float) $invoice->tax_amount > 0
                    )
                        <tr>
                            <td>الضريبة</td>
                            <td>
                                {{ $currencySymbol }}
                                {{ number_format(
                                    (float) $invoice->tax_amount,
                                    2
                                ) }}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td>المدفوع</td>
                        <td class="print-credit">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->paid_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>المتبقي</td>
                        <td class="{{ (float) $invoice->remaining_amount > 0 ? 'print-debit' : 'print-credit' }}">
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->remaining_amount,
                                2
                            ) }}
                        </td>
                    </tr>

                    <tr class="grand">
                        <td>الإجمالي</td>
                        <td>
                            {{ $currencySymbol }}
                            {{ number_format(
                                (float) $invoice->total_amount,
                                2
                            ) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection

@push('print_scripts')
@unless($pdfMode ?? false)
<script>
    window.addEventListener('load', function () {
        document.fonts.ready.then(function () {
            window.print();
        });
    });
</script>
@endunless
@endpush

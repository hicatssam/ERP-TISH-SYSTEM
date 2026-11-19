@php
    $printService = app(\App\Services\PrintThemeService::class);
    $theme = $printService->settings();
    $dashboardLogo = $printService->customerInvoiceLogoSource();
    $printThemeOverrides = ['logo_src' => $dashboardLogo ?: ($theme['logo_src'] ?? null)];
    $theme = array_replace($theme, $printThemeOverrides);

    $sourceOrder = $sourceOrder ?? $order ?? null;
    $sourceOrder?->loadMissing(['restaurantTable', 'customer', 'salesChannel', 'payments.paymentMethod']);
    $invoice->loadMissing(['location', 'customer', 'issuedBy.employee', 'items.product']);
    $issueTime = $invoice->issued_at ?? $invoice->created_at;
    $invoiceNumber = $invoice->invoice_number ?? ('INV-' . $invoice->id);
    $currencySymbol = $invoice->currency?->symbol ?: ($theme['currency_symbol'] ?? '₪');
    $customerName = ($invoice->customer?->name ?? $sourceOrder?->customer?->name ?? $sourceOrder?->guest_name) ?: 'عميل نقدي';
    $customerPhone = $invoice->customer?->phone ?? $sourceOrder?->customer?->phone ?? $sourceOrder?->guest_phone;
    $serviceCode = $sourceOrder?->restaurant_service_type;
    $serviceCode = $serviceCode instanceof \BackedEnum ? $serviceCode->value : (string) $serviceCode;
    $serviceLabel = match($serviceCode) {
        'dine_in' => 'داخل المطعم', 'takeaway' => 'سفري', 'delivery' => 'توصيل',
        default => $sourceOrder ? 'طلب مطعم' : 'بيع مباشر'
    };
    $tableName = $sourceOrder?->restaurantTable?->displayName();
    $status = $invoice->paymentStatusValue();
    $arrangement = $sourceOrder?->payment_arrangement;
    $arrangement = $arrangement instanceof \BackedEnum ? $arrangement->value : (string) $arrangement;
    $stamp = ! $invoice->isActive() ? 'ملغاة'
        : ($status === 'paid' ? 'مدفوعة'
        : ($status === 'partially_paid' ? 'مدفوعة جزئيًا'
        : ($arrangement === 'pending_verification' ? 'بانتظار التحقق' : 'غير مدفوعة')));
    $stampClass = ! $invoice->isActive() ? 'unpaid'
        : ($status === 'paid' ? 'paid' : ($status === 'partially_paid' || $arrangement === 'pending_verification' ? 'pending' : 'unpaid'));
    $invoiceLines = $invoice->items->map(fn ($item) => [
        'name' => $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف'),
        'quantity' => (float) $item->quantity, 'unit' => (float) $item->unit_price,
        'total' => (float) $item->line_total,
    ]);
    if ($invoiceLines->isEmpty() && $sourceOrder) {
        $sourceOrder->loadMissing('items');
        $invoiceLines = $sourceOrder->items->map(fn ($item) => [
            'name' => $item->product_name ?: 'صنف', 'quantity' => (float) $item->quantity,
            'unit' => (float) $item->unit_price, 'total' => (float) $item->line_total,
        ]);
    }
    $confirmedPayments = $sourceOrder?->payments?->filter(
        fn ($payment) => (string) ($payment->status instanceof \BackedEnum ? $payment->status->value : $payment->status) === 'confirmed'
    ) ?? collect();
    $methodNames = $confirmedPayments->map(
        fn ($payment) => $payment->paymentMethod?->name_ar ?: $payment->paymentMethod?->name
    )->filter()->unique()->values();
    $paymentLabel = $methodNames->implode(' + ') ?: ($invoice->payment_method ?: '—');
    $thermal = ($theme['paper_size'] ?? 'A4') === '80mm';
    $accent = $theme['primary_color'] ?? '#c20b27';
    $businessName = $theme['business_name'] ?: config('app.name');
    $businessNameEn = $theme['business_name_en'] ?? '';
    $businessPhone = $invoice->location?->phone ?: ($theme['business_phone'] ?? '');
    $businessAddress = $invoice->location?->address ?: ($theme['business_address'] ?? '');
@endphp
@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('document_title', 'فاتورة بيع')
@section('document_number', $invoiceNumber)
@section('document_date', $issueTime?->format('Y-m-d') ?? '')
@section('show_signatures', '0')
@section('show_stamp', '0')

@push('print_styles')
<style>
/* Reference-matched TISH customer invoice, scoped solely to this document. */
.print-header-table,.pdf-header,.print-footer,.pdf-footer {display:none!important}
.print-content{position:relative;clear:both;width:100%;overflow:visible}
.ci-page{position:relative;color:#18191c;direction:rtl;font-size:10pt;line-height:1.45}
.ci-header{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:12px}
.ci-header td{width:33.33%;vertical-align:middle;padding:4px 6px}
.ci-header .ci-brand{text-align:center}
.ci-logo{display:block;max-width:165px;max-height:120px;height:auto;width:auto;object-fit:contain;margin:0 auto 5px}
.ci-name{font-size:16pt;font-weight:900;line-height:1.3}
.ci-brand-en{direction:ltr;font-size:8pt;font-weight:800;letter-spacing:.04em}
.ci-side{font-size:8.5pt;line-height:1.9}
.ci-side strong{display:block;font-size:12pt;line-height:1.4}
.ci-side-left{text-align:left;direction:ltr}
.ci-side-right{text-align:right}
.ci-contact-line{display:block;overflow-wrap:anywhere}
.ci-contact-line b{color:{{ $accent }};padding:0 3px}
.ci-stripe{height:4px;background:{{ $accent }};margin:0 0 13px}
.ci-ribbon-wrap{text-align:center;margin:0 auto 19px}
.ci-ribbon{display:inline-block;width:57%;padding:8px 18px 12px;border-radius:12px;background:{{ $accent }};color:#fff;font-size:25pt;font-weight:900;line-height:1.25;text-align:center}
.ci-blocks{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:10px 0;margin:0 0 23px}
.ci-blocks>tbody>tr>td{width:50%;vertical-align:top;padding:0}
.ci-panel{border:1px solid #c5c5c5;border-radius:9px;overflow:hidden;min-height:170px}
.ci-panel-title{padding:7px 11px;background:{{ $accent }};color:#fff;font-size:12pt;font-weight:900;text-align:right}
.ci-info-table{width:100%;border-collapse:collapse}
.ci-info-table td{padding:7px 10px;border-bottom:1px solid #d8d8d8;font-size:9.1pt;vertical-align:top;text-align:right;overflow-wrap:anywhere}
.ci-info-table tr:last-child td{border-bottom:none}
.ci-info-label{font-weight:900;display:inline-block;min-width:40%;vertical-align:top}
.ci-info-value{font-weight:700;display:inline-block;max-width:60%;vertical-align:top}
.ci-info-value[dir=ltr]{direction:ltr;unicode-bidi:embed}
.ci-products{width:100%;border-collapse:collapse;table-layout:fixed}
.ci-products thead{display:table-header-group}
.ci-products th{background:{{ $accent }};color:#fff;font-size:9.6pt;font-weight:900;padding:10px 6px;border:1px solid #fff;text-align:center}
.ci-products td{padding:10px 7px;border:1px solid #d6d6d6;text-align:center;font-size:9pt;vertical-align:middle}
.ci-products tr{page-break-inside:avoid}
.ci-products tr:nth-child(even) td{background:#fafafa}
.ci-products .ci-product-name{text-align:right;font-weight:900;width:48%}
.ci-products .ci-num{width:7%}
.ci-products .ci-qty{width:12%}
.ci-products .ci-money{direction:ltr;white-space:nowrap;font-weight:800}
.ci-money b{color:{{ $accent }}}
.ci-bottom{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:10px 0;margin-top:24px}
.ci-bottom>tbody>tr>td{width:50%;padding:0;vertical-align:top}
.ci-notes{border:1px solid #cecece;border-radius:8px;min-height:140px;overflow:hidden}
.ci-notes-title{background:{{ $accent }};color:#fff;padding:8px 12px;font-size:12pt;font-weight:900}
.ci-notes-body{padding:11px;font-size:10pt}
.ci-totals{width:100%;border:1px solid #d6d6d6;border-collapse:collapse}
.ci-totals td{padding:9px;border-bottom:1px solid #ddd;font-size:9.5pt;font-weight:800}
.ci-totals td:last-child{text-align:left;direction:ltr;white-space:nowrap}
.ci-totals .ci-final td{background:{{ $accent }};color:#fff;font-size:13pt;font-weight:900;padding:11px}
.ci-footer{margin-top:26px;padding:12px 0 6px;border-top:3px solid {{ $accent }};font-size:9pt;font-weight:800;text-align:center}
.ci-footer-brand{font-size:10pt;font-weight:900;color:{{ $accent }}}
.ci-footer-en{font-size:8pt;letter-spacing:.07em;direction:ltr}
.ci-watermark{position:absolute;z-index:5;top:335px;left:5%;width:90%;height:245px;pointer-events:none;text-align:center;transform:rotate(-17deg);-webkit-transform:rotate(-17deg)}
.ci-watermark-inner{display:block;width:100%;height:100%;border:10px double currentColor;border-radius:18px;padding:36px 4px;box-sizing:border-box;font-size:65pt;line-height:1.4;font-weight:900;white-space:nowrap;text-shadow:1px 2px currentColor}
.ci-watermark.paid{color:rgba(5,170,20,.83)}
.ci-watermark.unpaid{color:rgba(223,0,26,.84)}
.ci-watermark.pending{color:rgba(196,132,0,.75)}

@if($thermal)
.ci-header td{display:block;width:100%;padding:2px;text-align:center!important}
.ci-logo{max-height:53px;max-width:86px}
.ci-name{font-size:10pt}.ci-brand-en{font-size:7pt}
.ci-side{font-size:6.7pt;line-height:1.4}.ci-side strong{font-size:8pt}
.ci-stripe{height:2px;margin-bottom:7px}
.ci-ribbon-wrap{margin-bottom:9px}
.ci-ribbon{width:100%;font-size:14pt;border-radius:4px;padding:6px}
.ci-blocks{border-spacing:0;margin-bottom:10px}
.ci-blocks>tbody>tr>td{display:block;width:100%;padding:0 0 8px}
.ci-panel{min-height:0;border-radius:4px}
.ci-panel-title{font-size:8.5pt;padding:5px 7px}
.ci-info-table td{padding:4px;font-size:7.5pt}
.ci-products th,.ci-products td{font-size:6.6pt;padding:5px 2px}
.ci-products .ci-product-name{width:37%}
.ci-products .ci-qty{width:11%}.ci-products .ci-num{width:7%}
.ci-bottom{margin-top:9px;border-spacing:0}
.ci-bottom>tbody>tr>td{display:block;width:100%;padding:0 0 8px}
.ci-notes{min-height:0;border-radius:4px}
.ci-notes-title{font-size:8pt;padding:5px}.ci-notes-body{font-size:7.5pt}
.ci-totals td{font-size:7pt;padding:5px}.ci-totals .ci-final td{font-size:9pt;padding:6px}
.ci-footer{margin-top:10px;font-size:7pt}
.ci-watermark{top:280px;left:5%;width:90%;height:85px}
.ci-watermark-inner{font-size:21pt;border-width:4px;border-radius:7px;padding:15px 2px}
@endif
@media print{
 .ci-panel,.ci-notes,.ci-bottom,.ci-header{page-break-inside:avoid;break-inside:avoid}
 .ci-products thead{display:table-header-group}
 .ci-ribbon,.ci-panel-title,.ci-products th,.ci-totals .ci-final td,.ci-notes-title{
    -webkit-print-color-adjust:exact;print-color-adjust:exact
 }
}

/* TISH invoice paper-art reference refinement — A4/A5 customer print */
.ci-page{background:#fff;isolation:isolate}
.ci-paper-art{position:absolute;z-index:0;top:0;width:24%;height:135px;opacity:.22;pointer-events:none}
.ci-paper-art svg{display:block;width:100%;height:100%}
.ci-paper-art-left{left:0;color:#656872}
.ci-paper-art-right{right:0;color:#c10b2a;opacity:.85}
.ci-header,.ci-stripe,.ci-ribbon-wrap,.ci-blocks,.ci-items-stage,.ci-bottom,.ci-footer{position:relative;z-index:1}
.ci-header{height:145px;margin-bottom:2px}
.ci-header .ci-brand{vertical-align:middle}
.ci-logo{max-height:118px;max-width:180px}
.ci-side{vertical-align:bottom!important;padding-bottom:13px!important}
.ci-stripe{height:2px;margin-bottom:13px}
.ci-ribbon-wrap{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:20px}
.ci-ribbon{width:auto;min-width:38%;padding:8px 30px 12px;border-radius:14px;font-size:25pt;
    background:linear-gradient(95deg,#b1001d,{{ $accent }} 45%,#9f0019);box-shadow:0 2px 3px rgba(80,0,0,.1)}
.ci-ribbon-line{height:3px;min-width:0;flex:1;background:{{ $accent }}}
.ci-burger-icon{color:{{ $accent }};font-size:26pt;font-weight:900;line-height:1}
.ci-blocks{margin-bottom:24px}
.ci-panel{border-radius:10px;min-height:171px}
.ci-panel-title{font-size:12pt;padding:8px 12px}
.ci-blocks>tbody>tr>td:first-child .ci-panel-title{background:#242529}
.ci-info-table td{padding:8px 11px;font-size:9.5pt}
.ci-info-label{min-width:43%}
.ci-info-value{max-width:57%}
.ci-items-stage{position:relative;min-height:180px}
.ci-products{position:relative;z-index:0}
.ci-products th{font-size:10pt;padding:11px 7px;background:linear-gradient(180deg,{{ $accent }},#9d0020)}
.ci-products td{font-size:9.4pt;padding:12px 7px}
.ci-watermark{position:absolute;top:12%;left:4%;width:92%;height:190px;z-index:3;
    transform:rotate(-17deg);-webkit-transform:rotate(-17deg);text-align:center;opacity:1}
.ci-watermark-inner{width:100%;height:100%;border:11px double currentColor;border-radius:24px;
    padding:15px 5px;font-size:63pt;line-height:1.48;font-weight:900;
    text-shadow:none;letter-spacing:-2px;box-shadow:inset 0 0 0 4px #fff9}
.ci-watermark.unpaid{color:rgba(220,0,18,.92)}
.ci-watermark.paid{color:rgba(11,164,32,.9)}
.ci-watermark.pending{color:rgba(185,112,0,.87)}
.ci-bottom{margin-top:27px}
.ci-totals td{font-size:10pt}
.ci-totals .ci-final td{font-size:13pt;background:linear-gradient(95deg,#bd0020,{{ $accent }})}
.ci-footer{border-top:3px solid {{ $accent }};margin-top:31px;padding-top:8px;min-height:78px;
   background:linear-gradient(180deg,#fff 0%,#f6f6f6 55%,#eceef0 100%)}
.ci-footer-skyline{text-align:center;color:#c8cbd0;font-size:19pt;letter-spacing:3px;margin-bottom:7px}
@if($thermal)
.ci-paper-art,.ci-ribbon-line,.ci-burger-icon,.ci-footer-skyline{display:none}
.ci-header{height:auto;margin-bottom:5px}
.ci-logo{max-height:53px;max-width:86px}
.ci-stripe{margin-bottom:7px}
.ci-ribbon-wrap{margin-bottom:9px}
.ci-ribbon{width:100%;min-width:0;font-size:14pt;border-radius:4px;padding:6px}
.ci-blocks{margin-bottom:10px}
.ci-panel{min-height:0;border-radius:4px}
.ci-panel-title{font-size:8.5pt;padding:5px 7px}
.ci-info-table td{font-size:7.5pt;padding:4px}
.ci-items-stage{min-height:110px}
.ci-products th,.ci-products td{font-size:6.6pt;padding:5px 2px}
.ci-watermark{top:14%;left:5%;width:90%;height:85px}
.ci-watermark-inner{font-size:20pt;padding:12px 2px;border-width:4px;border-radius:7px;letter-spacing:0}
.ci-footer{min-height:0;margin-top:10px;padding-top:6px}
@endif
@media print{
 .ci-paper-art,.ci-stripe,.ci-ribbon-line,.ci-footer{-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .ci-watermark{break-inside:avoid}
}

</style>
@endpush

@section('print_content')
<div class="ci-page">
    {{-- Decorative artwork is inline, monochrome, and independent of the configured logo. --}}
    <div class="ci-paper-art ci-paper-art-left" aria-hidden="true">
        <svg viewBox="0 0 220 170" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 154h195M26 154V71h36v83M33 71V48h22v23M44 48V22m-7 0h14M28 81h32M28 101h32M28 121h32M77 154V100h38v54M84 100V81h24v19M127 154V58h25v96M133 58V39h13v19M163 154V108h34v46M170 108V88h20v20" stroke="currentColor" stroke-width="2"/>
            <circle cx="44" cy="88" r="11" stroke="currentColor" stroke-width="2"/>
            <path d="M44 81v8l5 4M15 154l17-14 18 14M79 154l19-16 17 16M152 154l22-14 28 14" stroke="currentColor" stroke-width="2"/>
        </svg>
    </div>
    <div class="ci-paper-art ci-paper-art-right" aria-hidden="true">
        <svg viewBox="0 0 190 160" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
            <path d="M18 78Q22 35 95 34Q167 35 173 78Z"/>
            <path d="M29 90Q95 103 161 90M27 106Q53 96 77 111Q96 91 122 107Q140 95 167 109M28 123Q95 132 161 123"/>
            <path d="M20 139Q95 154 172 139L165 124H26Z"/>
            <path d="M46 64h4m25-12h4m28 9h4m26 8h4M5 153l47-8M134 147l49 7"/>
        </svg>
    </div>
    <table class="ci-header"><tr>
        <td class="ci-side ci-side-right">
            <strong>{{ $businessName }}</strong>
            @if($theme['business_tagline'] ?? false)<div>{{ $theme['business_tagline'] }}</div>@endif
            @if($businessAddress)<div>{{ $businessAddress }}</div>@endif
        </td>
        <td class="ci-brand">
            @if($theme['logo_src'] ?? false)
                <img class="ci-logo" src="{{ $theme['logo_src'] }}" alt="{{ $businessName }}">
            @endif
            @if(!($theme['logo_src'] ?? false))<div class="ci-name">{{ $businessName }}</div>@endif
            @if($businessNameEn)<div class="ci-brand-en">{{ $businessNameEn }}</div>@endif
        </td>
        <td class="ci-side ci-side-left">
            @if($businessPhone)<div class="ci-contact-line">{{ $businessPhone }} <b>☎</b></div>@endif
            @if($theme['business_email'] ?? false)<div class="ci-contact-line">{{ $theme['business_email'] }} <b>✉</b></div>@endif
            <div class="ci-contact-line">{{ $invoice->location?->name ?? $businessName }}</div>
        </td>
    </tr></table>
    <div class="ci-stripe"></div>
    <div class="ci-ribbon-wrap">
        <span class="ci-ribbon-line"></span>
        <span class="ci-burger-icon" aria-hidden="true">☰</span>
        <div class="ci-ribbon">فاتورة بيع</div>
        <span class="ci-burger-icon" aria-hidden="true">☰</span>
        <span class="ci-ribbon-line"></span>
    </div>

    <table class="ci-blocks"><tr>
        <td>
            <div class="ci-panel">
                <div class="ci-panel-title">بيانات العميل ♙</div>
                <table class="ci-info-table">
                    <tr><td><span class="ci-info-label">اسم العميل:</span><span class="ci-info-value">{{ $customerName }}</span></td></tr>
                    <tr><td><span class="ci-info-label">رقم الجوال:</span><span class="ci-info-value" dir="ltr">{{ $customerPhone ?: '—' }}</span></td></tr>
                    <tr><td><span class="ci-info-label">نوع الطلب:</span><span class="ci-info-value">{{ $serviceLabel }}</span></td></tr>
                    @if($tableName)<tr><td><span class="ci-info-label">رقم الطاولة:</span><span class="ci-info-value">{{ $tableName }}</span></td></tr>@endif
                </table>
            </div>
        </td>
        <td>
            <div class="ci-panel">
                <div class="ci-panel-title">بيانات الفاتورة ▤</div>
                <table class="ci-info-table">
                    <tr><td><span class="ci-info-label">رقم الفاتورة:</span><span class="ci-info-value" dir="ltr">{{ $invoiceNumber }}</span></td></tr>
                    <tr><td><span class="ci-info-label">تاريخ الفاتورة:</span><span class="ci-info-value" dir="ltr">{{ $issueTime?->format('Y-m-d') ?? '—' }}</span></td></tr>
                    <tr><td><span class="ci-info-label">وقت الفاتورة:</span><span class="ci-info-value" dir="ltr">{{ $issueTime?->format('H:i') ?? '—' }}</span></td></tr>
                    <tr><td><span class="ci-info-label">طريقة الدفع:</span><span class="ci-info-value">{{ $paymentLabel }}</span></td></tr>
                    <tr><td><span class="ci-info-label">حالة الدفع:</span><span class="ci-info-value">{{ $stamp }}</span></td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <div class="ci-items-stage">
    <table class="ci-products">
        <thead><tr>
            <th class="ci-num">#</th><th class="ci-product-name">الصنف</th>
            <th class="ci-qty">الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th>
        </tr></thead>
        <tbody>
            @forelse($invoiceLines as $line)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="ci-product-name">{{ $line['name'] }}</td>
                    <td>{{ number_format($line['quantity'], 0) }}</td>
                    <td class="ci-money">{{ number_format($line['unit'], 2) }} {{ $currencySymbol }}</td>
                    <td class="ci-money">{{ number_format($line['total'], 2) }} {{ $currencySymbol }}</td>
                </tr>
            @empty
                <tr><td colspan="5">لا توجد أصناف</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ci-watermark {{ $stampClass }}" aria-label="حالة الدفع: {{ $stamp }}">
        <div class="ci-watermark-inner">{{ $stamp }}</div>
    </div>
    </div>

    <table class="ci-bottom"><tr>
        <td>
            <div class="ci-notes">
                <div class="ci-notes-title">ملاحظات ▤</div>
                <div class="ci-notes-body">{{ $invoice->notes ?: ($theme['footer_text'] ?: 'شكرًا لزيارتكم، نتطلع لخدمتكم مرة أخرى.') }}</div>
            </div>
        </td>
        <td>
            <table class="ci-totals">
                <tr><td>إجمالي الأصناف</td><td>{{ number_format((float)$invoice->subtotal, 2) }} {{ $currencySymbol }}</td></tr>
                @if((float)$invoice->discount_amount > 0)
                    <tr><td>الخصم</td><td>-{{ number_format((float)$invoice->discount_amount, 2) }} {{ $currencySymbol }}</td></tr>
                @endif
                @if((float)$invoice->tax_amount > 0)
                    <tr><td>الضريبة</td><td>{{ number_format((float)$invoice->tax_amount, 2) }} {{ $currencySymbol }}</td></tr>
                @endif
                <tr><td>المدفوع</td><td>{{ number_format((float)$invoice->paid_amount, 2) }} {{ $currencySymbol }}</td></tr>
                @if((float)$invoice->remaining_amount > 0)
                    <tr><td>المتبقي</td><td>{{ number_format((float)$invoice->remaining_amount, 2) }} {{ $currencySymbol }}</td></tr>
                @endif
                <tr class="ci-final"><td>الإجمالي النهائي</td><td>{{ number_format((float)$invoice->total_amount, 2) }} {{ $currencySymbol }}</td></tr>
            </table>
        </td>
    </tr></table>

    <div class="ci-footer">
        <div class="ci-footer-skyline" aria-hidden="true">♜ ───────── ♜ ───────── ♜</div>
        <div class="ci-footer-brand">{{ $businessName }}</div>
        @if($businessNameEn)<div class="ci-footer-en">{{ $businessNameEn }}</div>@endif
        @if($businessPhone)<div>{{ $businessPhone }}</div>@endif
        <div>شكرًا لزيارتكم</div>
    </div>
</div>
@endsection

@push('print_scripts')
@unless($pdfMode ?? false)
<script>
window.addEventListener('load', () => {
    const ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    ready.then(() => window.print());
});
</script>
@endunless
@endpush

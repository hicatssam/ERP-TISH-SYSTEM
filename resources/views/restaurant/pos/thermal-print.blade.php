@php
    $theme = app(\App\Services\PrintThemeService::class)->settings();
    $logo = app(\App\Services\PrintThemeService::class)->customerInvoiceLogoSource();
    $currency = $invoice?->currency?->symbol ?: ($theme['currency_symbol'] ?? '₪');
    $money = fn ($n) => number_format((float)$n,2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float)$n,3,'.',''),'0'),'.');
    $refund = $invoice?->refundSummary();
    $state = $refund['state'] ?? $invoice?->paymentStatusValue();
    $paymentLabel = match(true) {
        $invoice && !$invoice->isActive() => 'ملغاة',
        $state === 'refunded' => 'مستردة بالكامل',
        $state === 'partially_refunded' => 'مستردة جزئيًا',
        $state === 'paid' => 'مدفوعة',
        $state === 'partially_paid' => 'مدفوعة جزئيًا',
        $order->payments()->where('status','pending_verification')->exists() => 'بانتظار التحقق',
        default => 'غير مدفوعة',
    };
    $tickets = $includeKitchen ? $order->kitchenTickets : collect();
    // Count printed rows, not units: ten burgers on one row do not require ten rows.
    $densityFor = static fn (int $rows): string => match (true) {
        $rows >= 10 => 'dense',
        $rows >= 5 => 'compact',
        default => 'normal',
    };
    $invoiceDensity = $densityFor($invoice?->items?->count() ?? 0);
    $kitchenDensity = $densityFor((int) $tickets->sum(fn ($ticket) => $ticket->items->count()));
    $service = $order->restaurant_service_type?->label() ?? 'طلب مطعم';
    $serviceEn = match($order->restaurant_service_type?->value) {
        'dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'outdoor' => 'Curbside pickup',
        'delivery' => 'Delivery', 'phone' => 'Phone', 'web' => 'Web', default => 'Restaurant order',
    };
    $paymentEn = match($paymentLabel) {
        'ملغاة' => 'Cancelled', 'مستردة بالكامل' => 'Fully refunded',
        'مستردة جزئيًا' => 'Partially refunded', 'مدفوعة' => 'Paid',
        'مدفوعة جزئيًا' => 'Partially paid', 'بانتظار التحقق' => 'Pending verification',
        default => 'Unpaid',
    };
    $kitchenStatusEn = ['queued'=>'Awaiting preparation','preparing'=>'Preparing','ready'=>'Ready','served'=>'Served','cancelled'=>'Cancelled'];
    $invoiceAt = ($invoice?->issued_at ?? $invoice?->created_at)?->copy()->setTimezone('Asia/Gaza');
    $kitchenAt = ($order->confirmed_at ?? $order->created_at)?->copy()->setTimezone('Asia/Gaza');
    $weekdays = ['Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    $dayLabel = fn ($at) => $at ? ($weekdays[$at->format('l')] ?? '').' / '.$at->format('l') : '—';
    // Keep the original transaction description and only supplement it with
    // the other language from existing catalogue fields when present.
    $otherName = function ($snapshot, $model) {
        $snapshot = trim((string) $snapshot);
        $hasArabic = preg_match('/\p{Arabic}/u', $snapshot);
        $candidate = trim((string) ($hasArabic ? $model?->name : $model?->name_ar));
        if ($candidate === '' || $snapshot === '' || mb_stripos($snapshot, $candidate) !== false) return null;
        if ($hasArabic && preg_match('/\p{Arabic}/u', $candidate)) return null;
        if (!$hasArabic && !preg_match('/\p{Arabic}/u', $candidate)) return null;
        return $candidate;
    };
@endphp
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>طباعة / Print {{ $order->order_number }}</title>
@include('layouts.print.fonts')
<style>
*{box-sizing:border-box}body{margin:0;background:#eee;color:#111;font:12px Cairo,Arial,sans-serif}.toolbar{padding:10px;display:flex;flex-wrap:wrap;justify-content:center;gap:8px;background:white}.toolbar button,.toolbar a{font:inherit;padding:8px;border:1px solid #888;background:white;color:#111;border-radius:4px;text-decoration:none;cursor:pointer}.receipt{width:{{ $paperWidth === 58 ? 46 : 64 }}mm;max-width:100%;margin:12px auto;padding:1.5mm;background:white;line-height:1.55;overflow-wrap:anywhere}h1,h2,p{margin:0}h1{text-align:center;font-size:17px}h2{font-size:14px;margin-top:9px}.center{text-align:center}.logo{display:block;max-width:30mm;max-height:18mm;margin:0 auto 5px;filter:grayscale(1)}.meta{border-block:1px dashed;padding:5px 0;margin:7px 0}.number{text-align:center;font-size:22px;font-weight:bold}table{width:100%;border-collapse:collapse;font-size:11px}td,th{padding:5px 1px;border-bottom:1px dotted #888;text-align:right;vertical-align:top}.amount{text-align:left;direction:ltr;white-space:nowrap}.line{display:flex;justify-content:space-between;gap:5px}.total{border-top:2px solid;font-size:15px;font-weight:bold;padding-top:5px}.status{border:1px solid;text-align:center;font-weight:bold;margin:8px 0;padding:4px}.kitchen-item{border-bottom:1px dashed;padding:6px 0;break-inside:avoid}.kitchen-item .line{font-size:15px;font-weight:bold}.note{font-weight:bold}.cancelled{text-decoration:line-through}

/* Kitchen copy uses the same paper width, logo, font and dotted rules as the customer invoice. */
.kitchen-copy .kitchen-title{font-size:14px;font-weight:700}
.kitchen-copy .kitchen-meta .line{align-items:baseline;gap:4px}
.kitchen-copy .kitchen-meta .line>span:last-child{text-align:left}
.kitchen-copy .kitchen-order{font-size:14px;font-weight:900}
.kitchen-copy .kitchen-order bdi{overflow-wrap:anywhere}
.kitchen-copy .kitchen-service{border:1.5px solid #111;padding:4px 3px;margin:6px 0 8px;break-inside:avoid}
.kitchen-copy .kitchen-service strong{display:block;font-size:17px;line-height:1.3}
.kitchen-copy .kitchen-service span{display:block;font-size:13px;font-weight:800}
.kitchen-copy .kitchen-station{break-after:avoid;page-break-after:avoid}
.kitchen-copy .kitchen-station h2{font-size:13px;margin:8px 0 0}
.kitchen-copy .kitchen-station-meta{font-size:10px;gap:4px;align-items:baseline}
.kitchen-copy .kitchen-table{table-layout:fixed;margin-top:4px;font-size:13px}
.kitchen-copy .kitchen-qty-col{width:22%}
.kitchen-copy .kitchen-table th{border-block:1px solid #111;font-size:11px;padding:4px 2px}
.kitchen-copy .kitchen-table td{padding:6px 2px;border-bottom:1px dotted #555;vertical-align:top}
.kitchen-copy .kitchen-qty{text-align:center;font-weight:900;font-variant-numeric:tabular-nums}
.kitchen-copy td.kitchen-qty{font-size:16px}
.kitchen-copy .kitchen-name{font-size:14px;line-height:1.45}
.kitchen-copy .kitchen-notes{margin-top:3px;padding:3px 5px;border-inline-start:2px solid #111;font-size:11px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}
.kitchen-copy .kitchen-cancel{font-size:11px;font-weight:900;border:1px solid #111;padding:2px;margin-top:3px}
.kitchen-copy .kitchen-footer{border-top:2px solid #111;margin-top:8px;padding-top:5px;font-size:11px;font-weight:700}
.kitchen-copy .kitchen-item{break-inside:avoid;page-break-inside:avoid}
@if($paperWidth === 58)
.kitchen-copy .kitchen-order,.kitchen-copy .kitchen-name{font-size:12px}
.kitchen-copy .kitchen-service strong{font-size:15px}
.kitchen-copy .kitchen-station-meta{flex-wrap:wrap}
@endif

/* Compact bilingual labels, without shrinking the operational quantities. */
.receipt .en{display:block;font-size:.83em;font-weight:500;line-height:1.3;direction:ltr;unicode-bidi:isolate}
.receipt .bi-label{min-width:0}
.receipt .meta .line{align-items:baseline;padding:2px 0}
.receipt .meta .line>span:last-child{text-align:left}
.receipt .meta bdi{direction:ltr;text-align:left}
.receipt .day-value{font-weight:700;text-align:left}
.receipt .doc-title{font-size:14px;font-weight:700}
.receipt .item-alt{display:block;font-size:10px;line-height:1.35;font-weight:500;margin-top:2px;overflow-wrap:anywhere}
.receipt th .en{font-size:9px}
.receipt .status .en{font-size:11px}
.receipt .time-zone{font-size:9px;margin-top:2px}
.receipt .kitchen-service strong .en{font-size:12px}
.receipt .customer-copy{font-variant-numeric:tabular-nums}
@if($paperWidth === 58)
.receipt .meta,.receipt .line{font-size:10px}
.receipt .total{font-size:13px}
.receipt th .en{font-size:8px}
.receipt .item-alt{font-size:9px}
.receipt .amount{font-size:10px}
@endif
/* Fit within the printable area of narrow thermal drivers.
   Keep the page length automatic and wrap content rather than clipping it. */
.receipt .line>*{min-width:0;overflow-wrap:anywhere}
.receipt .line>bdi{flex-shrink:0;max-width:62%;white-space:normal;text-align:left}
.receipt .meta .line>bdi{flex-shrink:1}
.receipt .customer-copy,.customer-copy{font-variant-numeric:tabular-nums}
.customer-copy table{table-layout:fixed;width:100%}
.customer-copy th:nth-child(1){width:42%}
.customer-copy th:nth-child(2){width:12%}
.customer-copy th:nth-child(3),.customer-copy th:nth-child(4){width:23%}
.customer-copy td,.customer-copy th{overflow-wrap:anywhere;white-space:normal}
.customer-copy td.amount{font-size:10px;font-variant-numeric:tabular-nums}
.receipt img{max-width:100%;object-fit:contain}
.receipt .logo{max-width:26mm}
.receipt .kitchen-station-meta{flex-wrap:wrap}
@if($paperWidth === 58)
.customer-copy table{font-size:10px}
.customer-copy td.amount{font-size:9px}
@endif
/* Compact bilingual thermal design. The 64/46 mm safe widths stay intact. */
.receipt{line-height:1.3}
.receipt .logo{max-width:23mm;max-height:15mm;margin-bottom:2px}
.receipt h1{font-size:15px;line-height:1.25}
.receipt .doc-title,.receipt .kitchen-title{font-size:11px;margin-top:2px}
.receipt .doc-title .en,.receipt .kitchen-title .en,
.receipt .line .bi-label .en,.receipt .line .service-value .en,
.receipt .status .en{display:inline;font-size:.85em;font-weight:inherit}
.receipt .meta{border-block:1px solid #111;padding:3px 0;margin:5px 0}
.receipt .meta .line{padding:1px 0;font-size:10px;align-items:baseline;gap:3px}
.receipt .meta .line>span:first-child{flex:0 1 43%}
.receipt .meta .line>span:last-child,.receipt .meta .line>bdi{flex:1;text-align:left;font-weight:700;max-width:none}
.receipt .date-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr) minmax(0,.9fr);border-top:1px solid #111;margin-top:3px;padding-top:3px;text-align:center;break-inside:avoid}
.receipt .date-cell{padding:1px 2px;min-width:0;overflow-wrap:anywhere}
.receipt .date-cell+.date-cell{border-inline-start:1px solid #777}
.receipt .date-label{display:block;font-size:9px;line-height:1.25}
.receipt .date-value{display:block;font-size:10px;font-weight:800;margin-top:2px;line-height:1.25}
.receipt .date-cell .en{font-size:9px}
.receipt .date-grid .time-zone{font-size:8px;line-height:1.2}
.customer-copy table,.kitchen-copy .kitchen-table{font-size:10px;margin:4px 0 0}
.receipt table thead th{background:#111;color:#fff;font-weight:800;padding:3px 1px;border-bottom:1px solid #111;line-height:1.2;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.receipt table thead .en{font-size:8px;color:inherit;margin-top:1px}
.customer-copy th:nth-child(1){width:44%}
.customer-copy th:nth-child(2){width:14%;text-align:center}
.customer-copy th:nth-child(3),.customer-copy th:nth-child(4){width:21%;text-align:center}
.customer-copy td:nth-child(2){text-align:center}
.customer-copy td.amount{font-size:10px;text-align:center}
.customer-copy td:first-child{font-weight:700}
.customer-copy td,.kitchen-copy .kitchen-table td{padding:3px 1px;vertical-align:middle}
.receipt tbody tr{break-inside:avoid;page-break-inside:avoid}
.receipt .item-alt{font-size:9px;margin-top:1px;line-height:1.25;font-weight:500}
.receipt .summary{margin-top:3px;font-size:10px}
.receipt .summary .line{padding:1px 0;align-items:baseline;gap:3px}
.receipt .summary .line .bi-label{flex:1}
.receipt .summary .total{margin:3px 0;padding:4px 0;border-block:1.5px solid #111;font-size:14px}
.receipt .summary .total .en{font-size:10px}
.receipt .status{border:1px solid #111;border-radius:2px;padding:3px 2px;margin:5px 0;font-size:10px;line-height:1.3;break-inside:avoid}
.receipt .status .en{font-size:9px}
.receipt .receipt-footer{border-top:1px solid #111;padding-top:3px;font-weight:700;font-size:10px}
.kitchen-copy .kitchen-service{margin:4px 0;padding:3px 2px}
.kitchen-copy .kitchen-service strong{font-size:14px}
.kitchen-copy .kitchen-service strong .en{display:inline;font-size:11px}
.kitchen-copy .kitchen-station h2{font-size:12px;margin-top:5px}
.kitchen-copy .kitchen-name{font-size:12px}
.kitchen-copy .kitchen-table td.kitchen-qty{font-size:15px}
.kitchen-copy .kitchen-notes{font-size:10px;margin-top:2px;padding:2px 3px}
.kitchen-copy .kitchen-footer{margin-top:5px;padding-top:3px}
@if($paperWidth === 58)
.receipt .meta .line{font-size:9px}
.receipt .date-label,.receipt .date-value,.receipt .date-cell .en{font-size:8px}
.receipt .date-cell{padding-inline:1px}
.customer-copy table,.customer-copy td.amount{font-size:9px}
.customer-copy th:nth-child(1){width:40%}
.customer-copy th:nth-child(2){width:16%}
.customer-copy th:nth-child(3),.customer-copy th:nth-child(4){width:22%}
.receipt .summary .total{font-size:12px}
@endif
/* Adaptive density: compress product rows only, not financial totals or instructions.
   A finite lower limit protects thermal legibility; long orders may grow in length. */
.receipt{--item-font:10px;--item-alt-font:9px;--item-pad:2px;--kitchen-name-font:12px;--kitchen-qty-font:15px;--kitchen-note-font:10px}
.receipt[data-density="compact"]{--item-font:9.5px;--item-alt-font:8.5px;--item-pad:1.5px;--kitchen-name-font:11.5px;--kitchen-qty-font:14px}
.receipt[data-density="dense"]{--item-font:9px;--item-alt-font:8px;--item-pad:1px;--kitchen-name-font:11px;--kitchen-qty-font:13px}
.customer-copy table,.customer-copy td.amount{font-size:var(--item-font)}
.customer-copy tbody td,.kitchen-copy .kitchen-table tbody td{padding:var(--item-pad) 1px;line-height:1.25}
.receipt .item-alt{font-size:var(--item-alt-font);line-height:1.2;margin-top:0}
.kitchen-copy .kitchen-name{font-size:var(--kitchen-name-font);line-height:1.25}
.kitchen-copy .kitchen-table td.kitchen-qty{font-size:var(--kitchen-qty-font);vertical-align:top}
.kitchen-copy .kitchen-notes{font-size:var(--kitchen-note-font);line-height:1.3;margin-top:1px;padding:1px 3px}
.kitchen-copy .kitchen-cancel{font-size:11px;line-height:1.3;margin-top:1px}
.receipt .discount-line{font-weight:800}
.receipt .discount-line>bdi{font-weight:900}
.receipt .social-footer{display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap;margin-top:3px;font-size:9px;line-height:1.25;color:#111}
.receipt .social-footer svg{width:11px;height:11px;flex:none}
.receipt .social-footer a{color:inherit;text-decoration:none;font-weight:800}
.receipt .social-footer bdi{white-space:nowrap}
@if($paperWidth === 58)
.receipt[data-density="normal"]{--item-font:9px;--item-alt-font:8px;--kitchen-name-font:11px}
.receipt[data-density="compact"],.receipt[data-density="dense"]{--item-font:9px;--item-alt-font:8px;--kitchen-name-font:10.5px}
@endif
/* Inline bilingual item names and column headings: no forced second line. */
.receipt table thead th{font-size:9px;line-height:1.2;vertical-align:middle;overflow-wrap:normal;word-break:normal}
.receipt table thead th .en{display:inline;font-size:8px;margin:0;white-space:nowrap}
.customer-copy th:nth-child(1){width:40%}
.customer-copy th:nth-child(2){width:16%}
.customer-copy th:nth-child(3),.customer-copy th:nth-child(4){width:22%}
.receipt .item-alt{display:inline;margin:0;line-height:inherit;overflow-wrap:anywhere}
.receipt .item-alt bdi{white-space:normal;overflow-wrap:anywhere}
.receipt .item-separator{font-weight:400}
.kitchen-copy .kitchen-name{display:inline}
.kitchen-copy .kitchen-item td:first-child{vertical-align:top}
@if($paperWidth === 58)
.receipt table thead th{font-size:8px}
.receipt table thead th .en{font-size:7.5px}
.customer-copy th:nth-child(1){width:38%}
.customer-copy th:nth-child(2){width:18%}
@endif
@page{size:auto;margin:0}
@media print{body{background:white}.toolbar{display:none!important}.receipt{margin:0 auto;max-width:100%}.receipt+.receipt{break-before:page;page-break-before:always}body[data-mode="customer"] .kitchen-copy,body[data-mode="kitchen"] .customer-copy{display:none}body[data-mode="kitchen"] .kitchen-copy{break-before:auto;page-break-before:auto}}
</style></head><body data-mode="all">
<div class="toolbar"><button data-print="all">طباعة المستندات / Print documents</button>
@if($invoice && $tickets->isNotEmpty())<button data-print="customer">فاتورة العميل فقط / Invoice only</button><button data-print="kitchen">المطبخ فقط / Kitchen only</button>@endif
<a href="{{ request()->fullUrlWithQuery(['width'=>$paperWidth === 80 ? 58 : 80,'preview'=>1]) }}">{{ $paperWidth === 80 ? '58 mm' : '80 mm' }}</a><button onclick="window.close()">إغلاق / Close</button></div>
@if($invoice)
<section class="receipt customer-copy" data-document="invoice" data-density="{{ $invoiceDensity }}">
@if($logo)<img class="logo" src="{{ $logo }}" alt="TISH">@endif
<h1>{{ $theme['business_name'] ?: 'TISH' }}</h1><p class="center doc-title">فاتورة العميل / <span class="en" lang="en">Customer Invoice</span></p>
<div class="meta">
<div class="line"><span class="bi-label">الطلب / <span class="en" lang="en">Order</span></span><bdi>{{ $order->order_number }}</bdi></div>
<div class="line"><span class="bi-label">الفاتورة / <span class="en" lang="en">Invoice</span></span><bdi>{{ $invoice->invoice_number }}</bdi></div>
<div class="line"><span class="bi-label">الفرع / <span class="en" lang="en">Branch</span></span><span>{{ $order->location?->name }}</span></div>
<div class="line"><span class="bi-label">نوع الطلب / <span class="en" lang="en">Service</span></span><span class="service-value">{{ $service }} / <span class="en" lang="en">{{ $serviceEn }}</span></span></div>
@if($order->restaurantTable)<div class="line"><span class="bi-label">الطاولة / <span class="en" lang="en">Table</span></span><span>{{ $order->restaurantTable->displayName() }}</span></div>@endif
<div class="date-grid">
    <div class="date-cell"><span class="date-label">اليوم / Day</span><strong class="date-value">{{ $invoiceAt ? ($weekdays[$invoiceAt->format('l')] ?? '—') : '—' }}</strong><span class="en" lang="en">{{ $invoiceAt?->format('l') ?? '—' }}</span></div>
    <div class="date-cell"><span class="date-label">التاريخ / Date</span><bdi class="date-value">{{ $invoiceAt?->format('d/m/Y') ?? '—' }}</bdi></div>
    <div class="date-cell"><span class="date-label">الوقت / Time</span><bdi class="date-value">{{ $invoiceAt?->format('H:i') ?? '—' }}</bdi><span class="en time-zone">Gaza / غزة</span></div>
</div>
</div>
<table><thead><tr><th>الصنف <span class="en" lang="en">Item</span></th><th>عدد <span class="en" lang="en">Qty</span></th><th class="amount">السعر <span class="en" lang="en">Price</span></th><th class="amount">مجموع <span class="en" lang="en">Total</span></th></tr></thead><tbody>
@foreach($invoice->items as $item)
@php($description = $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف'))
<tr><td>{{ $description }}@if($alternate = $otherName($description, $item->product))<span class="item-alt"><span class="item-separator" aria-hidden="true"> / </span><bdi>{{ $alternate }}</bdi></span>@endif</td><td>{{ $qty($item->quantity) }}</td><td class="amount">{{ $money($item->unit_price) }}</td><td class="amount">{{ $money($item->line_total) }}</td></tr>@endforeach
</tbody></table>
<div class="summary">
@if((float)$invoice->discount_amount>0 || (float)$invoice->tax_amount>0 || $money($invoice->subtotal) !== $money($invoice->total_amount))
<div class="line"><span class="bi-label">المجموع الفرعي / <span class="en" lang="en">Subtotal</span></span><bdi>{{ $money($invoice->subtotal) }}</bdi></div>
@endif
@if((float)$invoice->discount_amount>0)<div class="line discount-line"><span class="bi-label">الخصم / <span class="en" lang="en">Discount</span></span><bdi>-{{ $money($invoice->discount_amount) }} {{ $currency }}</bdi></div>@endif
@if((float)$invoice->tax_amount>0)<div class="line"><span class="bi-label">الضريبة / <span class="en" lang="en">Tax</span></span><bdi>{{ $money($invoice->tax_amount) }}</bdi></div>@endif
<div class="line total"><span class="bi-label">الإجمالي / <span class="en" lang="en">Grand total</span></span><bdi>{{ $money($invoice->total_amount) }} {{ $currency }}</bdi></div>
@if(($refund['refunded'] ?? 0)>0)<div class="line"><span class="bi-label">المسترد / <span class="en" lang="en">Refunded</span></span><bdi>{{ $money($refund['refunded']) }}</bdi></div>@endif
<div class="line"><span class="bi-label">صافي المدفوع / <span class="en" lang="en">Net paid</span></span><bdi>{{ $money($invoice->paid_amount) }}</bdi></div>
@if($invoice->isActive() && (float)$invoice->remaining_amount>0)<div class="line"><span class="bi-label">المتبقي / <span class="en" lang="en">Balance due</span></span><bdi>{{ $money($invoice->remaining_amount) }}</bdi></div>@endif
@foreach($order->payments as $payment)
@if(($payment->status instanceof \BackedEnum ? $payment->status->value : $payment->status)==='confirmed')
<div class="line"><span>{{ $payment->paymentMethod?->name_ar ?: $payment->paymentMethod?->name }}@if($payment->paymentMethod?->name_ar && $payment->paymentMethod?->name && $payment->paymentMethod->name !== $payment->paymentMethod->name_ar)<span class="en"><bdi>{{ $payment->paymentMethod->name }}</bdi></span>@endif</span><bdi>{{ $money($payment->amount) }}</bdi></div>
@endif
@endforeach
</div>
<div class="status">{{ $paymentLabel }} / <span class="en" lang="en">{{ $paymentEn }}</span></div><p class="center receipt-footer">{{ $theme['footer_text'] ?: 'شكرًا لاختياركم TISH' }}@if(empty($theme['footer_text']))<span class="en" lang="en">Thank you for choosing TISH</span>@endif</p>
<div class="social-footer">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
    <span lang="en" dir="ltr">Instagram</span>
    <a href="https://www.instagram.com/letstish/" aria-label="TISH Instagram"><bdi dir="ltr">&#64;letstish</bdi></a>
</div>
</section>
@endif
@if($tickets->isNotEmpty())
<section class="receipt kitchen-copy" data-document="kitchen" data-density="{{ $kitchenDensity }}">
    @if($logo)<img class="logo" src="{{ $logo }}" alt="TISH">@endif
    <h1>{{ $theme['business_name'] ?: 'TISH' }}</h1>
    <p class="center kitchen-title">تذكرة المطبخ / <span class="en" lang="en">Kitchen Ticket</span></p>
    <div class="meta kitchen-meta">
        <div class="line kitchen-order"><span class="bi-label">الطلب / <span class="en" lang="en">Order</span></span><bdi>{{ $order->order_number }}</bdi></div>
        <div class="line"><span class="bi-label">الفرع / <span class="en" lang="en">Branch</span></span><span>{{ $order->location?->name }}</span></div>
        <div class="date-grid">
    <div class="date-cell"><span class="date-label">اليوم / Day</span><strong class="date-value">{{ $kitchenAt ? ($weekdays[$kitchenAt->format('l')] ?? '—') : '—' }}</strong><span class="en" lang="en">{{ $kitchenAt?->format('l') ?? '—' }}</span></div>
    <div class="date-cell"><span class="date-label">التاريخ / Date</span><bdi class="date-value">{{ $kitchenAt?->format('d/m/Y') ?? '—' }}</bdi></div>
    <div class="date-cell"><span class="date-label">الوقت / Time</span><bdi class="date-value">{{ $kitchenAt?->format('H:i') ?? '—' }}</bdi><span class="en time-zone">Gaza / غزة</span></div>
</div>
    </div>
    <div class="kitchen-service center">
        <strong>{{ $service }} / <span class="en" lang="en">{{ $serviceEn }}</span></strong>
        @if($order->restaurantTable)<span>الطاولة / Table: {{ $order->restaurantTable->displayName() }}</span>@endif
    </div>
    @foreach($tickets as $ticket)
        <div class="kitchen-station">
            <h2>{{ $ticket->station?->name ?? 'المطبخ' }}<span class="en" lang="en">Kitchen station</span></h2>
            <div class="line kitchen-station-meta"><bdi>{{ $ticket->ticket_number }}</bdi><span>{{ $ticket->status?->label() }}<span class="en" lang="en">{{ $kitchenStatusEn[$ticket->status?->value ?? ''] ?? '' }}</span></span></div>
        </div>
        <table class="kitchen-table">
            <colgroup><col><col class="kitchen-qty-col"></colgroup>
            <thead><tr><th scope="col">الصنف <span class="en" lang="en">Item</span></th><th scope="col" class="kitchen-qty">عدد <span class="en" lang="en">Qty</span></th></tr></thead>
            <tbody>
            @foreach($ticket->items as $item)
                @php($cancelled = $item->status === \App\Enums\KitchenTicketStatus::CANCELLED)
                <tr class="kitchen-item">
                    <td>
                        <strong class="kitchen-name {{ $cancelled ? 'cancelled' : '' }}">{{ $item->product_name }}</strong>@if($alternate = $otherName($item->product_name, $item->product))<span class="item-alt {{ isset($cancelled) && $cancelled ? 'cancelled' : '' }}"><span class="item-separator" aria-hidden="true"> / </span><bdi>{{ $alternate }}</bdi></span>@endif
                        @if($cancelled)<div class="kitchen-cancel">ملغي — لا يُحضّر<span class="en" lang="en">Cancelled — do not prepare</span></div>@endif
                        @if($item->kitchen_notes)<div class="kitchen-notes"><strong>ملاحظات / إضافات <span class="en" lang="en">Notes / Add-ons</span></strong><div>{{ $item->kitchen_notes }}</div></div>@endif
                    </td>
                    <td class="kitchen-qty {{ $cancelled ? 'cancelled' : '' }}"><bdi>{{ $qty($item->quantity) }}</bdi></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
    <div class="kitchen-footer center">نسخة المطبخ — ليست فاتورة<span class="en" lang="en">Kitchen copy — not an invoice</span></div>
</section>
@endif
<script>
(()=>{const print=async mode=>{document.body.dataset.mode=mode;await(document.fonts?.ready||Promise.resolve());await Promise.all(Array.from(document.images).map(img=>img.decode?.().catch(()=>{})||Promise.resolve()));window.print();};document.querySelectorAll('[data-print]').forEach(b=>b.addEventListener('click',()=>print(b.dataset.print)));window.addEventListener('afterprint',()=>{document.body.dataset.mode='all'});if(!new URLSearchParams(location.search).has('preview'))window.addEventListener('load',()=>print('all'),{once:true});})();
</script></body></html>

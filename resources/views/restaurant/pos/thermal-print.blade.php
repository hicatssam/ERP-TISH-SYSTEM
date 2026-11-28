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
    $service = $order->restaurant_service_type?->label() ?? 'طلب مطعم';
@endphp
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>طباعة {{ $order->order_number }}</title>
@include('layouts.print.fonts')
<style>
*{box-sizing:border-box}body{margin:0;background:#eee;color:#111;font:12px Cairo,Arial,sans-serif}.toolbar{padding:10px;display:flex;flex-wrap:wrap;justify-content:center;gap:8px;background:white}.toolbar button,.toolbar a{font:inherit;padding:8px;border:1px solid #888;background:white;color:#111;border-radius:4px;text-decoration:none;cursor:pointer}.receipt{width:{{ $paperWidth-8 }}mm;max-width:100%;margin:12px auto;padding:2mm;background:white;line-height:1.55;overflow-wrap:anywhere}h1,h2,p{margin:0}h1{text-align:center;font-size:17px}h2{font-size:14px;margin-top:9px}.center{text-align:center}.logo{display:block;max-width:30mm;max-height:18mm;margin:0 auto 5px;filter:grayscale(1)}.meta{border-block:1px dashed;padding:5px 0;margin:7px 0}.number{text-align:center;font-size:22px;font-weight:bold}table{width:100%;border-collapse:collapse;font-size:11px}td,th{padding:5px 1px;border-bottom:1px dotted #888;text-align:right;vertical-align:top}.amount{text-align:left;direction:ltr;white-space:nowrap}.line{display:flex;justify-content:space-between;gap:5px}.total{border-top:2px solid;font-size:15px;font-weight:bold;padding-top:5px}.status{border:1px solid;text-align:center;font-weight:bold;margin:8px 0;padding:4px}.kitchen-item{border-bottom:1px dashed;padding:6px 0;break-inside:avoid}.kitchen-item .line{font-size:15px;font-weight:bold}.note{font-weight:bold}.cancelled{text-decoration:line-through}
@page{size:auto;margin:0}
@media print{body{background:white}.toolbar{display:none!important}.receipt{margin:0 auto;max-width:none}.receipt+.receipt{break-before:page;page-break-before:always}body[data-mode="customer"] .kitchen-copy,body[data-mode="kitchen"] .customer-copy{display:none}body[data-mode="kitchen"] .kitchen-copy{break-before:auto;page-break-before:auto}}
</style></head><body data-mode="all">
<div class="toolbar"><button data-print="all">طباعة المستندات</button>
@if($invoice && $tickets->isNotEmpty())<button data-print="customer">فاتورة العميل فقط</button><button data-print="kitchen">المطبخ فقط</button>@endif
<a href="{{ request()->fullUrlWithQuery(['width'=>$paperWidth === 80 ? 58 : 80,'preview'=>1]) }}">{{ $paperWidth === 80 ? '58 mm' : '80 mm' }}</a><button onclick="window.close()">إغلاق</button></div>
@if($invoice)
<section class="receipt customer-copy" data-document="invoice">
@if($logo)<img class="logo" src="{{ $logo }}" alt="TISH">@endif
<h1>{{ $theme['business_name'] ?: 'TISH' }}</h1><p class="center">فاتورة العميل</p>
<div class="meta"><div>الطلب: <bdi>{{ $order->order_number }}</bdi></div><div>الفاتورة: <bdi>{{ $invoice->invoice_number }}</bdi></div><div>{{ $order->location?->name }} · {{ $service }}</div>
@if($order->restaurantTable)<div>الطاولة: {{ $order->restaurantTable->displayName() }}</div>@endif
<div>{{ ($invoice->issued_at ?? $invoice->created_at)?->format('d/m/Y H:i') }}</div></div>
<table><thead><tr><th>الصنف</th><th>عدد</th><th class="amount">السعر</th><th class="amount">المجموع</th></tr></thead><tbody>
@foreach($invoice->items as $item)<tr><td>{{ $item->description ?: ($item->product?->name_ar ?: $item->product?->name ?: 'صنف') }}</td><td>{{ $qty($item->quantity) }}</td><td class="amount">{{ $money($item->unit_price) }}</td><td class="amount">{{ $money($item->line_total) }}</td></tr>@endforeach
</tbody></table>
<div class="line"><span>المجموع الفرعي</span><bdi>{{ $money($invoice->subtotal) }}</bdi></div>
@if((float)$invoice->discount_amount>0)<div class="line"><span>الخصم</span><bdi>-{{ $money($invoice->discount_amount) }}</bdi></div>@endif
@if((float)$invoice->tax_amount>0)<div class="line"><span>الضريبة</span><bdi>{{ $money($invoice->tax_amount) }}</bdi></div>@endif
<div class="line total"><span>الإجمالي</span><bdi>{{ $money($invoice->total_amount) }} {{ $currency }}</bdi></div>
@if(($refund['refunded'] ?? 0)>0)<div class="line"><span>المسترد</span><bdi>{{ $money($refund['refunded']) }}</bdi></div>@endif
<div class="line"><span>صافي المدفوع</span><bdi>{{ $money($invoice->paid_amount) }}</bdi></div>
@if($invoice->isActive() && (float)$invoice->remaining_amount>0)<div class="line"><span>المتبقي</span><bdi>{{ $money($invoice->remaining_amount) }}</bdi></div>@endif
@foreach($order->payments as $payment)
@if(($payment->status instanceof \BackedEnum ? $payment->status->value : $payment->status)==='confirmed')
<div class="line"><span>{{ $payment->paymentMethod?->name_ar ?: $payment->paymentMethod?->name }}</span><bdi>{{ $money($payment->amount) }}</bdi></div>
@endif
@endforeach
<div class="status">{{ $paymentLabel }}</div><p class="center">{{ $theme['footer_text'] ?: 'شكرًا لاختياركم TISH' }}</p>
</section>
@endif
@if($tickets->isNotEmpty())
<section class="receipt kitchen-copy" data-document="kitchen"><h1>تذكرة المطبخ</h1><div class="number"><bdi>{{ $order->order_number }}</bdi></div>
<div class="meta"><div>{{ $order->location?->name }} · {{ $service }}</div>@if($order->restaurantTable)<div>الطاولة: {{ $order->restaurantTable->displayName() }}</div>@endif<div>{{ ($order->confirmed_at ?? $order->created_at)?->format('d/m/Y H:i') }}</div></div>
@foreach($tickets as $ticket)
<h2>{{ $ticket->station?->name ?? 'المطبخ' }} · <bdi>{{ $ticket->ticket_number }}</bdi></h2><p>{{ $ticket->status?->label() }}</p>
@foreach($ticket->items as $item)
<div class="kitchen-item"><div class="line {{ $item->status === \App\Enums\KitchenTicketStatus::CANCELLED ? 'cancelled' : '' }}"><strong>{{ $item->product_name }}</strong><bdi>× {{ $qty($item->quantity) }}</bdi></div>
@if($item->status === \App\Enums\KitchenTicketStatus::CANCELLED)<p class="note">ملغي — لا يُحضّر</p>@endif
@if($item->kitchen_notes)<p class="note">{{ $item->kitchen_notes }}</p>@endif</div>
@endforeach
@endforeach
<p class="center">نسخة المطبخ — ليست فاتورة</p></section>
@endif
<script>
(()=>{const print=async mode=>{document.body.dataset.mode=mode;await(document.fonts?.ready||Promise.resolve());await Promise.all(Array.from(document.images).map(img=>img.decode?.().catch(()=>{})||Promise.resolve()));window.print();};document.querySelectorAll('[data-print]').forEach(b=>b.addEventListener('click',()=>print(b.dataset.print)));window.addEventListener('afterprint',()=>{document.body.dataset.mode='all'});if(!new URLSearchParams(location.search).has('preview'))window.addEventListener('load',()=>print('all'),{once:true});})();
</script></body></html>

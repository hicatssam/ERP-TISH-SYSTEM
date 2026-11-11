{{-- Preserve legacy invoice rendering when the view is included without a public order. --}}
@if(isset($order) && isset($branding))
@php
    /** Public customer invoice: live accounting values, no mocked totals. */
    $printSettings = app(\App\Services\PrintThemeService::class)->settings();
    $currency = $invoice->currency?->symbol ?? $printSettings['currency_symbol'] ?? '₪';
    $number = $invoice->invoice_number ?? $invoice->number ?? ('INV-' . $invoice->id);
    $issuedAt = $invoice->issued_at ?? now();
    $businessName = $branding['name'] ?? $printSettings['business_name'] ?? 'Tish Restaurant';
    // Match the real torn-paper TISH emblem used in the menu and PNG export.
    $logo = asset('assets/tish-paper/brand/logo-disc.webp');
    $branchName = $invoice->location?->name ?? $order->location?->name ?? '—';

    $lines = $invoice->items->map(fn ($row) => [
        'description' => $row->description ?: ($row->product?->name_ar ?: $row->product?->name ?: 'صنف'),
        'quantity' => (float) $row->quantity,
        'unit_price' => (float) $row->unit_price,
        'line_total' => (float) $row->line_total,
    ]);

    if ($lines->isEmpty()) {
        $lines = $order->items->map(fn ($row) => [
            'description' => $row->product_name ?: 'صنف',
            'quantity' => (float) $row->quantity,
            'unit_price' => (float) $row->unit_price,
            'line_total' => (float) $row->line_total,
        ]);
    }

    $subtotal = (float) $invoice->subtotal;
    $discount = (float) ($invoice->discount_amount ?? 0);
    $tax = (float) ($invoice->tax_amount ?? 0);
    $total = (float) $invoice->total_amount;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>فاتورة رقم {{ $number }} — {{ $businessName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-invoice-v2.css') }}?v=3">
</head>
<body class="tish-customer-invoice">
<div class="tish-invoice-toolbar" aria-label="إجراءات الفاتورة">
 <a href="{{ route('customer-menu.track', ['token' => $order->public_token]) }}">تتبع الطلب</a>
 <a href="{{ route('customer-menu.show', $order->location->code) }}">المنيو</a>
 <button type="button" onclick="window.print()">طباعة الفاتورة</button>
</div>
<main class="tish-invoice-paper" aria-label="فاتورة طلب TISH">
 <header class="tish-invoice-header">
  <div class="tish-invoice-brand">
   <img class="tish-invoice-logo" src="{{ $logo }}" alt="{{ $businessName }}">
   <div class="tish-invoice-brand-copy">
    <strong>{{ $businessName }}</strong>
    <span>{{ $branchName }}</span>
   </div>
  </div>
  <div class="tish-invoice-heading">
   <img class="tish-invoice-bolt" src="{{ asset('assets/tish-paper/decor/lightning.svg') }}" alt="" aria-hidden="true">
   <h1>فاتورة رقم <bdi dir="ltr">{{ $number }}</bdi></h1>
  </div>
  <div class="tish-invoice-meta">
   <span dir="ltr">{{ $number }}</span>
   <span aria-hidden="true">|</span>
   <time dir="ltr" datetime="{{ $issuedAt->format('Y-m-d\TH:i:s') }}">{{ $issuedAt->format('Y-m-d H:i') }}</time>
  </div>
  <p class="tish-invoice-order-reference">رقم الطلب: <bdi dir="ltr">{{ $order->order_number }}</bdi></p>
 </header>

 <section class="tish-invoice-items" aria-label="الأصناف">
  <table>
   <thead class="tish-visually-hidden">
    <tr><th>الصنف</th><th>سعر الوحدة والكمية</th></tr>
   </thead>
   <tbody>
   @forelse($lines as $line)
    <tr>
     <td class="tish-invoice-item-title">{{ $line['description'] }}</td>
     <td class="tish-invoice-item-money">
      <span dir="ltr">{{ $currency }} {{ number_format($line['unit_price'], 2) }} <span class="tish-invoice-multiply">× {{ number_format($line['quantity'], 3) }}</span></span>
     </td>
    </tr>
   @empty
    <tr><td class="tish-invoice-no-items" colspan="2">لا توجد أصناف في الفاتورة.</td></tr>
   @endforelse
   </tbody>
  </table>
 </section>

 <section class="tish-invoice-totals" aria-label="ملخص الفاتورة">
  <div class="tish-invoice-total-row">
   <strong>المجموع الفرعي:</strong>
   <span dir="ltr">{{ $currency }} {{ number_format($subtotal, 2) }}</span>
  </div>
  <div class="tish-invoice-total-row">
   <strong>الخصم:</strong>
   <span dir="ltr">{{ $currency }} {{ number_format($discount, 2) }}</span>
  </div>
  <div class="tish-invoice-total-row">
   <strong>الضريبة:</strong>
   <span dir="ltr">{{ $currency }} {{ number_format($tax, 2) }}</span>
  </div>
  <div class="tish-invoice-grand">
   <strong>الإجمالي:</strong>
   <span dir="ltr">{{ $currency }} {{ number_format($total, 2) }}</span>
  </div>
 </section>
 @if($invoice->notes)
  <p class="tish-invoice-notes">{{ $invoice->notes }}</p>
 @endif
</main>
<script>
window.addEventListener('load', function () {
  // Preserve the legacy public invoice auto-print behavior.
  const ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  ready.then(function () {
    Promise.all(Array.from(document.images).map(function (image) {
      return image.decode ? image.decode().catch(function () {}) : Promise.resolve();
    })).then(function () { window.print(); });
  });
});
</script>
</body>
</html>
@else
@include('finance.invoices.print')
@endif

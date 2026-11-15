@php($menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'])
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>سلة الطلب - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v2.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v3.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v4.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-summary-v5.css') }}?v=5">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-polish-v13.css') }}?v=17">
@include('customer-menu.partials.pwa-head')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-drive-ui-fixes-v1.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-ux-v2.css') }}?v=2">
</head>
<body class="crisp-customer-menu tish-paper tish-cart-page">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'سلة الطلب', 'pageSubtitle' => $location->name])

<main class="menu-area">
 <div class="shell">
  <div class="tish-cart-items-heading" id="cartItemsHeading" hidden><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><h2>منتجات السلة</h2></div>
  <div id="cartItems" aria-live="polite"></div>

  <div class="order-summary" aria-label="ملخص سلة الطلب">
   <div class="summary-row"><span>عدد الأصناف</span><span id="itemCount">0</span></div>
   <div class="cart-total"><strong>الإجمالي</strong><strong id="cartTotal" dir="ltr">0.00 {{ $menuCurrencySymbol }}</strong></div>
   <p class="cart-note">قد تُضاف رسوم توصيل حسب نوع الطلب.<span class="tish-note-next-step">تظهر بعد اختيار طريقة الاستلام في الخطوة التالية.</span></p>
  </div>
  <button class="checkout-start" id="checkoutStart" type="button" disabled>إتمام الطلب</button>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;

function renderCart() {
    const rows = CM.cartRows();
    document.getElementById('cartItems').innerHTML = rows.length
        ? rows.map(x => {
            const modifierLine = (x.modifiers || []).length
                ? x.modifiers.map(m => `${m.name}${m.quantity > 1 ? ' ×' + m.quantity : ''}`).join('، ')
                : '';
            const subtitle = [x.variant_name, modifierLine].filter(Boolean).join(' — ');
            return `
          <article class="cart-row" aria-label="صنف في سلة الطلب">
            <span class="tish-cart-row-index" aria-hidden="true"></span>
            <div class="cart-thumb">${x.image ? `<img src="${CM.esc(x.image)}" data-fallback="${CM.esc(x.original_image && x.original_image !== x.image ? x.original_image : '')}" alt="" onerror="if(this.dataset.fallback){const f=this.dataset.fallback;this.dataset.fallback='';this.src=f;}else{this.style.display='none';}">` : ''}</div>
            <div class="row-copy">
              <strong>${CM.esc(x.name.split(' - ')[0])}</strong>
              ${subtitle ? `<span style="display:block;font-size:.74rem;color:var(--muted)">${CM.esc(subtitle)}</span>` : ''}
              <small class="tish-cart-line-price">${CM.money(x.price * x.quantity)}</small>
            </div>
            <div class="row-actions">
              <button type="button" data-dec="${CM.esc(x.key)}" aria-label="تقليل كمية ${CM.esc(x.name)}">−</button>
              <b>${x.quantity}</b>
              <button type="button" data-inc="${CM.esc(x.key)}" aria-label="زيادة كمية ${CM.esc(x.name)}">+</button>
              <button type="button" data-remove="${CM.esc(x.key)}" aria-label="حذف ${CM.esc(x.name)}"><i class="fa-solid fa-trash"></i></button>
            </div>
          </article>`;
        }).join('')
        : `<section class="tish-cart-empty-paper" aria-label="السلة فارغة">
          <div class="tish-cart-bag-art" aria-hidden="true">
            <img class="tish-cart-bag-illustration" src="{{ asset('assets/tish-paper/decor/kraft-cart-bag.webp') }}" width="274" height="336" alt="" loading="eager">
            <span class="tish-cart-spark spark-one"></span><span class="tish-cart-spark spark-two"></span>
          </div>
          <h2 class="tish-cart-empty-title">السلة فارغة</h2>
          <a class="tish-cart-browse" href="${@json(route('customer-menu.products', $location->code))}">تصفح المنيو</a>
        </section>`;

    document.getElementById('cartItemsHeading').hidden = !rows.length;
    document.getElementById('itemCount').textContent = rows.reduce((s, r) => s + Number(r.quantity), 0);
    document.getElementById('cartTotal').textContent = CM.money(CM.cartTotal());
    document.getElementById('checkoutStart').disabled = !rows.length;
}

document.addEventListener('click', e => {
    const inc = e.target.closest('[data-inc]'); if (inc) { CM.changeCart(inc.dataset.inc, 1); renderCart(); return; }
    const dec = e.target.closest('[data-dec]'); if (dec) { CM.changeCart(dec.dataset.dec, -1); renderCart(); return; }
    const rm = e.target.closest('[data-remove]'); if (rm) { CM.removeFromCart(rm.dataset.remove); renderCart(); return; }
});

document.getElementById('checkoutStart').addEventListener('click', () => {
    if (!CM.cartRows().length) return;
    window.location.href = @json(route('customer-menu.checkout', $location->code));
});

renderCart();
</script>
</body>
</html>

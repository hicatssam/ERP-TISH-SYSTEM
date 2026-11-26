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




<style>
/* TISH-CART-EXACT-PAPER-V1 */

/* Full-sized paper card */
html body #cartItems {
  display:grid !important;
  grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),280px)) !important;
  justify-content:center !important;
  gap:22px !important;
}

html body #cartItems .cart-row {
  position:relative !important;
  box-sizing:border-box !important;
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  width:280px !important;
  max-width:100% !important;
  min-height:460px !important;
  padding:24px 22px 48px !important;
  margin:0 !important;
  border:0 !important;
  border-radius:0 !important;
  overflow:visible !important;
  background:transparent url('/assets/tish-paper/paper/category-ivory.webp')
    center/100% 100% no-repeat !important;
}

html body #cartItems .cart-row::before {
  content:none !important;
}

html body #cartItems .cart-thumb {
  display:grid !important;
  place-items:center !important;
  flex:0 0 145px !important;
  width:145px !important;
  height:145px !important;
  min-width:145px !important;
  min-height:145px !important;
  margin:12px auto 18px !important;
  padding:10px !important;
  border:0 !important;
  background:transparent url('/assets/tish-paper/paper/product-disc.webp')
    center/100% 100% no-repeat !important;
}

html body #cartItems .cart-thumb img {
  width:100% !important;
  height:100% !important;
  object-fit:contain !important;
}

html body #cartItems .row-copy {
  width:100% !important;
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  gap:18px !important;
  text-align:center !important;
  margin:0 !important;
  padding:0 !important;
  background:none !important;
}

html body #cartItems .row-copy > strong {
  font-size:20px !important;
  font-weight:900 !important;
  line-height:1.25 !important;
  text-align:center !important;
}

html body #cartItems .tish-cart-line-price {
  display:grid !important;
  place-items:center !important;
  width:175px !important;
  min-height:52px !important;
  margin:0 auto !important;
  padding:8px !important;
  color:#fffaf1 !important;
  font-size:21px !important;
  font-weight:900 !important;
  background:transparent url('/assets/tish-paper/paper/nav-active.webp')
    center/100% 100% no-repeat !important;
}

/* A real control row with no transformed parent */
html body #cartItems .row-actions {
  position:static !important;
  display:flex !important;
  flex-direction:row !important;
  align-items:center !important;
  justify-content:center !important;
  gap:14px !important;
  width:100% !important;
  margin:auto 0 0 !important;
  padding:0 !important;
  transform:none !important;
  filter:none !important;
  perspective:none !important;
  background:none !important;
}

/* Remove the inherited oval appearance completely */
html body #cartItems .row-actions button[data-inc],
html body #cartItems .row-actions button[data-dec],
html body #cartItems button[data-remove] {
  position:relative;
  isolation:isolate !important;
  display:grid !important;
  place-items:center !important;
  flex:0 0 55px !important;
  width:55px !important;
  height:55px !important;
  min-width:55px !important;
  min-height:55px !important;
  margin:0 !important;
  padding:0 !important;
  border:0 !important;
  border-radius:0 !important;
  box-shadow:none !important;
  clip-path:none !important;
  background:none !important;
  transform:none !important;
  overflow:visible !important;
  font:900 29px/1 Arial,sans-serif !important;
  color:#aa211b !important;
  cursor:pointer;
}

/* Actual torn paper shapes, independent of button borders */
html body #cartItems .row-actions button[data-inc]::before,
html body #cartItems .row-actions button[data-dec]::before,
html body #cartItems button[data-remove]::before {
  content:"" !important;
  position:absolute !important;
  inset:0 !important;
  z-index:-1 !important;
  display:block !important;
  width:100% !important;
  height:100% !important;
  border-radius:0 !important;
  background:#f5e3cb url('/assets/tish-paper/paper/category-ivory.webp')
    center/100% 100% no-repeat !important;
  clip-path:polygon(0 6%,19% 2%,37% 5%,56% 0,77% 4%,100% 2%,97% 30%,100% 54%,96% 82%,100% 96%,75% 99%,53% 95%,31% 100%,0 96%,4% 68%,0 39%) !important;
  filter:drop-shadow(0 3px 3px #46251d38);
}

/* Red paper plus and delete */
html body #cartItems .row-actions button[data-inc],
html body #cartItems button[data-remove] {
  color:#fffaf1 !important;
}

html body #cartItems .row-actions button[data-inc]::before,
html body #cartItems button[data-remove]::before {
  background:#af2019 url('/assets/tish-paper/paper/nav-active.webp')
    center/100% 100% no-repeat !important;
}

/* Quantity */
html body #cartItems .row-actions b,
html body #cartItems .row-actions .tish-cart-qty {
  display:grid !important;
  place-items:center !important;
  flex:0 0 32px !important;
  min-width:32px !important;
  height:55px !important;
  margin:0 !important;
  padding:0 !important;
  border:0 !important;
  border-radius:0 !important;
  background:none !important;
  box-shadow:none !important;
  color:#382019 !important;
  font-size:27px !important;
  font-weight:900 !important;
}

/* Delete at upper left; supports both old and new HTML */
html body #cartItems button[data-remove] {
  position:absolute !important;
  inset:auto !important;
  top:9px !important;
  left:9px !important;
  z-index:10 !important;
}

html body #cartItems button[data-remove] i {
  color:#fffaf1 !important;
  font-family:"Font Awesome 6 Free" !important;
  font-weight:900 !important;
  font-size:20px !important;
}

html body #cartItems button:focus-visible {
  outline:3px solid #af2019 !important;
  outline-offset:4px !important;
}

@media(max-width:600px) {
  html body #cartItems .cart-row {
    min-height:430px !important;
  }
}
</style>

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

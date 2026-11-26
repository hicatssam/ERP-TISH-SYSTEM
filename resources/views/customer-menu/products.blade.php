@php($menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'])
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>المنيو - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-reference-v3.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-card-fix-v4.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-circle-v5.css') }}?v=5">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-product-circle-card-v8.css') }}?v=9">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-product-layout-v10.css') }}?v=12">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-motion-v10.css') }}?v=12">
@include('customer-menu.partials.pwa-head')
<style>
@keyframes dahabMenuCardIn {from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.crisp-customer-menu .product.cm-card-motion {animation:dahabMenuCardIn .48s cubic-bezier(.2,.75,.2,1) both;animation-delay:var(--cm-delay,0ms)}
.crisp-customer-menu .cat {transition:transform .25s ease}
.crisp-customer-menu .cat:active{transform:scale(.95)}
.crisp-customer-menu .details:active{transform:scale(.96)}
@media(prefers-reduced-motion:reduce){.crisp-customer-menu .product.cm-card-motion{animation:none!important}.crisp-customer-menu .cat{transition:none!important}}
</style>
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-drive-ui-fixes-v1.css') }}?v=1">







<style>
/* TISH-SEARCH-ORIGINAL-PAPER-V4 */

body.tish-paper .tools .search {
    position: relative !important;
    isolation: isolate !important;
    display: flex !important;
    align-items: center !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    height: 54px !important;
    background: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    filter: none !important;
    clip-path: none !important;
}

/* Original TISH paper, cropped before the dark bottom edge */
body.tish-paper .tools .search::before {
    content: "" !important;
    display: block !important;
    position: absolute !important;
    inset: 0 !important;
    z-index: 0 !important;
    pointer-events: none !important;
    opacity: 1 !important;

    background-image:
        url('/assets/tish-paper/paper/category-ivory.webp') !important;
    background-repeat: no-repeat !important;
    background-position: center top !important;
    background-size: 100% 175% !important;

    clip-path: polygon(
        0 6%, 8% 3%, 18% 5%, 29% 2%,
        42% 4%, 55% 1%, 68% 4%,
        81% 2%, 94% 4%, 100% 6%,
        100% 94%, 94% 96%, 84% 94%,
        74% 98%, 62% 95%, 49% 98%,
        37% 95%, 25% 98%, 14% 95%,
        5% 97%, 0 94%
    ) !important;

    border: 0 !important;
    box-shadow: none !important;
}

body.tish-paper .tools .search::after {
    content: none !important;
    display: none !important;
}

body.tish-paper .tools .search input,
body.tish-paper.tish-paper-products .tools .search input,
body.tish-paper.reference-home .tools .search input {
    position: relative !important;
    z-index: 1 !important;
    box-sizing: border-box !important;
    width: 100% !important;
    height: 54px !important;
    min-height: 54px !important;
    margin: 0 !important;
    padding: 0 53px 0 20px !important;

    background: none !important;
    background-image: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    filter: none !important;

    color: #3d2b20 !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    text-align: start !important;
    direction: ltr;
}

body.tish-paper .tools .search input:focus,
body.tish-paper .tools .search input:focus-visible {
    outline: 0 !important;
    border: 0 !important;
    box-shadow: none !important;
}

body.tish-paper .tools .search input::placeholder {
    color: #806b55 !important;
    opacity: 1 !important;
}

body.tish-paper .tools .search > i {
    position: absolute !important;
    z-index: 2 !important;
    top: 50% !important;
    right: 19px !important;
    left: auto !important;
    transform: translateY(-50%) !important;
    translate: none !important;
    margin: 0 !important;
    font-size: 19px !important;
    line-height: 1 !important;
    pointer-events: none !important;
    color: #35271e !important;
}

html[dir="rtl"] body.tish-paper .tools .search input {
    direction: rtl !important;
    padding: 0 20px 0 53px !important;
}

html[dir="rtl"] body.tish-paper .tools .search > i {
    left: 19px !important;
    right: auto !important;
}

body.tish-paper .tools .search input::-webkit-search-cancel-button,
body.tish-paper .tools .search input::-webkit-search-decoration {
    display: none;
    -webkit-appearance: none;
}

body.tish-paper .tools .search:focus-within::before {
    filter: brightness(1.035);
}

@media (max-width:480px) {
    body.tish-paper .tools .search,
    body.tish-paper .tools .search input {
        height: 52px !important;
        min-height: 52px !important;
    }
}
</style>

</head>
<body class="crisp-customer-menu tish-paper tish-paper-products">
@include('customer-menu.partials.brand-watermark')
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'المنيو الكامل', 'pageSubtitle' => $location->name])

<main class="menu-area" id="menu">
 <div class="shell">
  <div class="tish-paper-products-header">
   <div class="tish-paper-products-hero-row">
    <div class="tish-paper-products-brand">
     <img src="{{ asset('assets/tish-paper/brand/telephone.webp') }}" alt="" class="tish-paper-products-booth">
     <div class="tish-paper-products-brandline">
      <img src="{{ asset('assets/tish-paper/brand/slogan.svg') }}" alt="Hear it, Feel it!" class="tish-paper-products-slogan">
      <button type="button" class="tish-paper-products-pin" id="tishPaperPin" aria-label="ثبّت المنيو"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> ثبّت المنيو</button>
     </div>
    </div>
    <img src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="{{ $branding['name'] ?? 'Tish' }}" class="tish-paper-products-logo">
   </div>
   <div class="tish-paper-products-title">
    <img src="{{ asset('assets/tish-paper/decor/lightning.svg') }}" alt="" aria-hidden="true">
    <h1>كل الأصناف</h1>
   </div>
  </div>
  <div class="tools">
   <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="{{ \App\Support\CustomerMenuCopy::searchPlaceholder() }}" autocomplete="off" value="{{ $initialSearch }}"></div>
   <button class="filter-square" type="button" id="openFilters" aria-label="تصفية الأصناف"><i class="fa-solid fa-sliders"></i></button>
  </div>

  <div class="categories" id="categories">
   <button class="cat {{ $initialCategory === 'all' || $initialCategory === '' ? 'active' : '' }}" type="button" data-cat="all"><b>الكل</b></button>
   @foreach(($categories ?? []) as $category)
    <button class="cat {{ (string) $initialCategory === (string) $category['id'] ? 'active' : '' }}" type="button" data-cat="{{ $category['id'] }}">
     @if(!empty($category['image']))<img src="{{ $category['image'] }}" alt="" onerror="this.remove()">@endif
     <b>{{ $category['name'] }}</b>
    </button>
   @endforeach
  </div>

  <div class="section-title"><div><h2>اختر صنفك المفضل</h2></div><small id="resultCount" style="color:var(--muted);font-size:.8rem"></small></div>
  <div class="grid" id="productGrid"></div>
 </div>
</main>
</div>

<div class="filter-sheet-overlay" id="filterOverlay">
 <div class="filter-sheet">
  <div class="filter-sheet-head">
   <button class="close-btn" type="button" id="closeFilters"><i class="fa-solid fa-xmark"></i></button>
   <h2>تصفية الأصناف</h2>
   <button type="button" id="resetFilters">إعادة ضبط</button>
  </div>

  <div class="filter-group">
   <h4>نطاق السعر</h4>
   <div class="chip-row" id="priceChips">
    <button class="chip active" type="button" data-price="all">الكل</button>
    <button class="chip" type="button" data-price="0-20">أقل من 20 {{ $menuCurrencySymbol }}</button>
    <button class="chip" type="button" data-price="20-50">20 - 50 {{ $menuCurrencySymbol }}</button>
    <button class="chip" type="button" data-price="50-999999">أكثر من 50 {{ $menuCurrencySymbol }}</button>
   </div>
  </div>

  <div class="filter-group">
   <h4>الترتيب</h4>
   <div class="chip-row" id="sortChips">
    <button class="chip active" type="button" data-sort="default">الافتراضي</button>
    <button class="chip" type="button" data-sort="price_asc">السعر: من الأقل</button>
    <button class="chip" type="button" data-sort="price_desc">السعر: من الأعلى</button>
   </div>
  </div>

  <button class="filter-apply" type="button" id="applyFilters">عرض النتائج</button>
 </div>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'menu'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')
<script src="{{ asset('assets/js/customer-menu/tish-product-circle-card-v8.js') }}?v=8"></script>
<script src="{{ asset('assets/js/customer-menu/tish-paper-motion-v10.js') }}?v=11"></script>

<script>
const CM = window.CustomerMenu;
const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
const popularSet = new Set(Array.from(@json($popularProductIds ?? [])).map(String));
function productUrl(id) { return PRODUCT_URL_BASE.replace('__ID__', encodeURIComponent(id)); }

let activeCat = @json($initialCategory ?: 'all');
let priceRange = 'all';
let sortMode = 'default';

function renderCard(p) {
    return window.TishProductCircleCard.render(p, {
        productUrl: productUrl,
        isPopular: popularSet.has(String(p.id)),
    });
}

function priceMatches(price) {
    if (priceRange === 'all') return true;
    const [min, max] = priceRange.split('-').map(Number);
    return price >= min && price <= max;
}

function revealMenuCards() {
    window.TishPaperMotion?.observe(document.getElementById('productGrid'));
}

function renderProducts() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    let rows = CM.PRODUCTS.filter(p =>
        (activeCat === 'all' || p.category === String(activeCat)) &&
        priceMatches(Number(p.price)) &&
        (!q || (p.name + ' ' + p.description + ' ' + p.category_name).toLowerCase().includes(q))
    );

    if (sortMode === 'price_asc') rows = rows.slice().sort((a, b) => a.price - b.price);
    if (sortMode === 'price_desc') rows = rows.slice().sort((a, b) => b.price - a.price);

    document.getElementById('resultCount').textContent = menuF('عدد {count} أصناف', {count: rows.length});
    document.getElementById('productGrid').innerHTML = rows.length
        ? rows.map(renderCard).join('')
        : `<div class="empty">لا توجد أصناف مطابقة.</div>`;

    document.getElementById('openFilters').classList.toggle('has-filters', priceRange !== 'all' || sortMode !== 'default');
    revealMenuCards();
}

document.addEventListener('click', e => {
    const fav = e.target.closest('[data-fav]');
    if (fav) {
        e.preventDefault(); e.stopPropagation();
        CM.toggleFavorite(fav.dataset.fav);
        window.TishPaperMotion?.syncFavorites(fav.dataset.fav, CM.isFavorite(fav.dataset.fav));
        return;
    }

    const add = e.target.closest('[data-add]');
    if (add) {
        e.preventDefault(); e.stopPropagation();
        const p = CM.product(add.dataset.add);
        if (!p || !p.available || add.disabled) return;
        window.TishProductCircleCard.addFromButton(add, p, productUrl(p.id));
        return;
    }
});

document.getElementById('categories').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    activeCat = btn.dataset.cat;
    document.querySelectorAll('.cat').forEach(x => x.classList.toggle('active', x === btn));
    renderProducts();
    window.TishPaperMotion?.categories(document.getElementById('categories'), btn);
});

document.getElementById('searchInput').addEventListener('input', renderProducts);

// filter sheet
const filterOverlay = document.getElementById('filterOverlay');
document.getElementById('openFilters').addEventListener('click', () => filterOverlay.classList.add('open'));
document.getElementById('closeFilters').addEventListener('click', () => filterOverlay.classList.remove('open'));
document.getElementById('applyFilters').addEventListener('click', () => { filterOverlay.classList.remove('open'); renderProducts(); });
filterOverlay.addEventListener('click', e => { if (e.target === filterOverlay) filterOverlay.classList.remove('open'); });

document.getElementById('priceChips').addEventListener('click', e => {
    const chip = e.target.closest('[data-price]'); if (!chip) return;
    priceRange = chip.dataset.price;
    document.querySelectorAll('#priceChips .chip').forEach(c => c.classList.toggle('active', c === chip));
});
document.getElementById('sortChips').addEventListener('click', e => {
    const chip = e.target.closest('[data-sort]'); if (!chip) return;
    sortMode = chip.dataset.sort;
    document.querySelectorAll('#sortChips .chip').forEach(c => c.classList.toggle('active', c === chip));
});
document.getElementById('resetFilters').addEventListener('click', () => {
    priceRange = 'all'; sortMode = 'default';
    document.querySelectorAll('#priceChips .chip').forEach(c => c.classList.toggle('active', c.dataset.price === 'all'));
    document.querySelectorAll('#sortChips .chip').forEach(c => c.classList.toggle('active', c.dataset.sort === 'default'));
    renderProducts();
});

renderProducts();
window.TishPaperMotion?.categories(document.getElementById('categories'));
document.getElementById('tishPaperPin')?.addEventListener('click', () => document.getElementById('pwaInstallBtn')?.click());
</script>
</body>
</html>

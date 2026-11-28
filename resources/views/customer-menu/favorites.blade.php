<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Favorites' : 'المفضلة' }} - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-favorites-paper.css') }}?v=2">
@include('customer-menu.partials.pwa-head')
</head>
<body class="crisp-customer-menu tish-paper tish-favorites-page">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => \App\Support\CustomerMenuLanguage::isEnglish() ? 'Favorites' : 'المفضلة', 'pageSubtitle' => $location->name])

<main class="menu-area">
 <div class="shell">
  <div id="favoritesItems"></div>
  <p id="favoritesFeedback" class="favorites-feedback" role="status"></p>
  <div class="favorites-install">@include('customer-menu.partials.pwa-install')</div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'favorites'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const PRODUCT_URL_BASE = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
const PRODUCTS_URL = @json(route('customer-menu.products', $location->code));

const items = document.getElementById('favoritesItems');
const feedback = document.getElementById('favoritesFeedback');
const english = @json(\App\Support\CustomerMenuLanguage::isEnglish());
const copy = english
    ? {add:'Add to cart', options:'Choose options', remove:'Remove from favorites', unavailable:'Unavailable', empty:'No favorites yet', browse:'Browse the menu', added:'Added to cart', removed:'Removed from favorites'}
    : {add:'إضافة للسلة', options:'اختر التفاصيل', remove:'إزالة من المفضلة', unavailable:'غير متوفر حاليًا', empty:'ما عندك أصناف بالمفضلة بعد', browse:'تصفّح المنيو', added:'تمت الإضافة للسلة', removed:'تمت الإزالة من المفضلة'};

document.addEventListener('tish:meal-status-changed', renderFavorites);
function renderFavorites() {
    const rows = CM.favorites().map(id => CM.product(id)).filter(Boolean);
    items.innerHTML = rows.length
        ? rows.map(product => {
            const id = CM.esc(product.id);
            const name = CM.esc(product.name);
            const url = CM.esc(PRODUCT_URL_BASE.replace('__ID__', encodeURIComponent(product.id)));
            const needsOptions = product.requiresChoices || product.isVariantProduct;
            const action = !product.available || CM.mealUnavailable(product)
                ? `<span class="favorite-unavailable">${copy.unavailable}</span>`
                : needsOptions
                    ? `<a class="favorite-action" href="${url}">${copy.options}</a>`
                    : `<button class="favorite-action" type="button" data-add="${id}"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> ${copy.add}</button>`;
            return `
              <article class="favorite-card">
                <a class="favorite-photo" href="${url}" aria-label="${name}">${CM.image(product)}</a>
                <div class="favorite-details">
                  <h2><a href="${url}">${name}</a></h2>
                  <span class="favorite-price">${CM.esc(CM.money(product.price))}</span>
                  ${action}
                </div>
                <button class="favorite-remove" type="button" data-fav="${id}" aria-label="${copy.remove}: ${name}"><i class="fa-solid fa-heart" aria-hidden="true"></i></button>
              </article>`;
        }).join('')
        : `<div class="favorite-empty"><i class="fa-regular fa-heart" aria-hidden="true"></i><h2>${copy.empty}</h2><a class="favorite-action" href="${CM.esc(PRODUCTS_URL)}">${copy.browse}</a></div>`;
}

items.addEventListener('click', event => {
    const favorite = event.target.closest('button[data-fav]');
    if (favorite) {
        const index = Array.from(items.querySelectorAll('button[data-fav]')).indexOf(favorite);
        CM.toggleFavorite(favorite.dataset.fav);
        renderFavorites();
        const remaining = items.querySelectorAll('button[data-fav]');
        const next = remaining[Math.min(index, remaining.length - 1)] || items.querySelector('.favorite-action');
        next?.focus({preventScroll:true});
        feedback.textContent = copy.removed;
        return;
    }

    const add = event.target.closest('button[data-add]');
    if (add && CM.addToCart(add.dataset.add, 1)) {
        feedback.textContent = copy.added;
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            add.animate?.([{scale:'1'}, {scale:'.95'}, {scale:'1.04'}, {scale:'1'}], {duration:280});
        }
    }
});

renderFavorites();
</script>
</body>
</html>

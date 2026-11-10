@php($menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'])
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $product['name'] }} - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
</head>
<body class="crisp-customer-menu tish-paper tish-product-page">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['backUrl' => route('customer-menu.products', $location->code)])

<main class="menu-area">
 <div class="shell">
  <div class="detail-media" id="detailMedia">
   @if(!empty($product['image']))
    <img src="{{ $product['cutout_image'] ?: $product['image'] }}" data-fallback="{{ $product['cutout_image'] ? $product['image'] : '' }}" alt="{{ $product['name'] }}" onerror="if(this.dataset.fallback){const original=this.dataset.fallback;this.dataset.fallback='';this.src=original;}else{this.remove();this.parentElement.insertAdjacentHTML('afterbegin', '<div class=&quot;product-placeholder&quot;>{{ mb_substr($product['name'], 0, 1) }}</div>');}">
   @else
    <div class="product-placeholder">{{ mb_substr($product['name'], 0, 1) }}</div>
   @endif
   <button class="fav" type="button" id="detailFav" aria-label="أضف للمفضلة"><i class="fa-regular fa-heart"></i></button>
  </div>

  <div class="detail-body">
   @if(!$product['available'])<div class="sold-out">غير متوفر حالياً</div>@endif
   <h1>{{ $product['name'] }}</h1>
   <div class="detail-description">{{ $product['description'] ?: 'لا يوجد وصف إضافي لهذا الصنف.' }}</div>

   <div id="pickerContainer"></div>

   <div class="detail-meta">
    <div class="detail-price" id="detailPrice">{{ number_format($product['price'], 2) }} {{ $menuCurrencySymbol }}</div>
    <div class="qty">
     <button type="button" id="detailMinus">−</button>
     <strong id="detailQty">1</strong>
     <button type="button" id="detailPlus">+</button>
    </div>
   </div>

   <div class="picker-error" id="pickerError" hidden></div>

   <button class="add-detail" id="detailAdd" type="button" {{ $product['available'] ? '' : 'disabled' }}>
    <i class="fa-solid fa-bag-shopping"></i> {{ $product['available'] ? 'إضافة للسلة' : 'غير متوفر حالياً' }}
   </button>
   <button class="detail-cart-link" id="detailOpenCart" type="button">
    <i class="fa-solid fa-basket-shopping"></i> عرض السلة <span id="detailCartCount">0</span>
   </button>
   <div class="tish-install-inline">@include('customer-menu.partials.pwa-install')</div>
  </div>

  @if($addOnGroups->isNotEmpty())
  <section class="tish-complete-meal" aria-labelledby="tishCompleteTitle">
   <div class="tish-addons-heading">
    <span class="tish-addons-eyebrow"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> {{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'MAKE IT A MEAL' : 'كمّل وجبتك' }}</span>
    <h2 id="tishCompleteTitle">{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Complete your order' : 'خلّي وجبتك كاملة!' }}</h2>
    <p>{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Pick fries, a drink or a sauce — all optional.' : 'اختار بطاطا أو مشروب أو صوص... حسب ذوقك.' }}</p>
   </div>
   <?php foreach ($addOnGroups as $addOnGroup): ?>
    <?php
     $addOnLabels = [
      'fries' => ['ar' => 'بطاطا ومقبلات', 'en' => 'Fries & sides', 'icon' => 'fa-box-open'],
      'drinks' => ['ar' => 'مشروبات منعشة', 'en' => 'Cold drinks', 'icon' => 'fa-glass-water'],
      'sauces' => ['ar' => 'صوصات وتغميسات', 'en' => 'Sauces & dips', 'icon' => 'fa-bottle-droplet'],
     ];
     $addOnGroupLabel = $addOnLabels[$addOnGroup['type']];
     $addOnTitle = \App\Support\CustomerMenuLanguage::isEnglish() ? $addOnGroupLabel['en'] : $addOnGroupLabel['ar'];
    ?>
    <div class="tish-addon-group" data-addon-group="{{ $addOnGroup['type'] }}">
     <div class="tish-addon-group-title">
      <div class="tish-addon-category-label">
       <span class="tish-addon-category-icon"><i class="fa-solid {{ $addOnGroupLabel['icon'] }}" aria-hidden="true"></i></span>
       <h3>{{ $addOnTitle }}</h3>
      </div>
      @if($addOnGroup['items']->count() > 2)
       <button type="button" class="tish-addon-discover" data-addon-expand aria-expanded="false" aria-controls="tish-addon-{{ $addOnGroup['type'] }}">
        <span>{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Discover more' : 'اكتشف المزيد' }}</span>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
       </button>
      @endif
     </div>
     <div class="tish-addons-grid" id="tish-addon-{{ $addOnGroup['type'] }}">
      <?php foreach ($addOnGroup['items'] as $addOnIndex => $addOn): ?>
       <article class="tish-addon-card" <?php if ($addOnIndex >= 2): ?> data-addon-extra hidden <?php endif; ?>>
        <a class="tish-addon-photo" href="{{ route('customer-menu.product.show', [$location->code, $addOn['product_id']]) }}" aria-label="{{ $addOn['name'] }}">
         @if(!empty($addOn['image']))
          <img src="{{ $addOn['image'] }}" alt="{{ $addOn['name'] }}" loading="lazy" onerror="this.style.display='none'">
         @else
          <span class="tish-addon-placeholder"><i class="fa-solid fa-utensils" aria-hidden="true"></i></span>
         @endif
        </a>
        <div class="tish-addon-info">
         <span class="tish-addon-type">{{ $addOn['category'] }}</span>
         <strong>{{ $addOn['name'] }}</strong>
         <div class="tish-addon-actions">
          <span class="tish-addon-price">{{ number_format($addOn['price'], 2) }} {{ $menuCurrencySymbol }}</span>
          @if($addOn['requires_choices'])
           <a class="tish-addon-pick" href="{{ route('customer-menu.product.show', [$location->code, $addOn['product_id']]) }}" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Choose options for' : 'اختيار تفاصيل' }} {{ $addOn['name'] }}">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
           </a>
          @else
           <button class="tish-addon-add" type="button" data-addon-id="{{ $addOn['product_id'] }}" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Add' : 'أضف' }} {{ $addOn['name'] }}">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
           </button>
          @endif
         </div>
        </div>
       </article>
      <?php endforeach; ?>
     </div>
    </div>
   <?php endforeach; ?>
   <p class="tish-addons-feedback" id="tishAddonsFeedback" role="status" aria-live="polite"></p>
  </section>
  @endif

  @if($relatedItems->isNotEmpty())
   <div class="section-title"><div><h2>قد يعجبك أيضًا</h2></div></div>
   <div class="related-row">
    @foreach($relatedItems as $related)
     <a class="product" href="{{ route('customer-menu.product.show', [$location->code, $related['product_id']]) }}">
      <div class="product-media">
       @if(!empty($related['image']))<img src="{{ $related['image'] }}" alt="{{ $related['name'] }}" onerror="this.outerHTML='<div class=&quot;product-placeholder&quot;>{{ mb_substr($related['name'], 0, 1) }}</div>'">@else<div class="product-placeholder">{{ mb_substr($related['name'], 0, 1) }}</div>@endif
      </div>
      <div class="product-body">
       <h3>{{ $related['name'] }}</h3>
       <div class="product-foot"><span class="price">{{ number_format($related['price'], 2) }} {{ $menuCurrencySymbol }}</span></div>
      </div>
     </a>
    @endforeach
   </div>
  @endif
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'menu'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const menuT = text => window.CustomerMenuI18n.t(text);
const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
const PRODUCT_ID = @json($product['product_id']);
const PRODUCT = CM.product(PRODUCT_ID);
document.querySelectorAll('[data-addon-expand]').forEach(button => {
    button.addEventListener('click', () => {
        const group = button.closest('[data-addon-group]');
        if (!group) return;
        const expanded = button.getAttribute('aria-expanded') === 'true';
        group.querySelectorAll('[data-addon-extra]').forEach(card => { card.hidden = expanded; });
        button.setAttribute('aria-expanded', String(!expanded));
        const text = button.querySelector('span');
        if (text) text.textContent = expanded
            ? @json(\App\Support\CustomerMenuLanguage::isEnglish() ? 'Discover more' : 'اكتشف المزيد')
            : @json(\App\Support\CustomerMenuLanguage::isEnglish() ? 'Show less' : 'عرض أقل');
    });
});
const addonFeedback = document.getElementById('tishAddonsFeedback');
document.querySelector('.tish-addons-grid')?.addEventListener('click', event => {
    const button = event.target.closest('button[data-addon-id]');
    if (!button) return;
    const item = CM.product(button.dataset.addonId);
    // Complex items must go through their dedicated choice picker.
    if (!item || !item.available || item.requiresChoices || item.isVariantProduct) return;
    if (!CM.addToCart(item.id, 1)) return;
    button.classList.add('added');
    button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
    if (addonFeedback) addonFeedback.textContent = menuT('تمت الإضافة للسلة') + ': ' + item.name;
    window.setTimeout(() => {
        if (!button.isConnected) return;
        button.classList.remove('added');
        button.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i>';
    }, 1000);
});

let qty = 1;

// ---- selection state ----
let selectedVariantId = null;
const selectedModifiers = {}; // modifier_id -> quantity

const variants = PRODUCT.variants || [];
let modifierGroups = PRODUCT.modifierGroups || [];

if (PRODUCT.isVariantProduct && variants.length) {
    const def = variants.find(v => v.is_default) || variants[0];
    selectedVariantId = def.id;
}

function resetModifierChoices() {
    const variant = variants.find(v => String(v.id) === String(selectedVariantId));
    modifierGroups = variant?.modifier_groups ?? PRODUCT.modifierGroups ?? [];
    Object.keys(selectedModifiers).forEach(id => delete selectedModifiers[id]);
    modifierGroups.forEach(group => {
        (group.modifiers || []).forEach(m => { if (m.is_default) selectedModifiers[m.id] = 1; });
    });
}
resetModifierChoices();

function updateMedia() {
    if (!PRODUCT.isVariantProduct) return;
    const v = variants.find(v => String(v.id) === String(selectedVariantId));
    if (!v || !v.image) return;
    const picture = v.cutout_image || v.image;
    const media = document.getElementById('detailMedia');
    let img = media.querySelector('img');
    if (!img) {
        img = document.createElement('img');
        media.querySelector('.product-placeholder')?.remove();
        media.prepend(img);
    }
    if (img.getAttribute('src') === picture) return;
    img.dataset.fallback = v.cutout_image ? v.image : '';
    img.onerror = function () {
        if (this.dataset.fallback) {
            const original = this.dataset.fallback;
            this.dataset.fallback = '';
            this.src = original;
        } else {
            this.style.display = 'none';
        }
    };
    img.src = picture;
    img.alt = PRODUCT.name;
    document.dispatchEvent(new CustomEvent('tish:product-image-changed', { detail: { image: img } }));
}

function currentPrice() {
    let base = Number(PRODUCT.price);
    if (PRODUCT.isVariantProduct) {
        const v = variants.find(v => String(v.id) === String(selectedVariantId));
        if (v) base = Number(v.price);
    }
    modifierGroups.forEach(group => {
        (group.modifiers || []).forEach(m => {
            if (selectedModifiers[m.id]) base += Number(m.price_delta) * selectedModifiers[m.id];
        });
    });
    return base;
}

function updatePriceDisplay() {
    document.getElementById('detailPrice').textContent = CM.money(currentPrice());
    updateMedia();
}

function renderPicker() {
    let html = '';

    if (PRODUCT.isVariantProduct && variants.length) {
        html += `<div class="picker-group">
          <div class="picker-group-head"><h4>الحجم / النوع</h4><span class="picker-required">مطلوب</span></div>
          ${variants.map(v => `
            <div class="option-row ${String(v.id) === String(selectedVariantId) ? 'selected' : ''}" data-variant="${v.id}">
              <span class="mark round"></span>
              <span class="opt-copy">${CM.esc(v.name)}</span>
              <span class="opt-price">${CM.money(v.price)}</span>
            </div>`).join('')}
        </div>`;
    }
    modifierGroups.forEach(group => {
        const isSingle = group.selection_type === 'single' || group.max === 1;
        const hint = group.required
            ? (group.max && group.max > 1 ? menuF('اختر من {min} إلى {max}', {min:group.min, max:group.max}) : 'اختيار مطلوب')
            : (group.max ? menuF('اختر حتى {max}', {max:group.max}) : 'اختياري');

        html += `<div class="picker-group" data-group="${group.id}" data-min="${group.min}" data-max="${group.max ?? ''}" data-required="${group.required ? 1 : 0}">
          <div class="picker-group-head">
            <h4>${CM.esc(group.name)}</h4>
            ${group.required ? `<span class="picker-required">${hint}</span>` : `<small>${hint}</small>`}
          </div>
          ${(group.modifiers || []).map(m => {
              const checked = !!selectedModifiers[m.id];
              const qtyNow = selectedModifiers[m.id] || 1;
              return `
              <div class="option-row ${checked ? 'selected' : ''}" data-modifier="${m.id}" data-group="${group.id}" data-single="${isSingle ? 1 : 0}">
                <span class="mark ${isSingle ? 'round' : 'square'}"></span>
                <span class="opt-copy">${CM.esc(m.name)}</span>
                ${m.allow_quantity && checked ? `
                  <span class="modifier-qty">
                    <button type="button" data-qty-minus="${m.id}">−</button>
                    <span>${qtyNow}</span>
                    <button type="button" data-qty-plus="${m.id}" data-max="${m.max_quantity}">+</button>
                  </span>` : ''}
                <span class="opt-price ${Number(m.price_delta) ? 'has-cost' : ''}">${Number(m.price_delta) ? '+' + CM.money(m.price_delta) : ''}</span>
              </div>`;
          }).join('')}
        </div>`;
    });

    document.getElementById('pickerContainer').innerHTML = html;
    updatePriceDisplay();
}

function validateSelection() {
    if (PRODUCT.isVariantProduct && variants.length && !selectedVariantId) {
        return menuT('اختر الحجم/النوع أولاً.');
    }
    for (const group of modifierGroups) {
        const count = (group.modifiers || []).filter(m => selectedModifiers[m.id]).length;
        const min = group.required ? Math.max(1, group.min) : group.min;
        if (count < min) return menuF('مجموعة "{name}" تتطلب اختيار {min} على الأقل.', {name:group.name, min});
        if (group.max && count > group.max) return menuF('مجموعة "{name}" تسمح بحد أقصى {max}.', {name:group.name, max:group.max});
    }
    return null;
}

document.getElementById('pickerContainer').addEventListener('click', e => {
    const variantRow = e.target.closest('[data-variant]');
    if (variantRow) {
        selectedVariantId = variantRow.dataset.variant;
        resetModifierChoices();
        renderPicker();
        return;
    }

    const qtyMinus = e.target.closest('[data-qty-minus]');
    if (qtyMinus) {
        const id = qtyMinus.dataset.qtyMinus;
        selectedModifiers[id] = Math.max(1, (selectedModifiers[id] || 1) - 1);
        renderPicker();
        return;
    }

    const qtyPlus = e.target.closest('[data-qty-plus]');
    if (qtyPlus) {
        const id = qtyPlus.dataset.qtyPlus;
        const max = Number(qtyPlus.dataset.max || 20);
        selectedModifiers[id] = Math.min(max, (selectedModifiers[id] || 1) + 1);
        renderPicker();
        return;
    }

    const modRow = e.target.closest('[data-modifier]');
    if (modRow) {
        const id = modRow.dataset.modifier;
        const groupId = modRow.dataset.group;
        const isSingle = modRow.dataset.single === '1';

        if (selectedModifiers[id]) {
            delete selectedModifiers[id];
        } else {
            if (isSingle) {
                modifierGroups.find(g => String(g.id) === String(groupId))
                    ?.modifiers.forEach(m => delete selectedModifiers[m.id]);
            }
            selectedModifiers[id] = 1;
        }
        renderPicker();
    }
});

renderPicker();

document.getElementById('detailPlus').onclick = () => { qty = Math.min(50, qty + 1); document.getElementById('detailQty').textContent = qty; };
document.getElementById('detailMinus').onclick = () => { qty = Math.max(1, qty - 1); document.getElementById('detailQty').textContent = qty; };

document.getElementById('detailAdd').onclick = () => {
    const error = validateSelection();
    const errorBox = document.getElementById('pickerError');
    if (error) { errorBox.textContent = error; errorBox.hidden = false; return; }
    errorBox.hidden = true;

    const modifiers = Object.entries(selectedModifiers).map(([modifier_id, quantity]) => ({ modifier_id: Number(modifier_id), quantity }));
    const ok = CM.addToCart(PRODUCT_ID, qty, { variantId: selectedVariantId, modifiers });
    if (!ok) return;

    const btn = document.getElementById('detailAdd');
    btn.innerHTML = `<i class="fa-solid fa-check"></i> تمت الإضافة`;
    setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-bag-shopping"></i> إضافة المزيد للسلة'; }, 900);
};

document.getElementById('detailOpenCart').onclick = () => {
    window.location.href = @json(route('customer-menu.cart', $location->code));
};

const favBtn = document.getElementById('detailFav');
function paintFav() {
    const active = CM.isFavorite(PRODUCT_ID);
    favBtn.classList.toggle('active', active);
    favBtn.innerHTML = `<i class="fa-${active ? 'solid' : 'regular'} fa-heart"></i>`;
}
favBtn.addEventListener('click', () => { CM.toggleFavorite(PRODUCT_ID); paintFav(); });
paintFav();
</script>
</body>
</html>


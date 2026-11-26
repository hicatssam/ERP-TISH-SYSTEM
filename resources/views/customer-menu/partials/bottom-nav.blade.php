@php
    $activeNav = $activeNav ?? 'home';
@endphp
@if(($showStickyCart ?? true) && in_array($activeNav, ['home', 'menu', 'favorites'], true))
<a class="cm-sticky-cart" id="cmStickyCart" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="عرض السلة" hidden>
 <span class="cm-sticky-cart-icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
 <span class="cm-sticky-cart-copy"><strong>سلتك</strong><small id="cmStickyCartCount">0 أصناف</small></span>
 <strong class="cm-sticky-cart-total" id="cmStickyCartTotal">0.00</strong>
 <span class="cm-sticky-cart-cta">عرض السلة <i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
</a>
@endif
<style>
/* Reference paper footer: one shared surface and independently selected paper tabs. */
html body.tish-paper:has(.tish-reference-nav){--tish-nav-height:88px!important;--cart-nav-height:88px!important}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion]{
 position:fixed!important;bottom:0!important;left:50%!important;right:auto!important;transform:translateX(-50%)!important;
 width:min(100%,920px)!important;max-width:920px!important;height:auto!important;min-height:calc(88px + env(safe-area-inset-bottom,0px))!important;
 margin:0!important;padding:12px 5px calc(8px + env(safe-area-inset-bottom,0px))!important;
 display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;align-items:center!important;gap:0!important;direction:rtl!important;
 background:none!important;border:0!important;border-radius:0!important;box-shadow:none!important;
 overflow:visible!important;isolation:isolate!important;contain:none!important;z-index:50!important;
 -webkit-mask:none!important;mask:none!important;clip-path:none!important;filter:drop-shadow(0 -2px 3px #21181236)!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion]::before{
 content:""!important;display:block!important;position:absolute!important;inset:0!important;z-index:-1!important;pointer-events:none!important;
 background:#252423 url('/assets/tish-paper/paper/nav-charcoal.webp') center/100% 100% no-repeat!important;
 clip-path:polygon(0.00% 7.50px,0.56% 8.85px,1.11% 7.51px,1.67% 9.96px,2.22% 8.36px,2.78% 10.30px,3.33% 10.74px,3.89% 9.94px,4.44% 8.15px,5.00% 7.96px,5.56% 6.57px,6.11% 6.19px,6.67% 4.99px,7.22% 4.74px,7.78% 5.09px,8.33% 5.21px,8.89% 4.95px,9.44% 6.75px,10.00% 6.29px,10.56% 5.98px,11.11% 7.76px,11.67% 6.27px,12.22% 5.96px,12.78% 4.73px,13.33% 4.44px,13.89% 6.21px,14.44% 3.66px,15.00% 5.42px,15.56% 5.37px,16.11% 4.74px,16.67% 3.18px,17.22% 4.31px,17.78% 6.59px,18.33% 6.91px,18.89% 6.34px,19.44% 9.92px,20.00% 10.29px,20.56% 10.58px,21.11% 8.82px,21.67% 10.81px,22.22% 10.96px,22.78% 10.22px,23.33% 9.93px,23.89% 9.03px,24.44% 7.89px,25.00% 7.43px,25.56% 6.00px,26.11% 4.59px,26.67% 4.50px,27.22% 5.53px,27.78% 5.74px,28.33% 6.65px,28.89% 6.76px,29.44% 4.96px,30.00% 5.87px,30.56% 4.91px,31.11% 5.89px,31.67% 6.90px,32.22% 6.98px,32.78% 4.52px,33.33% 5.14px,33.89% 6.36px,34.44% 5.05px,35.00% 3.58px,35.56% 4.47px,36.11% 5.82px,36.67% 7.56px,37.22% 7.45px,37.78% 8.42px,38.33% 10.59px,38.89% 8.45px,39.44% 10.32px,40.00% 8.70px,40.56% 9.62px,41.11% 9.43px,41.67% 8.36px,42.22% 7.93px,42.78% 7.05px,43.33% 4.47px,43.89% 6.34px,44.44% 3.28px,45.00% 4.54px,45.56% 3.71px,46.11% 3.61px,46.67% 4.11px,47.22% 6.10px,47.78% 4.29px,48.33% 4.47px,48.89% 7.11px,49.44% 6.94px,50.00% 5.96px,50.56% 7.77px,51.11% 5.67px,51.67% 7.40px,52.22% 6.46px,52.78% 6.73px,53.33% 7.46px,53.89% 6.12px,54.44% 5.79px,55.00% 6.31px,55.56% 7.15px,56.11% 8.23px,56.67% 8.62px,57.22% 8.55px,57.78% 9.01px,58.33% 9.64px,58.89% 8.56px,59.44% 10.26px,60.00% 8.04px,60.56% 7.79px,61.11% 8.23px,61.67% 6.01px,62.22% 3.33px,62.78% 5.57px,63.33% 3.65px,63.89% 4.61px,64.44% 2.15px,65.00% 3.13px,65.56% 4.93px,66.11% 4.92px,66.67% 6.27px,67.22% 5.79px,67.78% 7.56px,68.33% 6.93px,68.89% 8.60px,69.44% 8.18px,70.00% 7.54px,70.56% 7.97px,71.11% 8.54px,71.67% 6.05px,72.22% 5.28px,72.78% 6.97px,73.33% 8.32px,73.89% 6.83px,74.44% 6.48px,75.00% 6.81px,75.56% 8.56px,76.11% 7.38px,76.67% 9.76px,77.22% 9.84px,77.78% 10.23px,78.33% 7.80px,78.89% 8.26px,79.44% 7.70px,80.00% 6.21px,80.56% 4.91px,81.11% 2.55px,81.67% 4.40px,82.22% 3.79px,82.78% 4.37px,83.33% 3.10px,83.89% 2.49px,84.44% 3.33px,85.00% 6.16px,85.56% 4.93px,86.11% 8.52px,86.67% 8.37px,87.22% 9.52px,87.78% 7.76px,88.33% 6.91px,88.89% 9.83px,89.44% 7.94px,90.00% 6.91px,90.56% 6.45px,91.11% 7.81px,91.67% 8.45px,92.22% 6.65px,92.78% 7.40px,93.33% 5.78px,93.89% 6.34px,94.44% 8.11px,95.00% 7.43px,95.56% 9.46px,96.11% 9.03px,96.67% 7.82px,97.22% 8.24px,97.78% 6.66px,98.33% 6.18px,98.89% 3.65px,99.44% 3.83px,100.00% 2.64px,100% 100%,0 100%)!important;
 border:0!important;border-radius:0!important;box-shadow:none!important;mask:none!important;-webkit-mask:none!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion]::after{content:none!important}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn{
 position:relative!important;isolation:isolate!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;
 gap:5px!important;min-width:0!important;width:100%!important;height:67px!important;min-height:67px!important;max-height:none!important;
 padding:5px 0 3px!important;margin:0!important;color:#f8ebd8!important;line-height:1.15!important;
 background:none!important;border:0!important;border-radius:0!important;box-shadow:none!important;filter:none!important;
 text-decoration:none!important;transform:none!important;clip-path:none!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn::before{
 content:none!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn.active::before{
 content:""!important;display:block!important;position:absolute!important;inset:-1px 4px -1px!important;z-index:-1!important;pointer-events:none!important;
 background:transparent url('/assets/tish-paper/paper/nav-active.webp') center/100% 100% no-repeat!important;
 border:0!important;box-shadow:none!important;clip-path:none!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn::after{content:none!important;display:none!important}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn>i,
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .tish-paper-cart-icon-disc{display:none!important}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn .tish-paper-nav-icon{
 display:block!important;flex:none!important;width:clamp(30px,8.3vw,38px)!important;height:clamp(30px,8.3vw,38px)!important;max-width:none!important;max-height:none!important;
 object-fit:contain!important;transform:none!important;translate:none!important;scale:1!important;filter:drop-shadow(0 .35px 0 #f8ebd8)!important;
 background:none!important;border:0!important;padding:0!important;margin:0!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn>span:not(.tish-paper-cart-icon-disc){
 display:block!important;color:inherit!important;font-size:clamp(12px,3.5vw,17px)!important;font-weight:850!important;line-height:1.2!important;letter-spacing:0!important;
 text-align:center!important;white-space:nowrap!important;margin:0!important;
}
html[lang="en"] body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn>span:not(.tish-paper-cart-icon-disc){
 font-family:"TISH Oswald","TISH Noto",sans-serif!important;font-size:clamp(11px,3.2vw,15px)!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn .nav-badge{
 position:absolute!important;z-index:3!important;top:0!important;right:calc(50% - 30px)!important;left:auto!important;
 min-width:21px!important;height:21px!important;padding:0 4px!important;display:grid!important;place-items:center!important;
 background:#b22215!important;border:1.5px solid #fff1da!important;border-radius:50px!important;
 color:#fff!important;font-size:12px!important;font-weight:900!important;line-height:1!important;box-shadow:none!important;
}
html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn .nav-badge[hidden]{display:none!important}
html body.tish-paper:has(.tish-reference-nav) .cm-sticky-cart:not([hidden]){bottom:calc(100px + env(safe-area-inset-bottom,0px))!important}
html body:is(#tishCart,.tish-paper):has(.tish-reference-nav) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){bottom:calc(98px + env(safe-area-inset-bottom,0px))!important}
html body:is(#tishCart,.tish-paper):has(.tish-reference-nav):has(.cm-sticky-cart:not([hidden])) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){bottom:calc(178px + env(safe-area-inset-bottom,0px))!important}
@media(max-width:365px){
 html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion] .nav-btn .nav-badge{right:calc(50% - 26px)!important;min-width:19px!important;height:19px!important}
}
@media print{html body:is(#tishCart,#tishTrack,.tish-paper) nav.bottom-nav.tish-reference-nav[data-tish-nav-motion]{display:none!important}}
</style>
<nav class="bottom-nav tish-reference-nav" aria-label="التنقل" data-tish-nav-motion>
 <a class="nav-btn {{ $activeNav === 'home' ? 'active' : '' }}" href="{{ route('customer-menu.show', $location->code) }}"><i class="fa-solid fa-house"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/home.svg') }}" alt="" aria-hidden="true"><span>الرئيسية</span></a>
 <a class="nav-btn {{ $activeNav === 'menu' ? 'active' : '' }}" href="{{ route('customer-menu.products', $location->code) }}"><i class="fa-solid fa-utensils"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/menu.svg') }}" alt="" aria-hidden="true"><span>المنيو</span></a>
 <a class="nav-btn tish-paper-cart-nav {{ $activeNav === 'cart' ? 'active' : '' }}" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><span class="tish-paper-cart-icon-disc" aria-hidden="true"><span class="tish-paper-cart-glyph"></span></span><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/cart.svg') }}" alt="" aria-hidden="true"><span>السلة</span><b class="nav-badge" id="cartBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'favorites' ? 'active' : '' }}" href="{{ route('customer-menu.favorites', $location->code) }}"><i class="fa-regular fa-heart"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/favorites.svg') }}" alt="" aria-hidden="true"><span>المفضلة</span><b class="nav-badge" id="favBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'orders' ? 'active' : '' }}" href="{{ route('customer-menu.my-orders', $location->code) }}"><i class="fa-solid fa-receipt"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/orders.svg') }}" alt="" aria-hidden="true"><span>طلباتي</span></a>
</nav>
<script>
/* TISH: native navigation without ripple or forced layout. */
(function () {
  const nav = document.querySelector('[data-tish-nav-motion]');
  if (!nav) return;

  nav.querySelectorAll('.nav-btn.active').forEach(function (link) {
    link.setAttribute('aria-current', 'page');
  });

  nav.addEventListener('pointerdown', function (event) {
    const link = event.target.closest('.nav-btn');
    if (!link || !nav.contains(link)) return;

    nav.querySelectorAll('.nav-btn').forEach(function (item) {
      item.classList.remove('tish-nav-pending');
    });

    link.classList.add('tish-nav-pending');
  }, { passive: true });

  window.addEventListener('pageshow', function () {
    nav.querySelectorAll('.tish-nav-pending').forEach(function (item) {
      item.classList.remove('tish-nav-pending');
    });
  });
})();
</script>

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
<nav class="bottom-nav" aria-label="التنقل">
 <a class="nav-btn {{ $activeNav === 'home' ? 'active' : '' }}" href="{{ route('customer-menu.show', $location->code) }}"><i class="fa-solid fa-house"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/home.svg') }}" alt="" aria-hidden="true"><span>الرئيسية</span></a>
 <a class="nav-btn {{ $activeNav === 'menu' ? 'active' : '' }}" href="{{ route('customer-menu.products', $location->code) }}"><i class="fa-solid fa-utensils"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/menu.svg') }}" alt="" aria-hidden="true"><span>المنيو</span></a>
 <a class="nav-btn tish-paper-cart-nav {{ $activeNav === 'cart' ? 'active' : '' }}" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><span class="tish-paper-cart-icon-disc" aria-hidden="true"><span class="tish-paper-cart-glyph"></span></span><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/cart.svg') }}" alt="" aria-hidden="true"><span>السلة</span><b class="nav-badge" id="cartBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'favorites' ? 'active' : '' }}" href="{{ route('customer-menu.favorites', $location->code) }}"><i class="fa-regular fa-heart"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/favorites.svg') }}" alt="" aria-hidden="true"><span>المفضلة</span><b class="nav-badge" id="favBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'orders' ? 'active' : '' }}" href="{{ route('customer-menu.my-orders', $location->code) }}"><i class="fa-solid fa-receipt"></i><img class="tish-paper-nav-icon" src="{{ asset('assets/tish-paper/icons/orders.svg') }}" alt="" aria-hidden="true"><span>طلباتي</span></a>
</nav>

<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $branding['name'] ?? config('app.name') }} - {{ $location->name }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/floating-showcase.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-reference-v3.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-card-fix-v4.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-menu-circle-v5.css') }}?v=5">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-featured-showcase-v6.css') }}?v=6">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-product-circle-card-v8.css') }}?v=9">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-product-layout-v10.css') }}?v=11">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-motion-v10.css') }}?v=12">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-polish-v13.css') }}?v=17">
@include('customer-menu.partials.pwa-head')
<script src="{{ asset('assets/js/customer-menu/branch-entry.js') }}?v=1"
        data-branch-code="{{ $location->code }}"
        data-branch-entry-url="{{ route('customer-menu.index', ['choose' => 1]) }}"></script>
@php
    $cmSetting = static fn (string $key, mixed $default = null): mixed =>
        \App\Models\SystemSetting::get($key, $default);

    $showSearch = (bool) $cmSetting('customer_menu_show_search', true);
    $showCategories = (bool) $cmSetting('customer_menu_show_categories', true);
    $showDescriptions = (bool) $cmSetting('customer_menu_show_descriptions', true);
    $showFeatured = (bool) $cmSetting('customer_menu_show_featured', true);
    $featuredTitle = (string) $cmSetting('customer_menu_featured_title', 'الأكثر طلباً');
    $featuredLimit = max(2, min(8, (int) $cmSetting('customer_menu_featured_limit', 4)));
    $homeSubtitle = \App\Support\CustomerMenuCopy::homeSubtitle();
    $introMode = (string) $cmSetting('customer_menu_intro_mode', 'first_visit');
    $introTitle = (string) $cmSetting('customer_menu_intro_title', 'أهلاً بك');
    $introSubtitle = (string) $cmSetting('customer_menu_intro_subtitle', 'تجربة ألذ تبدأ من هنا');
    $introEyebrow = (string) $cmSetting('customer_menu_intro_eyebrow', '');
    $introCta = (string) $cmSetting('customer_menu_intro_cta', 'ابدأ الطلب');
    $introShowLogo = (bool) $cmSetting('customer_menu_intro_show_logo', true);
    $introImage = \App\Models\SystemSetting::assetUrl('customer_menu_intro_image');
    $coverImage = \App\Models\SystemSetting::assetUrl('customer_menu_cover_image')
        ?: ($theme['cover'] ?? null);
    $overlay = max(0, min(90, (int) $cmSetting('customer_menu_cover_overlay', 48)));
    $cardRadius = max(0, min(40, (int) $cmSetting('customer_menu_product_card_radius', 18)));
    $contentWidth = max(360, min(1700, (int) $cmSetting('customer_menu_content_width', 980)));
    $sectionGap = max(20, min(100, (int) $cmSetting('customer_menu_section_gap', 34)));
    $imageFit = in_array($cmSetting('customer_menu_image_fit', 'cover'), ['cover', 'contain'], true)
        ? $cmSetting('customer_menu_image_fit', 'cover')
        : 'cover';
    $imageRatio = match ((string) $cmSetting('customer_menu_card_image_ratio', '4-3')) {
        '1-1' => '1 / 1',
        '3-4' => '3 / 4',
        default => '4 / 3',
    };
    $shadow = match ((string) $cmSetting('customer_menu_product_card_shadow', 'soft')) {
        'none' => 'none',
        'deep' => '0 18px 44px rgba(27, 19, 22, .16)',
        default => '0 10px 28px rgba(27, 19, 22, .08)',
    };
    $categoryIconClasses = [
        'burger' => 'fa-burger',
        'pizza' => 'fa-pizza-slice',
        'coffee' => 'fa-mug-hot',
        'cake' => 'fa-cake-candles',
        'croissant' => 'fa-bread-slice',
        'donut' => 'fa-cookie-bite',
        'icecream' => 'fa-ice-cream',
        'juice' => 'fa-glass-water',
        'drink' => 'fa-bottle-water',
        'fries' => 'fa-box',
        'chicken' => 'fa-drumstick-bite',
        'salad' => 'fa-bowl-food',
        'sandwich' => 'fa-burger',
        'chocolate' => 'fa-cookie',
        'tea' => 'fa-mug-saucer',
        'breakfast' => 'fa-egg',
        'gift' => 'fa-gift',
        'dessert' => 'fa-cookie-bite',
        'sparkles' => 'fa-wand-magic-sparkles',
    ];
@endphp
<style>
.reference-home{
 --home-radius:{{ $cardRadius }}px;
 --home-shadow:{{ $shadow }};
 --home-image-ratio:{{ $imageRatio }};
 --home-image-fit:{{ $imageFit }};
 --home-overlay:{{ $overlay / 100 }};
 background:
  radial-gradient(circle at 8% 8%,color-mix(in srgb,var(--primary) 4%,transparent),transparent 28rem),
  var(--bg);
}
.reference-home .shell{width:min({{ $contentWidth }}px,calc(100% - 30px))}
.reference-home .menu-area{padding-bottom:112px}
body.tish-paper.reference-home .reference-masthead .tish-paper-banner-frame:has(.banner-artwork-only){
 padding:clamp(6px,1.35vw,16px)!important;
}
body.tish-paper.reference-home .reference-masthead .tish-paper-banner-frame .banner-slide.banner-artwork-only{
 aspect-ratio:3 / 1;
 min-height:0!important;
 display:grid;
 place-items:center;
}
body.tish-paper.reference-home .reference-masthead .tish-paper-banner-frame .banner-artwork-only img{
 display:block;
 width:100%;
 height:auto!important;
 max-height:100%;
 aspect-ratio:3 / 1;
 object-fit:contain!important;
}
body.tish-paper.reference-home .reference-brand-copy h1{font-size:clamp(.9rem,3.5vw,1.25rem);line-height:1.35;margin:0}
body.tish-paper.reference-home .reference-brand-copy p{font-size:.72rem;line-height:1.35;margin:2px 0 5px;color:var(--tish-paper-red)}
body.tish-paper.reference-home .reference-header-row{gap:8px}
body.tish-paper.reference-home .reference-masthead .tish-paper-banner-frame .banner-slide,
body.tish-paper.reference-home .reference-masthead .tish-paper-banner-frame .tish-paper-cover-banner{
 aspect-ratio:3 / 1;min-height:clamp(118px,29vw,230px)!important;
}
body.tish-paper.reference-home .tish-home-install{display:flex;justify-content:center;margin:16px 0 30px}
body.tish-paper.reference-home .tish-home-install #pwaInstallBtn.tish-paper-pin-control:not([hidden]){
 position:relative!important;inset:auto!important;z-index:auto!important;
 width:auto!important;height:auto!important;min-width:112px!important;min-height:48px!important;
 max-width:none!important;flex-direction:row!important;gap:7px!important;padding:8px 14px!important;
}
/* Let the shared paper canvas show through the full masthead, including search. */
body.tish-paper.reference-home .reference-masthead.is-illustrated::after{
 display:none;
}
.reference-header{padding:20px 0 8px}
.reference-header-row{display:flex;align-items:center;justify-content:space-between;gap:16px}
.reference-brand{display:flex;align-items:center;gap:12px;min-width:0}
.reference-logo,.reference-logo-fallback{width:62px;height:62px;border-radius:18px;border:1px solid var(--line);background:var(--surface);box-shadow:0 8px 24px rgba(20,17,18,.07);flex:0 0 auto}
.reference-logo{object-fit:contain;padding:5px}
.reference-logo-fallback{display:grid;place-items:center;background:var(--primary);color:#fff;font-size:1.4rem;font-weight:900}
.reference-branch{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:1rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.reference-branch i{color:var(--primary);font-size:.8rem}
.reference-actions{display:flex;gap:10px;direction:ltr}
.reference-actions .icon-btn{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;font-size:1.35rem;box-shadow:0 8px 24px rgba(20,17,18,.06)}
.reference-actions .badge{top:-4px;left:auto;right:-4px;min-width:24px;height:24px;font-size:.78rem;border:2px solid var(--bg)}
.reference-greeting{margin:22px 0 16px}
.reference-greeting h1{margin:0;font-size:clamp(2rem,7vw,3.2rem);line-height:1;font-weight:900;letter-spacing:-.045em}
.reference-greeting p{margin:10px 0 0;color:var(--muted);font-size:clamp(1.1rem,4vw,1.5rem);font-weight:500}
.reference-home .tools{gap:12px;margin:8px 0 16px}
.reference-home .search input{height:66px;padding-inline:58px 20px;border-radius:20px;font-size:1.1rem;box-shadow:0 8px 26px rgba(20,17,18,.045)}
.reference-home .search i{right:21px;font-size:1.35rem;color:var(--text)}
.reference-home .filter-square{width:66px;height:66px;border-radius:20px;font-size:1.25rem;box-shadow:0 8px 26px rgba(20,17,18,.045)}
.reference-hero{position:relative;overflow:hidden;margin:10px 0 0;border-radius:24px;background:#1b1214;box-shadow:0 14px 34px rgba(24,15,17,.14)}
body.tish-paper.reference-home #bannerTrack{
 display:grid!important;
 grid-template-columns:minmax(0,1fr);
 width:100%;
}
body.tish-paper.reference-home #bannerTrack > .banner-slide{
 grid-area:1 / 1;
 width:100%;
 min-width:0;
 visibility:hidden;
 opacity:0;
 pointer-events:none;
}
body.tish-paper.reference-home #bannerTrack > .banner-slide[data-banner-active]{
 z-index:1;
 visibility:visible;
 opacity:1;
 pointer-events:auto;
}
@media(prefers-reduced-motion:no-preference){
 body.tish-paper.reference-home #bannerTrack > .banner-slide[data-banner-active]{animation:tish-banner-appear 260ms ease-out}
}
@keyframes tish-banner-appear{from{opacity:0}to{opacity:1}}
.banner-slide{position:relative;min-width:100%;aspect-ratio:2.9/1;overflow:hidden;color:#fff;direction:rtl;display:block}
.banner-slide img{width:100%;height:100%;object-fit:cover}
.banner-slide::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(9,5,6,.12),rgba(9,5,6,var(--home-overlay)) 62%,rgba(9,5,6,.82))}
.banner-copy{position:absolute;z-index:2;inset:0;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:clamp(18px,5vw,44px);max-width:70%}
.banner-copy .tag{display:inline-flex;padding:8px 16px;border-radius:999px;background:var(--primary);color:#fff;font-size:.8rem;font-weight:900;margin-bottom:10px}
.banner-copy h2{margin:0;font-size:clamp(1.55rem,5vw,2.7rem);font-weight:900;line-height:1.1}
.banner-copy p{margin:10px 0 0;max-width:460px;color:rgba(255,255,255,.86);font-size:.92rem;line-height:1.7}
.banner-cta{display:inline-flex;align-items:center;gap:10px;margin-top:18px;padding:10px 18px;border-radius:999px;background:var(--primary);color:#fff;font-size:.9rem;font-weight:900}
.banner-cta i{transform:rotate(180deg)}
.banner-dots{display:flex;justify-content:center;gap:8px;padding:12px;background:var(--bg)}
.banner-dots button{width:10px;height:10px;padding:0;border:0;border-radius:50%;background:color-mix(in srgb,var(--muted) 24%,transparent);transition:.2s}
.banner-dots button.active{width:24px;border-radius:999px;background:var(--primary)}
.reference-hero-fallback{min-height:260px;background:linear-gradient(90deg,rgba(8,4,5,.12),rgba(8,4,5,.78)),var(--primary) center/cover no-repeat;display:flex;align-items:center;color:#fff;padding:32px}
.reference-section{margin-top:{{ $sectionGap }}px}
.reference-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:16px}
.reference-section-copy{display:flex;align-items:flex-start;gap:9px}
.reference-section-icon{color:var(--accent);font-size:1.4rem;margin-top:3px}
.reference-section-head h2{margin:0;font-size:clamp(1.35rem,4vw,1.8rem);font-weight:900}
.reference-section-head p{margin:4px 0 0;color:var(--muted);font-size:.88rem}
.reference-see-all{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;border:1px solid var(--line);border-radius:999px;background:color-mix(in srgb,var(--primary) 3%,var(--surface));padding:10px 16px;color:var(--primary);font-size:.86rem;font-weight:900}
.reference-see-all i{font-size:.72rem}
.reference-home .categories{gap:12px;padding:2px 0 6px;scroll-snap-type:x proximity}
.reference-home .cat{min-width:96px;min-height:118px;padding:10px;border-radius:22px;color:var(--text);justify-content:center;scroll-snap-align:start;box-shadow:0 7px 22px rgba(20,17,18,.045)}
.reference-home .cat.active{background:color-mix(in srgb,var(--primary) 6%,var(--surface));color:var(--text);border:2px solid var(--primary)}
.cat-visual{width:64px;height:64px;border-radius:18px;display:grid;place-items:center;background:color-mix(in srgb,var(--category-color,var(--primary)) 11%,var(--surface));color:var(--category-color,var(--primary));overflow:hidden;font-size:1.7rem}
.reference-home .cat img{width:100%;height:100%;border-radius:0;object-fit:cover}
.reference-home .cat b{font-size:.86rem}
.reference-home .grid{gap:16px}
.reference-home .product{border-radius:var(--home-radius);box-shadow:var(--home-shadow);border-color:color-mix(in srgb,var(--text) 7%,transparent);overflow:hidden}
.reference-home .product-media{height:auto;aspect-ratio:var(--home-image-ratio);background:#f2eeeb}
.reference-home .product-media img{padding:0;object-fit:var(--home-image-fit);background:#f2eeeb}
.reference-home .fav{top:12px;left:12px;width:39px;height:39px;border-radius:12px;color:var(--text)}
.reference-home .product-body{padding:13px 14px 15px}
.reference-home .product h3{font-size:1rem;margin:0}
.reference-home .product-desc{min-height:2.8em;margin:6px 0 12px;font-size:.78rem;line-height:1.4}
.reference-home .price{color:var(--primary);font-size:1.02rem}
.reference-home .details{border-radius:12px;padding:10px 15px;font-size:.82rem}
.product-ribbon{position:absolute;z-index:3;top:12px;right:12px;padding:7px 11px;border-radius:999px;background:var(--primary);color:#fff;font-size:.7rem;font-weight:900;box-shadow:0 5px 16px rgba(0,0,0,.14)}
.catalog-section.is-focused{scroll-margin-top:18px}
.intro{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;background:var(--primary) center/cover no-repeat;transition:opacity .45s ease,visibility .45s ease}
.intro::before{content:"";position:absolute;inset:0;background:rgba(13,7,9,.48)}
.intro.hide{opacity:0;visibility:hidden;pointer-events:none}
.intro-box{position:relative;z-index:1;max-width:440px;text-align:{{ $cmSetting('customer_menu_intro_align','center') === 'right' ? 'right' : 'center' }};color:#fff;padding:28px}
.intro-logo{width:92px;height:92px;object-fit:contain;margin:0 auto 18px;border-radius:22px;background:#fff;padding:5px}
.intro-box small{display:block;margin-bottom:8px;color:rgba(255,255,255,.78);font-weight:800}
.intro-box h1{margin:0;font-size:2.25rem;font-weight:900}
.intro-box p{margin:10px 0 0;color:rgba(255,255,255,.82);line-height:1.7}
.intro-enter{margin-top:20px;border:0;border-radius:999px;background:#fff;color:var(--primary);padding:12px 22px;font-weight:900}
@media(max-width:700px){
 .reference-home .shell{width:min(100% - 24px,560px)}
 .reference-header{padding-top:14px}
 .reference-logo,.reference-logo-fallback{width:52px;height:52px;border-radius:15px}
 .reference-actions .icon-btn{width:50px;height:50px;border-radius:15px}
 .reference-branch{font-size:.86rem}
 .banner-slide{aspect-ratio:2.55/1;min-height:170px}
 .banner-copy{max-width:88%;padding:20px}
 .banner-copy p{font-size:.78rem}
 .reference-home .grid{gap:12px}
 .reference-home .product-body{padding:10px 10px 12px}
 .reference-home .product h3{font-size:.88rem}
 .reference-home .product-desc{font-size:.7rem}
 .reference-home .details{padding:9px 12px}
 .reference-home .price{font-size:.87rem}
}
@media(max-width:380px){
 .reference-brand{gap:7px}
 .reference-branch{max-width:120px}
 .reference-actions{gap:6px}
 .reference-actions .icon-btn{width:44px;height:44px}
 .reference-home .cat{min-width:84px}
}

/* Motion only: preserve the existing layout and configurable brand palette. */
@keyframes cmHeroEnter{from{opacity:.15;transform:translateY(30px) scale(.985)}to{opacity:1;transform:none}}
@keyframes cmSectionEnter{from{opacity:.1;transform:translateY(22px)}to{opacity:1;transform:none}}
.reference-home .reference-hero,.reference-home .reference-hero-fallback,.reference-home .reference-banner{animation:cmHeroEnter .85s cubic-bezier(.2,.75,.2,1) both}
.reference-home .reference-section-head{animation:cmSectionEnter .65s cubic-bezier(.2,.75,.2,1) both}

@keyframes cmIntroReveal{from{opacity:0;transform:translate3d(0,18px,0) scale(.94);filter:blur(5px)}to{opacity:1;transform:none;filter:blur(0)}}
@keyframes cmCardReveal{from{opacity:0;transform:translate3d(0,20px,0)}to{opacity:1;transform:none}}
@keyframes cmBannerCopyReveal{from{opacity:0;transform:translate3d(0,14px,0)}to{opacity:1;transform:none}}
.intro:not(.hide) .intro-logo{animation:cmIntroReveal .75s cubic-bezier(.2,.75,.2,1) both}
.intro:not(.hide) .intro-box small{animation:cmIntroReveal .6s .13s both}
.intro:not(.hide) .intro-box h1{animation:cmIntroReveal .7s .19s both}
.intro:not(.hide) .intro-box p{animation:cmIntroReveal .7s .29s both}
.intro:not(.hide) .intro-enter{animation:cmIntroReveal .65s .4s both;transition:transform .2s ease}
.intro-enter:active{transform:scale(.96)}
.reference-home .cat{transition:transform .28s cubic-bezier(.2,.75,.2,1),background-color .28s ease,border-color .28s ease,box-shadow .28s ease}
.reference-home .cat:active{transform:scale(.95)}
.reference-home .cat.active{transform:translateY(-3px)}
.reference-home .product.cm-reveal{animation:cmCardReveal .52s cubic-bezier(.2,.75,.2,1) both;animation-delay:var(--cm-delay,0ms)}
.reference-home .product-media img{transition:transform .55s cubic-bezier(.2,.75,.2,1)}
@media(hover:hover){.reference-home .product:hover .product-media img{transform:scale(1.045)}}
.reference-home .banner-slide .banner-copy{animation:cmBannerCopyReveal .55s ease both}
.reference-home .details{transition:transform .18s ease,box-shadow .25s ease}
.reference-home .details:active{transform:scale(.96)}
@media(prefers-reduced-motion:reduce){
 .intro:not(.hide) .intro-logo,.intro:not(.hide) .intro-box small,.intro:not(.hide) .intro-box h1,.intro:not(.hide) .intro-box p,.intro:not(.hide) .intro-enter,.reference-home .product.cm-reveal,.reference-home .banner-slide .banner-copy{animation:none!important}
 .reference-home .cat,.reference-home .product-media img,.reference-home .details,.reference-home .banner-track{transition:none!important}
 .reference-home .reference-hero,.reference-home .reference-hero-fallback,.reference-home .reference-banner,.reference-home .reference-section-head{animation:none!important}
 .reference-home .cat.active{transform:none}
}

</style>
<style>
/* Prevent an already-dismissed intro from flashing during navigation. */
html.cm-skip-menu-intro #intro{display:none!important}
</style>
<script>
(function () {
    const branch = @json($location->code);
    const mode = @json($introMode);
    if (mode === 'off') return;
    try {
        const key = 'tish_menu_intro_seen_' + branch;
        const visited = sessionStorage.getItem(key) === '1'
            || (mode === 'first_visit' && localStorage.getItem(key) === '1');
        if (visited) document.documentElement.classList.add('cm-skip-menu-intro');
    } catch (error) { /* Private browsing: normal intro remains available. */ }
})();
</script>
</head>
<body class="crisp-customer-menu reference-home tish-paper">
@php
 $floatingBrandingEnabled = true;
@endphp
@include('customer-menu.partials.brand-watermark')

@if($introMode !== 'off')
<div id="intro" class="intro" data-mode="{{ $introMode }}" @if($introImage) style="background-image:url('{{ $introImage }}')" @endif>
 <div class="intro-box">
  @if($introShowLogo && ($branding['logo'] ?? null))
   <img class="intro-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">
  @endif
  @if($introEyebrow !== '')<small>{{ $introEyebrow }}</small>@endif
  <h1>{{ $introTitle }}</h1>
  <p>{{ $introSubtitle }}</p>
  <button class="intro-enter" id="introEnter" type="button">{{ $introCta }}</button>
 </div>
</div>
@endif

<div class="app crisp-menu-app">
<main class="menu-area">
 <div class="shell">

  <div class="reference-masthead {{ !empty($theme['header_image']) ? 'is-illustrated' : '' }}"
       style="--masthead-height:{{ $theme['header_height'] ?? 340 }}px;--masthead-overlay:{{ ($theme['header_overlay'] ?? 15) / 100 }}">
   @if(!empty($theme['header_image']))
    <img class="reference-masthead-image" src="{{ $theme['header_image'] }}" alt="" fetchpriority="high"
         style="object-position:{{ $theme['header_position'] ?? 'center' }}">
   @endif
  <header class="reference-header">
   <div class="reference-header-row">
    <div class="reference-brand">
     <span class="reference-logo-square">
      @if($branding['logo'] ?? null)
       <img class="reference-client-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">
      @else
       <span aria-label="{{ $branding['name'] }}">{{ mb_substr($branding['name'] ?? '', 0, 1) }}</span>
      @endif
     </span>
     <div class="reference-brand-copy">
     <h1>{{ $branding['name'] }}</h1>
     <p>{{ $branding['tagline'] ?: $homeSubtitle }}</p>
     <a class="reference-branch" href="{{ route('customer-menu.index', ['choose' => 1]) }}" aria-label="تغيير الفرع">{{ $location->name }} <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></a>
     </div>
    </div>
    <div class="reference-actions">
     <a class="icon-btn" href="{{ route('customer-menu.cart', $location->code) }}" aria-label="السلة"><i class="fa-solid fa-bag-shopping"></i><span class="badge" id="cartBadgeTop">0</span></a>
     <a class="icon-btn" href="{{ route('customer-menu.favorites', $location->code) }}" aria-label="المفضلة"><i class="fa-regular fa-heart"></i><span class="badge" id="favBadgeTop">0</span></a>
    </div>
   </div>
  </header>


  @if($showSearch)
  <div class="tools">
   <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="searchInput" type="search" placeholder="{{ \App\Support\CustomerMenuCopy::searchPlaceholder() }}" autocomplete="off"></div>
   <button class="filter-square" type="button" id="openFullMenu" aria-label="فلترة الأصناف"><i class="fa-solid fa-sliders"></i></button>
  </div>
  @endif


  @if($showCategories)
  @php
    $categoryPriority = ['برجر لحم', 'برجر دجاج', 'برجر نباتي', 'الفرايز'];
    $homeCategories = collect($categories ?? [])->sortBy(static function ($category) use ($categoryPriority) {
        $priority = array_search(trim($category['name']), $categoryPriority, true);
        return $priority === false ? count($categoryPriority) : $priority;
    });
  @endphp
  <section class="reference-section" aria-labelledby="categoriesTitle">
   <div class="reference-section-head">
    <div>
     <h2 id="categoriesTitle">التصنيفات</h2>
    </div>
    <button class="reference-see-all" type="button" data-show-all>عرض الكل <i class="fa-solid fa-chevron-left"></i></button>
   </div>
   <div class="categories" id="categories">
    <button class="cat active" type="button" data-cat="all">
     <span class="cat-visual" style="--category-color:var(--primary)"><i class="fa-solid fa-utensils"></i></span>
     <b>الكل</b>
    </button>
    @foreach($homeCategories as $category)
     @php
      $iconClass = $categoryIconClasses[$category['icon_key'] ?? 'sparkles'] ?? 'fa-wand-magic-sparkles';
     @endphp
     <button class="cat" type="button" data-cat="{{ $category['id'] }}" data-cat-name="{{ $category['name'] }}">
      <span class="cat-visual" style="--category-color:var(--tish-paper-red)">
       @if(($theme['category_visual'] ?? 'icons') === 'images' && !empty($category['image']))
        <img src="{{ $category['image'] }}" alt="" loading="lazy" onerror="this.outerHTML='<i class=&quot;fa-solid {{ $iconClass }}&quot;></i>'">
       @else
        <i class="fa-solid {{ $iconClass }}"></i>
       @endif
      </span>
      <b>{{ $category['name'] }}</b>
     </button>
    @endforeach
   </div>
  </section>
  @endif


  @if(($theme['show_hero'] ?? true) && ($banners->isNotEmpty() || $coverImage))
  <div id="homePromotion" class="tish-paper-banner-frame">
   <section class="reference-hero tish-paper-top-ads" aria-label="إعلانات المنيو">
   @if($banners->isNotEmpty())
    <div class="banner-track" id="bannerTrack">
     @foreach($banners as $banner)
      @if($banner['link_url'])
       <a href="{{ $banner['link_url'] }}" class="banner-slide {{ ($banner['artwork_only'] ?? false) ? 'banner-artwork-only' : '' }}" @if($loop->first) data-banner-active @else inert @endif
          @unless(str_starts_with($banner['link_url'], '#')) target="_blank" rel="noopener" @endunless>
      @else
       <article class="banner-slide {{ ($banner['artwork_only'] ?? false) ? 'banner-artwork-only' : '' }}" @if($loop->first) data-banner-active @else inert @endif>
      @endif
        <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] ?: 'إعلان من ' . $branding['name'] }}" loading="eager">
        @if(!($banner['artwork_only'] ?? false) && ($banner['title'] || $banner['subtitle'] || $banner['badge_text'] || $banner['cta_text']))
        <div class="banner-copy">
         @if($banner['badge_text'])<span class="tag">{{ $banner['badge_text'] }}</span>@endif
         @if($banner['title'])<h2>{{ $banner['title'] }}</h2>@endif
         @if($banner['subtitle'])<p>{{ $banner['subtitle'] }}</p>@endif
         @if($banner['cta_text'])<span class="banner-cta">{{ $banner['cta_text'] }} <i class="fa-solid fa-arrow-left"></i></span>@endif
        </div>
        @endif
      @if($banner['link_url'])
       </a>
      @else
       </article>
      @endif
     @endforeach
    </div>
    @if($banners->count() > 1)
     <div class="banner-dots" id="bannerDots">
      @foreach($banners as $index => $banner)
       <button type="button" class="{{ $index === 0 ? 'active' : '' }}" data-banner-dot="{{ $index }}" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Slide ' . ($index + 1) : 'العرض ' . ($index + 1) }}"></button>
      @endforeach
     </div>
    @endif
   @else
    <article class="reference-hero-fallback tish-paper-cover-banner" @if($coverImage) style="background-image:linear-gradient(90deg,rgba(8,4,5,.12),rgba(8,4,5,.78)),url('{{ $coverImage }}')" @endif>
     <div class="banner-copy">
      <span class="tag">اختيارات اليوم</span>
      <h2>{{ $branding['tagline'] ?: 'مذاق استثنائي' }}</h2>
      <p>اختار صنفك المفضل واطلبه مباشرة من المنيو.</p>
      <span class="banner-cta" data-scroll-menu>اطلب الآن <i class="fa-solid fa-arrow-left"></i></span>
     </div>
    </article>
   @endif
  </section>
  </div>
  @endif
  </div>{{-- /reference-masthead --}}


  @if($showFeatured)
  <section class="reference-section" id="featuredSection" aria-labelledby="featuredTitle">
   <div class="reference-section-head">
    <div class="reference-section-copy">
     <i class="fa-solid fa-star reference-section-icon"></i>
     <div><h2 id="featuredTitle">{{ $featuredTitle }}</h2><p>اختيارات مميزة من المنيو</p></div>
    </div>
    <a class="reference-see-all" href="{{ route('customer-menu.products', $location->code) }}">عرض الكل <i class="fa-solid fa-chevron-left"></i></a>
   </div>
   <div class="cm-featured-showcase" id="featuredShowcase"
        role="region" aria-roledescription="carousel" aria-label="{{ $featuredTitle }}" tabindex="0">
     <button class="cm-showcase-nav cm-showcase-prev" type="button" data-showcase-prev
             aria-label="الصنف السابق" aria-controls="featuredGrid">
       <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
     </button>
     <div class="cm-showcase-stage" id="featuredGrid" aria-live="off"></div>
     <button class="cm-showcase-nav cm-showcase-next" type="button" data-showcase-next
             aria-label="الصنف التالي" aria-controls="featuredGrid">
       <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
     </button>
     <div class="cm-showcase-dots" id="featuredDots" aria-label="صفحات الأصناف المميزة"></div>
   </div>
  </section>
  @endif

  <section class="reference-section catalog-section" id="menuCatalog" aria-labelledby="catalogTitle">
   <div class="reference-section-head">
    <div><h2 id="catalogTitle">كل الأصناف</h2><p id="catalogSubtitle">كل اللي بتحبه بمكان واحد</p></div>
    <a class="reference-see-all" href="{{ route('customer-menu.products', $location->code) }}">المنيو كامل <i class="fa-solid fa-chevron-left"></i></a>
   </div>
   <div class="grid" id="productGrid"></div>
  </section>
  <div class="tish-home-install">@include('customer-menu.partials.pwa-install')</div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'home'])
@include('customer-menu.partials.cart-engine')
<script src="{{ asset('assets/js/customer-menu/tish-product-circle-card-v8.js') }}?v=8"></script>
<script src="{{ asset('assets/js/customer-menu/tish-paper-motion-v10.js') }}?v=11"></script>
<script src="{{ asset('assets/js/customer-menu/featured-carousel.js') }}?v=3"></script>

<script>
(function () {
    const CM = window.CustomerMenu;
    const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
    const productUrlBase = @json(route('customer-menu.product.show', [$location->code, '__ID__']));
    const productsUrl = @json(route('customer-menu.products', $location->code));
    const featuredLimit = @json($featuredLimit);
    const popularProductIds = @json($popularProductIds ?? []);
    const popularSet = new Set(Array.from(popularProductIds).map(String));
    const showDescriptions = @json($showDescriptions);
    let activeCat = 'all';

    function productUrl(id) {
        return productUrlBase.replace('__ID__', encodeURIComponent(id));
    }

    // Shared ProductCircleCard for homepage catalog and popular carousel.
    function renderCard(product) {
        return window.TishProductCircleCard.render(product, {
            productUrl: productUrl,
            isPopular: popularSet.has(String(product.id)),
        });
    }

    function filteredProducts() {
        const input = document.getElementById('searchInput');
        const query = input ? input.value.trim().toLowerCase() : '';

        return CM.PRODUCTS.filter(function (product) {
            const inCategory = activeCat === 'all' || product.category === String(activeCat);
            const haystack = (product.name + ' ' + product.description + ' ' + product.category_name).toLowerCase();
            return inCategory && (!query || haystack.includes(query));
        });
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    function animateVisibleCards() {
        window.TishPaperMotion?.observe(document.getElementById('featuredGrid'));
        window.TishPaperMotion?.observe(document.getElementById('productGrid'));
    }

    function prioritizeBurgers(products) {
        const burgers = products.filter(product => /برجر|برغر|burger/i.test(product.category_name || ''));
        return burgers.concat(products.filter(product => !burgers.includes(product)));
    }

    function render() {
        const rows = filteredProducts();
        const ranked = popularProductIds
            .map(function (id) { return CM.product(id); })
            .filter(function (product) { return product && product.available; });
        const fallback = CM.PRODUCTS.filter(function (product) {
            return product.available && !ranked.some(function (rankedProduct) {
                return String(rankedProduct.id) === String(product.id);
            });
        });
        const featured = prioritizeBurgers(ranked).concat(prioritizeBurgers(fallback)).slice(0, featuredLimit);
        const featuredGrid = document.getElementById('featuredGrid');
        const featuredSection = document.getElementById('featuredSection');
        const hasFocus = activeCat !== 'all' || Boolean(document.getElementById('searchInput')?.value.trim());

        if (featuredGrid) {
            featuredGrid.innerHTML = featured.length
                ? featured.map(function (product) {
                    return '<div class="cm-showcase-slide" data-showcase-slide data-offset="outside">' +
                        renderCard(product) + '</div>';
                }).join('')
                : '<div class="cm-featured-empty">لا توجد أصناف مميزة متاحة حالياً.</div>';
        }
        if (featuredSection) featuredSection.hidden = hasFocus;
        const promotion = document.getElementById('homePromotion');
        if (promotion) promotion.hidden = hasFocus;
        window.CustomerMenuFeaturedCarousel?.refresh();

        document.getElementById('productGrid').innerHTML = rows.length
            ? rows.map(function (product) { return renderCard(product, false); }).join('')
            : '<div class="empty">لا توجد أصناف مطابقة لبحثك.</div>';

        const selected = document.querySelector('[data-cat].active');
        document.getElementById('catalogTitle').textContent = activeCat === 'all'
            ? 'كل الأصناف'
            : (selected?.dataset.catName || 'الأصناف');
        document.getElementById('catalogSubtitle').textContent = menuF('عدد {count} أصناف', {count:rows.length});
        animateVisibleCards();
    }

    document.addEventListener('click', function (event) {
        const favorite = event.target.closest('[data-fav]');
        if (favorite) {
            event.preventDefault();
            CM.toggleFavorite(favorite.dataset.fav);
            window.TishPaperMotion?.syncFavorites(favorite.dataset.fav, CM.isFavorite(favorite.dataset.fav));
            return;
        }

        const add = event.target.closest('[data-add]');
        if (add) {
            event.preventDefault();
            const product = CM.product(add.dataset.add);
            if (!product || !product.available || add.disabled) return;
            window.TishProductCircleCard.addFromButton(add, product, productUrl(product.id));
            return;
        }

        if (event.target.closest('[data-show-all]')) {
            activeCat = 'all';
            document.querySelectorAll('[data-cat]').forEach(function (button) {
                button.classList.toggle('active', button.dataset.cat === 'all');
            });
            render();
            document.getElementById('menuCatalog').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (event.target.closest('[data-scroll-menu]')) {
            document.getElementById('menuCatalog').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    document.getElementById('categories')?.addEventListener('click', function (event) {
        const button = event.target.closest('[data-cat]');
        if (!button) return;
        activeCat = button.dataset.cat;
        document.querySelectorAll('[data-cat]').forEach(function (item) {
            item.classList.toggle('active', item === button);
        });
        render();
        window.TishPaperMotion?.categories(document.getElementById('categories'), button);
        document.getElementById('menuCatalog').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('searchInput')?.addEventListener('input', render);
    document.getElementById('openFullMenu')?.addEventListener('click', function () {
        const query = document.getElementById('searchInput')?.value.trim() || '';
        window.location.href = productsUrl + (query ? '?q=' + encodeURIComponent(query) : '');
    });

    const track = document.getElementById('bannerTrack');
    const dots = Array.from(document.querySelectorAll('[data-banner-dot]'));
    const slides = track ? Array.from(track.querySelectorAll('.banner-slide')) : [];
    let bannerIndex = 0;
    let bannerTimer = null;

    function showBanner(index) {
        if (!slides.length) return;
        bannerIndex = (index + slides.length) % slides.length;
        slides.forEach(function (slide, slideIndex) {
            const active = slideIndex === bannerIndex;
            slide.toggleAttribute('data-banner-active', active);
            slide.inert = !active;
        });
        dots.forEach(function (dot, dotIndex) { dot.classList.toggle('active', dotIndex === bannerIndex); });
    }

    if (track && slides.length > 1) {
        showBanner(0);
        const hero = track.closest('.reference-hero');
        let hovered = false;
        let focused = false;

        function syncBannerTimer() {
            clearInterval(bannerTimer);
            bannerTimer = null;
            if (reducedMotion.matches || document.hidden || hovered || focused) return;
            bannerTimer = setInterval(function () { showBanner(bannerIndex + 1); }, 5500);
        }

        if (hero) {
            hero.addEventListener('mouseenter', function () { hovered = true; syncBannerTimer(); });
            hero.addEventListener('mouseleave', function () { hovered = false; syncBannerTimer(); });
            hero.addEventListener('focusin', function () { focused = true; syncBannerTimer(); });
            hero.addEventListener('focusout', function () {
                window.setTimeout(function () {
                    focused = hero.contains(document.activeElement);
                    syncBannerTimer();
                }, 0);
            });
        }
        document.addEventListener('visibilitychange', syncBannerTimer);
        if (typeof reducedMotion.addEventListener === 'function') {
            reducedMotion.addEventListener('change', syncBannerTimer);
        } else if (typeof reducedMotion.addListener === 'function') {
            reducedMotion.addListener(syncBannerTimer);
        }
        dots.forEach(function (dot, index) {
            dot.addEventListener('click', function () {
                showBanner(index);
                syncBannerTimer();
            });
        });
        syncBannerTimer();
    }

    const intro = document.getElementById('intro');
    if (intro) {
        const mode = intro.dataset.mode;
        const key = 'tish_menu_intro_seen_' + CM.LOCATION_CODE;
        const hasSeenIntro = function () {
            try {
                return sessionStorage.getItem(key) === '1'
                    || (mode === 'first_visit' && localStorage.getItem(key) === '1');
            } catch (error) { return false; }
        };
        const hideIntro = function () {
            try {
                sessionStorage.setItem(key, '1');
                if (mode === 'first_visit') localStorage.setItem(key, '1');
            } catch (error) { /* Storage may be disabled. */ }
            intro.classList.add('hide');
            window.setTimeout(() => intro.remove(), 500);
        };
        if (hasSeenIntro() || document.documentElement.classList.contains('cm-skip-menu-intro')) {
            intro.remove();
        } else {
            document.getElementById('introEnter')?.addEventListener('click', hideIntro);
            if (reducedMotion.matches) intro.classList.add('cm-reduced-motion');
        }
    }

    render();
    window.TishPaperMotion?.categories(document.getElementById('categories'));
})();
</script>
</body>
</html>

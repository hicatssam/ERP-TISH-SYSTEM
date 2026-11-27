<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>شاشة الزبون — {{ $brand }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-fonts-v1.css') }}?v=2">
<style>
:root{--ink:#28221d;--red:#a91f17;--muted:#67594c;--paper:#f3eee6;--cream:#fff4e7}
*{box-sizing:border-box}
html,body{margin:0;width:100%;height:100%;overflow:hidden}
body.tish-paper{background:var(--paper) url('/assets/tish-paper/paper/background.webp') center/540px auto repeat;color:var(--ink);font-family:"TISH Noto",Arial,sans-serif;line-height:1.5;font-kerning:normal;text-rendering:optimizeLegibility}
[hidden]{display:none!important}
#screen{display:grid;grid-template-columns:minmax(0,1fr);height:100%;height:100dvh;position:relative;padding:clamp(10px,1.5vw,26px);gap:clamp(10px,1.5vw,26px)}
#screen.active{grid-template-columns:minmax(0,var(--bill,40%)) minmax(0,1fr)}
#screen::before{content:'';position:absolute;inset:0 0 auto;height:clamp(9px,1.4vw,23px);background:url('/assets/tish-paper/decor/red-torn-top.svg') center/100% 100% no-repeat;pointer-events:none;z-index:5}
#media{position:relative;min-width:0;min-height:0;display:grid;place-items:center;overflow:hidden;background:none;isolation:isolate}
#media::before{content:'';position:absolute;inset:0;background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;filter:drop-shadow(0 7px 5px #54362225);z-index:-1;pointer-events:none}
#mediaSlot{position:absolute;inset:clamp(8px,1.2vw,20px);overflow:hidden}
#mediaSlot:empty{display:none}
#mediaSlot img,#mediaSlot video{display:block;width:100%;height:100%;object-fit:contain;background:transparent}
#fallback{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:clamp(6px,1.6vh,18px);width:100%;height:100%;text-align:center;padding:clamp(20px,4vw,65px) clamp(15px,3vw,50px) clamp(64px,10vh,100px);overflow:hidden}
#fallback::before,#fallback::after{content:'';position:absolute;width:clamp(60px,14vw,210px);height:clamp(60px,14vw,210px);background:url('/assets/tish-paper/decor/halftone.svg') center/contain no-repeat;pointer-events:none;opacity:.55;z-index:-1}
#fallback::before{top:12px;right:10px}#fallback::after{bottom:12px;left:10px;transform:rotate(180deg)}
#fallback .brand-mark{display:block;width:min(58%,38vh,330px);max-height:42%;height:auto;object-fit:contain;filter:drop-shadow(0 8px 5px #49301c30)}
#fallback .brand-slogan{display:block;width:min(80%,460px);max-height:16vh;object-fit:contain}
#fallback .brand-name{font-size:clamp(17px,2vw,30px);font-weight:800;color:var(--muted);margin:0;line-height:1.3}
#bill{min-width:0;min-height:0;position:relative;isolation:isolate;display:flex;flex-direction:column;padding:clamp(18px,2.5vw,40px);gap:clamp(8px,1.4vh,17px)}
#bill::before{content:'';position:absolute;inset:0;background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;filter:drop-shadow(0 7px 5px #54362235);z-index:-1;pointer-events:none}
#bill header{display:flex;flex-direction:column;align-items:flex-start;padding-bottom:12px;border-bottom:2px dashed #a91f1755}
#bill header small{font-size:clamp(11px,1.05vw,17px);color:var(--muted);font-weight:700;overflow-wrap:anywhere}
h2{font-size:clamp(24px,3vw,48px);margin:0;color:var(--red);font-weight:900;line-height:1.5}
#items{flex:1;min-height:0;overflow:auto;scrollbar-width:thin}
.line{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:start;border-bottom:1px dashed #a68b7080;padding:clamp(7px,1.6vh,18px) 2px;font-size:clamp(15px,1.6vw,27px);line-height:1.5}
.line strong{font-weight:800;overflow-wrap:anywhere}
.line small{display:block;font-size:.7em;color:var(--muted);margin-top:3px;overflow-wrap:anywhere}
.amount{direction:ltr;unicode-bidi:isolate;white-space:nowrap;font-weight:800;font-variant-numeric:tabular-nums}
.summary{display:flex;justify-content:space-between;align-items:center;font-size:clamp(15px,1.55vw,25px);gap:10px;margin:4px 0}
#discountRow{color:var(--red)}
.total{position:relative;isolation:isolate;color:var(--cream);padding:clamp(13px,1.6vw,26px) clamp(15px,2vw,30px);font-weight:900;font-size:clamp(22px,2.6vw,44px);margin-top:10px;min-height:70px;line-height:1.3}
.total::before{content:'';position:absolute;inset:0;background:url('/assets/tish-paper/paper/price-ticket.webp') center/100% 100% no-repeat;filter:drop-shadow(0 4px 3px #5c291b30);z-index:-1}
#thanks{position:absolute;inset:0;z-index:2;background:var(--paper) url('/assets/tish-paper/paper/background.webp') center/540px auto repeat;display:grid;place-content:center;justify-items:center;text-align:center;padding:30px;color:var(--red)}
#thanks .thanks-logo{width:clamp(90px,16vh,175px);height:auto;object-fit:contain;filter:drop-shadow(0 5px 4px #49301c30)}
#thanks h1{font-size:clamp(26px,4.5vw,68px);margin:10px 0;font-weight:900;line-height:1.5}
#thanks p{color:var(--ink);font-weight:800;font-size:clamp(18px,2.5vw,36px);margin:0 0 15px}
#thanks strong{background:url('/assets/tish-paper/paper/price-ticket.webp') center/100% 100% no-repeat;color:var(--cream);font-size:clamp(24px,4vw,60px);direction:ltr;unicode-bidi:isolate;padding:14px 42px;line-height:1.3}
.controls{position:absolute;bottom:clamp(15px,2vw,30px);left:clamp(15px,2vw,30px);z-index:3;display:flex;gap:8px}
.controls button{background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;color:var(--red);border:0;min-height:42px;padding:8px 15px;font-size:clamp(10px,1vw,14px);font-weight:800;cursor:pointer;filter:drop-shadow(0 3px 2px #49301c35)}
.controls button:focus-visible{outline:2px solid var(--red);outline-offset:2px}
#connection{position:absolute;top:26px;left:26px;z-index:4;background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;color:var(--red);padding:8px 16px;font-size:13px;font-weight:800}
@media(max-width:620px){#screen.active{grid-template-columns:1fr;grid-template-rows:minmax(0,62%) minmax(0,1fr)}#screen{padding:10px;gap:6px}#bill{padding:14px;gap:4px}.total{padding:10px 18px;min-height:48px}#fallback{padding:12px 12px 50px;gap:5px}#fallback .brand-mark{max-height:42%;width:auto;max-width:55%}#fallback .brand-slogan{max-height:11vh;width:min(65%,240px)}}
@media(max-height:480px) and (min-width:621px){#bill{padding:12px 20px;gap:3px}.total{min-height:44px;padding:10px 20px}#bill header{padding-bottom:4px}.line{padding:4px 0}#fallback{padding-block:12px 46px}}
</style>
</head>
<body class="tish-paper tish-pos-customer-screen">
<main id="screen">
<section id="bill" hidden>
<header><small><bdi>{{ $brand }}</bdi> — {{ $location->name }}</small><h2 id="billTitle">طلبك</h2></header>
<div id="items"></div>
<div><div class="summary"><span>المجموع</span><span id="subtotal" class="amount"></span></div>
<div class="summary" id="discountRow" hidden><span>الخصم</span><span id="discount" class="amount"></span></div>
<div class="summary total"><span>الإجمالي</span><span id="total" class="amount"></span></div></div>
</section>
<section id="media">
    <div id="fallback">
        <img class="brand-mark" src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="{{ $brand }}">
        <img class="brand-slogan" src="{{ asset('assets/tish-paper/brand/slogan.svg') }}" alt="Hear it. Feel it.">
        <p class="brand-name" lang="en" dir="ltr">{{ $brand }}</p>
    </div>
    <div id="mediaSlot"></div>
</section>
<section id="thanks" hidden><img class="thanks-logo" src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt=""><h1>شكراً لاختيارك {{ $brand }}!</h1><p>تم تسجيل طلبك</p><strong id="orderNumber"></strong></section>
<div class="controls"><button id="fullscreen" type="button">ملء الشاشة ⛶</button><button id="sound" type="button" aria-pressed="false">الصوت مكتوم</button></div>
<div id="connection">بانتظار ربط الكاشير</div>
</main>
@php
    $customerScreenConfig = [
        'scope' => $scope,
        'settings' => $config,
        'playlistUrl' => route('restaurant.pos.customer-screen.playlist', ['location_id' => $location->id], false),
    ];
@endphp
<script>
window.TishCustomerScreenConfig = {{ \Illuminate\Support\Js::from($customerScreenConfig) }};
</script>
<script src="{{ asset('assets/js/pos-customer-screen.js') }}?v=1"></script>
</body></html>

<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>شاشة الزبون — {{ $brand }}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f6e8d4;color:#27201b;font-family:Arial,sans-serif;height:100dvh;overflow:hidden} [hidden]{display:none!important}
#screen{display:grid;grid-template-columns:minmax(0,1fr);height:100%;position:relative}
#screen.active{grid-template-columns:minmax(0,var(--bill,40%)) minmax(0,1fr)}
#media{position:relative;min-width:0;min-height:0;background:#a71c13;display:grid;place-items:center;overflow:hidden}
#media img,#media video{width:100%;height:100%;position:absolute;inset:0;object-fit:contain;background:#251b16}
#fallback{text-align:center;color:#fff0da;padding:2rem}#fallback img{position:static;width:min(45vw,300px);height:auto;background:none;object-fit:contain}#fallback h1{font-size:clamp(36px,9vw,120px);margin:0}#fallback p{font-size:clamp(18px,3vw,38px);font-weight:bold}
#bill{min-width:0;min-height:0;background:#fff3e0 url('/assets/tish-paper/paper/background.webp') center/540px auto;display:flex;flex-direction:column;padding:clamp(16px,2.6vw,40px);gap:16px;border-left:6px solid #b51c11}
#bill header{border-bottom:3px solid #b51c11;padding-bottom:12px}#bill header small{color:#a71c13;font-weight:bold}h2{font-size:clamp(22px,3vw,46px);margin:6px 0}
#items{flex:1;min-height:0;overflow:auto} .line{display:grid;grid-template-columns:1fr auto;gap:12px;border-bottom:1px dashed #a68b70;padding:14px 0;font-size:clamp(16px,1.9vw,29px)}.line small{display:block;font-size:.72em;color:#76604c;margin-top:5px}.amount{direction:ltr;white-space:nowrap;font-weight:900}
.summary{display:flex;justify-content:space-between;font-size:clamp(15px,1.8vw,26px);gap:10px;margin:6px 0}
.total{background:#b51c11;color:#fff3e0;padding:18px;font-weight:900;font-size:clamp(24px,3.4vw,54px);border-radius:5px}
#thanks{position:absolute;inset:0;z-index:2;background:#f6e8d4 url('/assets/tish-paper/paper/background.webp') center/540px auto;display:grid;place-content:center;text-align:center;padding:24px;color:#b51c11}
#thanks h1{font-size:clamp(36px,7vw,100px);margin:10px}#thanks strong{font-size:clamp(25px,5vw,65px);direction:ltr}
.controls{position:absolute;bottom:12px;left:12px;z-index:3;display:flex;gap:6px}.controls button{background:#241c17b8;color:#fff;border:1px solid #ffffff55;border-radius:8px;padding:9px;font:inherit;cursor:pointer}
#connection{position:absolute;top:10px;left:10px;z-index:4;background:#2b2119b8;color:white;padding:7px 12px;border-radius:8px;font-size:13px}
@media(max-width:620px){#screen.active{grid-template-columns:1fr;grid-template-rows:minmax(0,62%) minmax(0,1fr)}#bill{border-left:0;border-bottom:4px solid #b51c11;padding:12px;gap:4px}.total{padding:10px}}
</style>
</head>
<body>
<main id="screen">
<section id="bill" hidden>
<header><small>{{ $brand }} — {{ $location->name }}</small><h2 id="billTitle">طلبك</h2></header>
<div id="items"></div>
<div><div class="summary"><span>المجموع</span><span id="subtotal" class="amount"></span></div>
<div class="summary" id="discountRow" hidden><span>الخصم</span><span id="discount" class="amount"></span></div>
<div class="summary total"><span>الإجمالي</span><span id="total" class="amount"></span></div></div>
</section>
<section id="media"><div id="fallback"><img src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt=""><h1>{{ $brand }}</h1><p>Hear it. Feel it.</p></div><div id="mediaSlot"></div></section>
<section id="thanks" hidden><h1>شكراً لاختيارك {{ $brand }}!</h1><p>تم تسجيل طلبك</p><strong id="orderNumber"></strong></section>
<div class="controls"><button id="fullscreen" type="button">ملء الشاشة ⛶</button><button id="sound" type="button" aria-pressed="false">الصوت مكتوم</button></div>
<div id="connection">بانتظار ربط الكاشير</div>
</main>
<script>
window.TishCustomerScreenConfig = @json(['scope'=>$scope, 'settings'=>$config, 'playlistUrl'=>route('restaurant.pos.customer-screen.playlist', ['location_id'=>$location->id])]);
</script>
<script src="{{ asset('assets/js/pos-customer-screen.js') }}?v=1"></script>
</body></html>

<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    $brandLogo = $branding['logo'] ?? $branding['logo_small'] ?? $branding['favicon'] ?? null;
    $brandName = $branding['name'] ?? 'طلباتك';
@endphp
<title>طلباتي — {{ $brandName }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:Cairo,Tajawal,Arial,sans-serif;padding-bottom:calc(102px + var(--safe-bottom))}
a{text-decoration:none;color:inherit}.wrap{width:min(720px,calc(100% - 28px));margin:auto}
.topbar{position:sticky;top:0;z-index:30;background:color-mix(in srgb,var(--surface) 96%,transparent);backdrop-filter:blur(18px);border-bottom:1px solid color-mix(in srgb,var(--text) 7%,transparent)}
.topin{height:72px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;align-items:center;gap:10px}.brand img{width:54px;height:54px;object-fit:contain}.brand b,.brand small{display:block}.brand small{font-size:.68rem;color:var(--muted);margin-top:2px}
.back{width:40px;height:40px;display:grid;place-items:center;border-radius:13px;background:var(--surface);border:1px solid color-mix(in srgb,var(--text) 8%,transparent);font-size:20px}
main{padding:22px 0}.titleRow{margin-bottom:18px}.titleRow h1{margin:0 0 4px;font-size:1.35rem}.titleRow p{margin:0;color:var(--muted);font-size:.8rem}
.orders{display:grid;gap:10px}.order{display:flex;align-items:center;gap:12px;padding:14px;background:var(--surface);border-radius:17px;border:1px solid color-mix(in srgb,var(--text) 7%,transparent)}
.orderIcon{width:48px;height:48px;flex:0 0 48px;display:grid;place-items:center;border-radius:14px;background:color-mix(in srgb,var(--accent) 18%,var(--surface));color:var(--primary);font-size:20px}
.orderInfo{min-width:0;flex:1}.orderInfo strong{display:block;font-size:.92rem}.orderInfo small{display:block;color:var(--muted);font-size:.7rem;margin-top:3px}.orderInfo .orderStatus{color:var(--primary);font-weight:900}.orderInfo .orderStatus[data-state="cancelled"]{color:#c73f3f}.orderInfo .orderStatus[data-state="ready"],.orderInfo .orderStatus[data-state="completed"]{color:#18864b}
.arrow{color:var(--primary);font-size:20px}.empty{text-align:center;padding:70px 20px}.emptyIcon{font-size:42px}.empty h2{font-size:1rem;margin:12px 0 5px}.empty p{font-size:.78rem;color:var(--muted);margin:0 0 16px}.primary{display:inline-flex;padding:11px 18px;border-radius:13px;background:var(--primary);color:#fff;font-weight:900;font-size:.82rem}
@media(min-width:700px){.wrap{width:min(920px,calc(100% - 40px))}.orders{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:561px){body{padding-bottom:30px}}
</style>
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-circular-ui.css') }}?v=9">
</head>
<body class="cm-my-orders">
<header class="topbar"><div class="wrap topin">
<a class="brand" href="{{ route('customer-menu.show',$location->code) }}">
@if($brandLogo)<img src="{{ $brandLogo }}" alt="{{ $brandName }}">@endif
<span><b>{{ $brandName }}</b><small>{{ $location->name }}</small></span>
</a>
<a class="back" href="{{ route('customer-menu.show',$location->code) }}" aria-label="العودة">←</a>
</div></header>
<main class="wrap">
<div class="titleRow"><h1>طلباتي</h1><p>الطلبات التي أنشأتها من هذا الجهاز.</p></div>
<section id="orders" class="orders" aria-live="polite"></section>
</main>
@include('customer-menu.partials.bottom-nav', ['activeNav' => 'orders'])
@include('customer-menu.partials.nav-badge-sync')
<script>
const root=document.querySelector('#orders');
const menuT = text => window.CustomerMenuI18n.t(text);
const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
const locationCode=@json($location->code);
const menuUrl=@json(route('customer-menu.show',$location->code));
const ordersKey='customer_menu_orders';
function esc(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}

function localUrl(value){
    try {
        const url=new URL(String(value||''),window.location.origin);
        return url.origin===window.location.origin?url.href:null;
    } catch(e) { return null; }
}

function statusUrl(item){
    const explicit=localUrl(item.status_url);
    if(explicit)return explicit;
    const track=localUrl(item.url);
    return track?track.replace(/\/$/,'')+'/status':null;
}

const fallbackLabels={
    received:'تم استلام الطلب',accepted:'تم تأكيد الطلب',preparing:'قيد التحضير',
    ready:'الطلب جاهز',completed:'تم التسليم',cancelled:'تم إلغاء الطلب'
};

let rows=[];try{rows=JSON.parse(localStorage.getItem(ordersKey)||'[]')||[]}catch(e){rows=[]}
rows=rows
    .filter(item=>item&&String(item.location||item.location_code||'')===String(locationCode)&&localUrl(item.url))
    .slice(-30)
    .reverse();

root.innerHTML=rows.length?rows.map((item,index)=>{
    const state=String(item.state||'received').toLowerCase();
    const status=menuT(item.status_label||fallbackLabels[state]||'جاري تحديث الحالة');
    return `<a class="order" href="${esc(localUrl(item.url))}" data-order-index="${index}">
        <span class="orderIcon">✓</span>
        <span class="orderInfo">
            <strong>${esc(menuF('طلب #{number}',{number:item.order_number||item.number||'—'}))}</strong>
            <small class="orderStatus" data-state="${esc(state)}">${esc(status)}</small>
            <small>اضغط لعرض التفاصيل وتتبع الحالة</small>
        </span>
        <span class="arrow">←</span>
    </a>`;
}).join(''):`<div class="empty"><div class="emptyIcon">🛍️</div><h2>لا توجد طلبات بعد</h2><p>بعد إرسال أول طلب سيظهر هنا تلقائيًا.</p><a class="primary" href="${menuUrl}">ابدأ طلبًا جديدًا</a></div>`;

let refreshingStatuses = false;
async function refreshStatuses(){
    if(document.hidden||!rows.length||refreshingStatuses)return;
    refreshingStatuses=true;
    try {
        // Keep at most four requests in flight, even for a returning customer
        // with 30 saved orders. The open order tracking page remains 5-second live.
        let nextIndex=0;
        const updateOne=async()=>{
            while(nextIndex<rows.length&&!document.hidden){
                const index=nextIndex++;
                const item=rows[index];
                const endpoint=statusUrl(item);
                if(!endpoint)continue;
                try {
                    const response=await fetch(endpoint,{headers:{Accept:'application/json'},cache:'no-store'});
                    if(!response.ok)continue;
                    const data=await response.json();
                    const element=root.querySelector(`[data-order-index="${index}"] .orderStatus`);
                    if(!element)continue;
                    const state=String(data.state||'received').toLowerCase();
                    element.dataset.state=state;
                    element.textContent=menuT(data.label||fallbackLabels[state]||'تم تحديث الطلب');
                } catch(error) { /* Keep the last known state while offline. */ }
            }
        };
        await Promise.all(Array.from({length:Math.min(4,rows.length)},()=>updateOne()));
    }finally{
        refreshingStatuses=false;
    }
}

refreshStatuses();
setInterval(refreshStatuses,20000);
document.addEventListener('visibilitychange',()=>{
    if(!document.hidden)refreshStatuses();
});
</script>
</body></html>

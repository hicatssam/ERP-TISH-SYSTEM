<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#101c2d">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ route('orders-app.manifest') }}">
<title>طلبات دهب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{font-family:'Cairo',Tahoma,sans-serif;color:#1d293b;background:#f5f6f8;font-synthesis:none}
*{box-sizing:border-box}body{margin:0;font-size:13px}button,input{font:inherit}button{cursor:pointer}
header{background:#202b3c;color:#fff;padding:13px max(18px,calc((100vw - 1160px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;border-bottom:2px solid #c99b2a}
header h1{font-size:20px;line-height:1.5;margin:0;font-weight:800}header small{color:#e6c77d;font-size:11px}
main{max-width:1160px;margin:auto;padding:15px 16px}
h2{font-size:17px;margin:0 0 4px;font-weight:800}p{line-height:1.8}.muted{color:#687589}
.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}
button,.button{border:1px solid #d5dae3;border-radius:8px;padding:8px 13px;background:#fff;color:#1d293b;text-decoration:none;font-weight:700}
button:hover,.button:hover{border-color:#b8994b}.gold{background:#d6a128;color:#fff;border-color:#d6a128}.dark{background:#34445c;color:#fff;border-color:#34445c}
.panel{background:#fff;border:1px solid #e2e5eb;border-radius:10px;padding:10px 12px;margin:12px 0 14px}
.tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:stretch;gap:3px;border-bottom:1px solid #d9dee7}
.tabs button{border:0;border-bottom:2px solid transparent;border-radius:6px 6px 0 0;background:transparent;color:#667386;padding:9px 4px;font-size:11px;white-space:normal;min-width:0}
.tabs button[aria-selected="true"]{border-bottom-color:#c99b2a;color:#233249;background:#fff;font-weight:800}
.tab-count{font-size:11px;background:#eff1f5;border-radius:6px;padding:1px 6px;margin-inline-start:5px}
.tabs button[aria-selected="true"] .tab-count{background:#f6ead0;color:#806018}
.search{padding:10px 12px;border:1px solid #cfd5df;border-radius:8px;min-width:190px;flex:1}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,310px),1fr));gap:9px}
.card{border:1px solid #e1e4e9;background:white;border-radius:9px;padding:12px;display:flex;flex-direction:column;gap:7px;border-right:3px solid #d5ad52}
.card h3{font-size:13px;margin:0;line-height:1.65}.card p{margin:0;font-size:12px;overflow-wrap:anywhere;color:#546071}
.card .button{display:block;text-align:center;margin-top:auto;padding:8px}
.tag{background:#f8efdb;color:#86651d;border-radius:5px;padding:3px 7px;font-size:11px}
.toolbar{display:flex;align-items:center;gap:9px;flex-wrap:wrap;justify-content:space-between}
#alertStatus{font-size:11px}.danger{color:#b32020}.empty{padding:35px;text-align:center;background:white;border-radius:9px;grid-column:1/-1}
@media(max-width:580px){header{padding:10px 12px;gap:8px}header h1{font-size:18px}header .actions{gap:6px}header .actions button,header .actions .button{padding:7px 9px;font-size:11px}main{padding:12px 10px}.tabs button{padding:10px 3px}.cards{grid-template-columns:1fr}.toolbar h2{font-size:15px}}
</style>
</head>
<body>
<header>
 <div><small>حلويات دهب</small><h1>طلبات دهب</h1></div>
 <div class="actions">
  <button id="installButton" type="button" class="gold">تثبيت التطبيق</button>
  <button id="soundButton" type="button" class="dark">تفعيل صوت التنبيه</button>
  <a href="{{ route('dashboard') }}" class="button">العودة للنظام</a>
 </div>
</header>
<main>
 <div class="toolbar"><div><h2>مركز متابعة الطلبات</h2><p class="muted" style="margin:0">الطلبات الواردة حسب صلاحيات الحساب</p></div><span id="alertStatus" role="status">جاري الاتصال...</span></div>
 <div class="panel">
  <nav class="tabs" role="tablist" aria-label="أقسام الطلبات">
   <button type="button" role="tab" data-filter="all" aria-selected="true">الكل <span class="tab-count" data-count="all">0</span></button>
   <button type="button" role="tab" data-filter="cake" aria-selected="false">الكيك <span class="tab-count" data-count="cake">0</span></button>
   <button type="button" role="tab" data-filter="sweets" aria-selected="false">الحلويات <span class="tab-count" data-count="sweets">0</span></button>
   <button type="button" role="tab" data-filter="special" aria-selected="false">الكيك الخاص <span class="tab-count" data-count="special">0</span></button>
   <button type="button" role="tab" data-filter="branch-cake" aria-selected="false">كيك الفروع <span class="tab-count" data-count="branch-cake">0</span></button>
   <button type="button" role="tab" data-filter="branch-sweets" aria-selected="false">حلويات الفروع <span class="tab-count" data-count="branch-sweets">0</span></button>
  </nav>
  <div class="actions" style="margin-top:13px"><input id="search" class="search" type="search" placeholder="بحث برقم الطلب، الفرع أو الوصف..." aria-label="بحث في الطلبات"><button id="refresh" type="button">تحديث</button></div>
 </div>
 <section id="cards" class="cards" aria-live="polite"></section>
 <p class="muted">تعرض الشاشة إشعارات الطلبات غير المقروءة فقط. فتح الطلب يحدد إشعاره كمقروء، ولا يغيّر حالة إنتاج الطلب.</p>
</main>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const endpoint=@json(route('notifications.pending-order-alerts'));
 const readTemplate=@json(route('notifications.read',['id'=>'__NOTIFICATION_ID__']));
 const csrf=document.querySelector('meta[name="csrf-token"]').content;
 const cards=document.getElementById('cards'),status=document.getElementById('alertStatus'),search=document.getElementById('search');
 const soundButton=document.getElementById('soundButton');
 const installButton=document.getElementById('installButton');
 let installPrompt=null;
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;installButton.textContent='تثبيت التطبيق';});
 window.addEventListener('appinstalled',()=>{installPrompt=null;installButton.textContent='التطبيق مثبت';});
 installButton.addEventListener('click',async()=>{
  if(!installPrompt){alert('لتثبيت طلبات دهب، افتح قائمة Chrome أو Edge واختر «تثبيت التطبيق» أو «Install app». إذا كان مثبتًا بالفعل، افتحه من قائمة التطبيقات.');return;}
  installPrompt.prompt();
  await installPrompt.userChoice;
  installPrompt=null;installButton.textContent='تثبيت التطبيق';
 });
 let items=[],filter='all',busy=false,audio=null,soundOn=false,initialized=false;
 const seenKey='dahab:orders-pwa:seen:{{ auth()->id() }}';
 let seen=new Set();
 try{seen=new Set(JSON.parse(localStorage.getItem(seenKey)||'[]'));}catch(_){seen=new Set();}
 function remember(ids){ids.forEach(id=>seen.add(id));if(seen.size>500)seen=new Set([...seen].slice(-500));try{localStorage.setItem(seenKey,JSON.stringify([...seen]));}catch(_){}}
 const classify=n=>{
  const path=String(n.url||'');
  if(path.includes('showroom-sweets-requests'))return 'branch-sweets';
  if(path.includes('showroom-cake-requests'))return 'branch-cake';
  return 'special';
 };
 const matches=(n,key)=>key==='all'||(key==='cake'&&classify(n)!=='branch-sweets')||(key==='sweets'&&classify(n)==='branch-sweets')||classify(n)===key;
 const categoryLabel=n=>({'special':'كيك خاص','branch-cake':'كيك فروع','branch-sweets':'حلويات فروع'})[classify(n)];
 function draw(){
  const q=search.value.trim().toLocaleLowerCase();
  const visible=items.filter(n=>matches(n,filter)&&[n.title,n.message].join(' ').toLocaleLowerCase().includes(q));
  cards.replaceChildren();
  document.querySelectorAll('[data-count]').forEach(el=>{
    el.textContent=items.filter(n=>matches(n,el.dataset.count)).length;
  });
  if(!visible.length){const empty=document.createElement('div');empty.className='empty muted';empty.textContent='لا توجد طلبات تطابق البحث حاليًا';cards.append(empty);return;}
  visible.forEach(n=>{
   const article=document.createElement('article');article.className='card';
   const top=document.createElement('div');top.className='actions';top.style.justifyContent='space-between';
   const title=document.createElement('h3');title.textContent=n.title||'طلب جديد';
   const tag=document.createElement('span');tag.className='tag';tag.textContent=categoryLabel(n);
   const body=document.createElement('p');body.textContent=n.message||'';
   const time=document.createElement('small');time.className='muted';time.textContent=n.time||'';
   const link=document.createElement('a');link.className='button gold';link.textContent='فتح الطلب ومراجعته';link.href=n.url||'#';
   link.addEventListener('click',async e=>{
    e.preventDefault();
    if(!n.url)return;
    try{await fetch(readTemplate.replace('__NOTIFICATION_ID__',encodeURIComponent(n.id)),{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});}catch(err){console.debug(err)}
    location.assign(n.url);
   });
   top.append(title,tag);article.append(top,body,time,link);cards.append(article);
  });
 }
 // A short, soft two-note chime played once per newly received notification.
 function beep(){
  if(!soundOn||!audio||document.hidden)return;
  const start=audio.currentTime;
  [660,880].forEach((frequency,index)=>{
   const t=start+index*.19;
   const oscillator=audio.createOscillator(),gain=audio.createGain();
   oscillator.type='sine';
   oscillator.frequency.setValueAtTime(frequency,t);
   gain.gain.setValueAtTime(0,t);
   gain.gain.linearRampToValueAtTime(.11,t+.035);
   gain.gain.exponentialRampToValueAtTime(.0001,t+.31);
   oscillator.connect(gain);gain.connect(audio.destination);
   oscillator.start(t);oscillator.stop(t+.32);
  });
 }
 soundButton.addEventListener('click',async()=>{
  if(soundOn){soundOn=false;soundButton.textContent='تفعيل صوت التنبيه';return;}
  const AudioClass=window.AudioContext||window.webkitAudioContext;
  if(!AudioClass){status.textContent='متصفحك لا يدعم صوت التنبيهات';return;}
  audio??=new AudioClass();await audio.resume();soundOn=audio.state==='running';
  soundButton.textContent=soundOn?'إيقاف صوت التنبيه':'تفعيل صوت التنبيه';
  // Enabling sound never replays previously received orders.
 });
 async function poll(){
  if(busy||document.hidden)return;busy=true;
  try{
   const response=await fetch(endpoint,{cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.ok)throw new Error('تعذر تحميل التنبيهات');
   const json=await response.json();
   items=Array.isArray(json.items)?json.items:[];
   const ids=items.map(x=>x.id);
   // Establish a silent baseline on first load. Only later, genuinely new
   // notification IDs can trigger the single chime.
   const newIds=initialized?ids.filter(id=>!seen.has(id)):[];
   remember(ids);
   initialized=true;
   draw();status.textContent='متصل — آخر تحديث '+new Date().toLocaleTimeString('ar');
   if(newIds.length)beep();
  }catch(e){status.textContent='انقطع الاتصال — تتم إعادة المحاولة';status.className='danger'}
  finally{busy=false;}
 }
 document.getElementById('refresh').addEventListener('click',poll);
 search.addEventListener('input',draw);
 document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{
  filter=b.dataset.filter;document.querySelectorAll('[data-filter]').forEach(x=>x.setAttribute('aria-selected',String(x===b)));draw();
 }));
 setInterval(poll,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});poll();
 if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});
});
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#101c2d">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ route('orders-app.manifest') }}">
<title>طلبات دهب</title>
<style>
:root{font-family:Tahoma,Arial,sans-serif;color:#132238;background:#f4f6f9;font-synthesis:none}
*{box-sizing:border-box}body{margin:0}button,input{font:inherit}button{cursor:pointer}
header{background:#101c2d;color:#fff;padding:20px max(18px,calc((100vw - 1100px)/2));display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
header h1{font-size:24px;margin:0}header small{color:#e0b65b}main{max-width:1100px;margin:auto;padding:22px 16px}
h2{font-size:20px;margin:0 0 8px}p{line-height:1.7}.muted{color:#617085}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
button,.button{border:1px solid #ccd2dc;border-radius:11px;padding:10px 15px;background:white;color:#172133;text-decoration:none;font-weight:700}
.gold{background:#eea51b;color:#172133;border-color:#eea51b}.dark{background:#26354c;color:#fff;border-color:#26354c}
.panel{background:white;border:1px solid #e1e4eb;border-radius:15px;padding:18px;margin:15px 0}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#fff;border:1px solid #e1e4eb;border-radius:12px;padding:18px}
.stat strong{font-size:30px;display:block;margin-top:5px}.stat small{color:#66758b}
.filters{display:flex;gap:10px;flex-wrap:wrap}.filters button[aria-pressed="true"]{background:#101c2d;color:white}
.search{padding:12px;border:1px solid #cbd2df;border-radius:10px;min-width:220px;flex:1}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.card{border:1px solid #e0e4ed;background:white;border-radius:15px;padding:17px;display:flex;flex-direction:column;gap:10px}
.card h3{font-size:16px;margin:0}.card p{margin:0;font-size:14px;overflow-wrap:anywhere}
.card .button{display:block;text-align:center;margin-top:auto}.tag{background:#fff4d8;color:#825500;border-radius:50px;padding:5px 9px;font-size:12px}
#alertStatus{font-size:13px}.danger{color:#b32020}.empty{padding:35px;text-align:center;background:white;border-radius:14px}
@media(max-width:580px){.stats{gap:8px}.stat{padding:10px}.stat strong{font-size:25px}header{padding:16px}header h1{font-size:20px}}
</style>
</head>
<body>
<header>
 <div><small>حلويات دهب</small><h1>طلبات دهب</h1></div>
 <div class="actions">
  <button id="installButton" type="button" class="gold" hidden>تثبيت طلبات دهب</button>
  <button id="soundButton" type="button" class="dark">تفعيل صوت التنبيه</button>
  <a href="{{ route('dashboard') }}" class="button">العودة للنظام</a>
 </div>
</header>
<main>
 <div class="actions" style="justify-content:space-between"><div><h2>مركز متابعة الطلبات</h2><p class="muted" style="margin:0">الطلبات الواردة لحسابك حسب صلاحياتك وإشعاراتك الحالية</p></div><span id="alertStatus" role="status">جاري الاتصال...</span></div>
 <section class="stats" aria-label="ملخص الطلبات">
  <div class="stat"><small>بانتظار المراجعة</small><strong id="newCount">0</strong></div>
  <div class="stat"><small>تنبيهات الكيك</small><strong id="cakeCount">0</strong></div>
  <div class="stat"><small>تنبيهات الحلويات</small><strong id="sweetsCount">0</strong></div>
 </section>
 <div class="panel">
  <div class="actions"><input id="search" class="search" type="search" placeholder="ابحث برقم الطلب أو النص" aria-label="بحث في الطلبات"><button id="refresh" type="button">تحديث</button></div>
  <div class="filters" style="margin-top:14px" aria-label="نوع الطلب">
   <button type="button" data-filter="all" aria-pressed="true">الكل</button>
   <button type="button" data-filter="cake" aria-pressed="false">الكيك</button>
   <button type="button" data-filter="sweets" aria-pressed="false">الحلويات</button>
  </div>
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
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;installButton.hidden=false;});
 window.addEventListener('appinstalled',()=>{installPrompt=null;installButton.hidden=true;});
 installButton.addEventListener('click',async()=>{
  if(!installPrompt)return;
  installPrompt.prompt();
  await installPrompt.userChoice;
  installPrompt=null;installButton.hidden=true;
 });
 let items=[],filter='all',busy=false,audio=null,soundOn=false,seen=new Set(),lastRing=0;
 const classify=n=>String(n.url||'').includes('showroom-sweets-requests')?'sweets':'cake';
 function draw(){
  const q=search.value.trim().toLocaleLowerCase();
  const visible=items.filter(n=>(filter==='all'||classify(n)===filter)&&[n.title,n.message].join(' ').toLocaleLowerCase().includes(q));
  cards.replaceChildren();
  document.getElementById('newCount').textContent=items.length;
  document.getElementById('cakeCount').textContent=items.filter(n=>classify(n)==='cake').length;
  document.getElementById('sweetsCount').textContent=items.filter(n=>classify(n)==='sweets').length;
  if(!visible.length){const empty=document.createElement('div');empty.className='empty muted';empty.textContent='لا توجد طلبات تطابق البحث حاليًا';cards.append(empty);return;}
  visible.forEach(n=>{
   const article=document.createElement('article');article.className='card';
   const top=document.createElement('div');top.className='actions';top.style.justifyContent='space-between';
   const title=document.createElement('h3');title.textContent=n.title||'طلب جديد';
   const tag=document.createElement('span');tag.className='tag';tag.textContent=classify(n)==='sweets'?'حلويات':'كيك';
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
 function beep(){
  if(!soundOn||!audio||document.hidden||!items.length)return;
  if(Date.now()-lastRing<2600)return;
  lastRing=Date.now();
  const start=audio.currentTime;
  [580,820,580].forEach((f,i)=>{
   const o=audio.createOscillator(),g=audio.createGain(),t=start+i*.35;
   o.type='sine';o.frequency.setValueAtTime(f,t);o.frequency.linearRampToValueAtTime(f*1.12,t+.3);
   g.gain.setValueAtTime(0,t);g.gain.linearRampToValueAtTime(.13,t+.08);g.gain.linearRampToValueAtTime(0,t+.32);
   o.connect(g);g.connect(audio.destination);o.start(t);o.stop(t+.34);
  });
 }
 soundButton.addEventListener('click',async()=>{
  if(soundOn){soundOn=false;soundButton.textContent='تفعيل صوت التنبيه';return;}
  const AudioClass=window.AudioContext||window.webkitAudioContext;
  if(!AudioClass){status.textContent='متصفحك لا يدعم صوت التنبيهات';return;}
  audio??=new AudioClass();await audio.resume();soundOn=audio.state==='running';
  soundButton.textContent=soundOn?'إيقاف صوت التنبيه':'تفعيل صوت التنبيه';
  if(soundOn)beep();
 });
 async function poll(){
  if(busy||document.hidden)return;busy=true;
  try{
   const response=await fetch(endpoint,{cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.ok)throw new Error('تعذر تحميل التنبيهات');
   const json=await response.json();
   items=Array.isArray(json.items)?json.items:[];
   const ids=new Set(items.map(x=>x.id));
   if([...ids].some(id=>!seen.has(id)))lastRing=0;
   seen=ids;draw();status.textContent='متصل — آخر تحديث '+new Date().toLocaleTimeString('ar');
   if(items.length)beep();
  }catch(e){status.textContent='انقطع الاتصال — تتم إعادة المحاولة';status.className='danger'}
  finally{busy=false;}
 }
 document.getElementById('refresh').addEventListener('click',poll);
 search.addEventListener('input',draw);
 document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{
  filter=b.dataset.filter;document.querySelectorAll('[data-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));draw();
 }));
 setInterval(poll,5000);setInterval(beep,2800);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});poll();
 if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});
});
</script>
</body>
</html>

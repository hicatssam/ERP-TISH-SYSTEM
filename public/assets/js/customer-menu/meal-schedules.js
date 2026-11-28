(() => {
'use strict';
const config=window.TishMealSchedule, root=document.getElementById('tishMealSchedule');
if(!config||!root)return;
let status=config.initial, start=performance.now(), refreshing=false;
const en=config.en, label=(ar,enText)=>en?enText:ar;
const now=()=>status.server_ms+performance.now()-start;
function format(ms){
    const s=Math.max(0,Math.ceil(ms/1000));
    return [Math.floor(s/3600),Math.floor(s%3600/60),s%60].map(x=>String(x).padStart(2,'0')).join(':');
}
function render(){
    let any=false, boundary=false;
    root.querySelectorAll('[data-meal]').forEach(card=>{
        const w=status.windows[card.dataset.meal];
        if(!w)return;
        card.hidden=!w.enabled||!w.count;
        if(card.hidden)return;
        any=true;
        const t=now();
        const state=t<w.starts_ms?'upcoming':t<w.ends_ms?'open':'ended';
        card.dataset.state=state;
        card.querySelector('.meal-hours').textContent=w.start+' – '+w.end;
        const message=card.querySelector('.meal-message'),clock=card.querySelector('.meal-clock'),cta=card.querySelector('.meal-cta');
        if(state==='open'){
            message.textContent=(w.ends_ms-t<=30*60000)?label('آخر فرصة قبل انتهاء الموعد','Last chance before closing'):label('متاح الآن • ينتهي خلال','Available now • ends in');
            clock.textContent=format(w.ends_ms-t);
            cta.textContent=label('اطلب قبل انتهاء الوقت','Order before closing');
        }else if(state==='upcoming'){
            message.textContent=label('يبدأ بعد','Starts in');
            clock.textContent=format(w.starts_ms-t);
            cta.textContent=label('قريبًا','Coming soon');
        }else{
            message.textContent=label('انتهى موعد اليوم','Today’s window has ended');
            clock.textContent=label('غدًا '+w.start,'Tomorrow '+w.start);
            cta.textContent=label('نشوفك بكرة!','See you tomorrow!');
        }
        if(state!==w.state)boundary=true;
    });
    root.hidden=!any;
    if(boundary && !refreshing) refresh();
}
let lastFetch=0;
async function refresh(){
    if(refreshing||performance.now()-lastFetch<2500 && lastFetch!==0)return;
    refreshing=true;lastFetch=performance.now();
    try{
        const response=await fetch(config.url,{cache:'no-store',headers:{Accept:'application/json'}});
        if(!response.ok)return;
        const next=await response.json();
        if(!next.windows||!next.revision)return;
        const changed=next.revision!==status.revision;
        status=next;start=performance.now();
        render();
        if(changed){
            // Reload catalogue once for new product eligibility; local cart is preserved.
            const key='tish.meal.refresh.'+location.pathname+location.search;
            let last='';try{last=sessionStorage.getItem(key)||'';}catch(_){}
            if(last!==next.revision){try{sessionStorage.setItem(key,next.revision);}catch(_){} location.reload();}
        }
    }catch(_){ /* Offline countdown still reflects the last trusted server timestamp. */ }
    finally{refreshing=false;}
}
render();setInterval(render,1000);setInterval(refresh,30000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();

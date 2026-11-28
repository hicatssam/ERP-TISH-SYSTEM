(() => {
'use strict';
const config=window.TishMealSchedule, root=document.getElementById('tishMealSchedule');
if(!config||!root)return;
let status=config.initial, start=performance.now(), refreshing=false, lastFetch=0;
const en=config.en, label=(ar,enText)=>en?enText:ar;
const now=()=>Number(status.server_ms)+performance.now()-start;
const cards=[...root.querySelectorAll('[data-meal]')];
const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)');
const text=(el,value)=>{if(el && el.textContent!==value)el.textContent=value;};
// Presentation only. All availability comparisons still use server milliseconds.
function time12(value){
    const parts=/^([01]\d|2[0-3]):([0-5]\d)$/.exec(String(value));
    if(!parts)return String(value);
    const hour=Number(parts[1]), minute=Number(parts[2]);
    return String(hour%12||12)+(minute?':'+parts[2]:'')+' '+(hour>=12?label('م','PM'):label('ص','AM'));
}
function range12(start,end){
    return en?time12(start)+' – '+time12(end):'من '+time12(start)+' إلى '+time12(end);
}
function stateOf(w,t){
    if(!w.enabled||!w.count)return 'disabled';
    return t<w.starts_ms?'upcoming':t<w.ends_ms?'open':'ended';
}
function setClickable(card,enabled){
    if(enabled){
        if(card.getAttribute('href')!==card.dataset.mealUrl)card.setAttribute('href',card.dataset.mealUrl);
        card.removeAttribute('aria-disabled');card.removeAttribute('tabindex');card.removeAttribute('role');
    }else{
        card.removeAttribute('href');card.setAttribute('role','link');
        card.setAttribute('aria-disabled','true');card.setAttribute('tabindex','-1');
    }
}
function updateDigits(card,ms){
    const s=Math.max(0,Math.ceil(ms/1000));
    const digits=[Math.floor(s/3600),Math.floor(s%3600/60),s%60].map(x=>String(x).padStart(2,'0'));
    ['hours','minutes','seconds'].forEach((part,index)=>{
        const el=card.querySelector('[data-part="'+part+'"]');
        if(!el||el.textContent===digits[index])return;
        el.textContent=digits[index];
        if(!reduced?.matches && typeof el.animate==='function'){
            el.getAnimations?.().forEach(animation=>animation.cancel());
            el.animate([{transform:'translateY(3px)',opacity:.55},{transform:'translateY(0)',opacity:1}],{duration:230,easing:'ease-out'});
        }
    });
}
function render(){
    let any=false,boundary=false;
    for(const card of cards){
        const w=status.windows[card.dataset.meal];
        if(!w)continue;
        const t=now(), state=stateOf(w,t);
        card.hidden=state==='disabled';
        if(card.hidden){setClickable(card,false);continue;}
        any=true;
        if(card.dataset.state!==state)card.dataset.state=state;
        setClickable(card,state==='open');
        text(card.querySelector('.meal-hours'),range12(w.start,w.end));
        const message=card.querySelector('.meal-message'),clock=card.querySelector('.meal-clock'),cta=card.querySelector('.meal-cta');
        const next=card.querySelector('.meal-next'),progress=card.querySelector('.meal-progress');
        clock.hidden=state==='ended';
        next.hidden=state!=='ended';
        progress.hidden=state!=='open';
        if(state==='open'){
            const remaining=w.ends_ms-t;
            text(message,remaining<=30*60000?label('آخر فرصة للطلب · ينتهي بعد','Last chance · ends in'):label('متاح الآن · ينتهي بعد','Available now · ends in'));
            updateDigits(card,remaining);
            const pct=Math.max(0,Math.min(100,100*remaining/Math.max(1,w.ends_ms-w.starts_ms)));
            progress.firstElementChild.style.width=pct+'%';
            text(cta,label('اطلب الآن','Order now'));
        }else if(state==='upcoming'){
            text(message,label('يبدأ بعد','Starts in'));updateDigits(card,w.starts_ms-t);
            text(cta,label('قريبًا','Coming soon'));
        }else{
            text(message,label('انتهى موعد اليوم','Closed for today'));
            text(card.querySelector('.meal-next-time'),time12(w.start));
            text(cta,label('نلقاك غدًا','See you tomorrow'));
        }
        if(state!==w.state || (state==='ended' && t>=w.next_start_ms))boundary=true;
    }
    root.hidden=!any;
    if(boundary&&!refreshing)refresh();
}
// Guard a click even if the scheduled render has not yet run at the cutoff.
root.addEventListener('click',event=>{
    const card=event.target.closest('[data-meal]');
    if(!card||!root.contains(card))return;
    const w=status.windows[card.dataset.meal];
    if(!w||stateOf(w,now())!=='open'){event.preventDefault();event.stopImmediatePropagation();render();}
},true);
async function refresh(){
    if(refreshing||(lastFetch!==0&&performance.now()-lastFetch<2500))return;
    refreshing=true;lastFetch=performance.now();
    try{
        const response=await fetch(config.url,{cache:'no-store',headers:{Accept:'application/json'}});
        if(!response.ok)return;
        const next=await response.json();
        if(!next.windows||!next.revision||!Number.isFinite(Number(next.server_ms)))return;
        const changed=next.revision!==status.revision;
        status=next;start=performance.now();
        render();
        if(changed){
            // Rebuild catalogue eligibility from the server; keep the local cart.
            const key='tish.meal.refresh.'+location.pathname+location.search;
            let last='';try{last=sessionStorage.getItem(key)||'';}catch(_){}
            if(last!==next.revision){try{sessionStorage.setItem(key,next.revision);}catch(_){} location.reload();}
        }
    }catch(_){ /* Continue the last server-synchronized countdown when offline. */ }
    finally{refreshing=false;}
}
render();setInterval(render,1000);setInterval(refresh,30000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden){render();refresh();}});
})();

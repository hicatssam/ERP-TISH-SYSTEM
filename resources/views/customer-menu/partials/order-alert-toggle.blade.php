{{-- Shared device opt-in for future orders. Tracking keeps its order-specific control. --}}
<style>
html body .tish-future-alert{
 appearance:none!important;position:relative!important;isolation:isolate;display:inline-grid!important;place-items:center!important;
 flex:0 0 44px!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;
 padding:0!important;margin:0!important;border:0!important;border-radius:50%!important;
 background:none!important;box-shadow:none!important;color:#ad2117!important;cursor:pointer;
 filter:drop-shadow(0 3px 2px #5436224a);
}
html body .tish-future-alert::before{
 content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
 background:url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat;
}
html body .tish-future-alert[aria-pressed="false"]{color:#8b3026!important}
html body .tish-future-alert svg{display:block;width:23px;height:23px;fill:currentColor;flex-shrink:0}
html body .tish-future-alert[aria-pressed="true"] .tish-alert-slash{display:none}
html body .tish-future-alert:disabled{opacity:.65;cursor:wait}
html body .tish-future-alert:focus-visible{outline:2px solid #a92117!important;outline-offset:2px!important}
html body .tish-future-alert:active{scale:.94}
html body .tish-future-alert-hint{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
html body .tish-future-alert-error{
 position:fixed;z-index:500;top:calc(12px + env(safe-area-inset-top,0px));left:50%;transform:translateX(-50%);
 width:max-content;max-width:calc(100% - 28px);padding:10px 15px;border-radius:9px;background:#fff3de;color:#8e2119;
 box-shadow:0 3px 12px #45271d40;font-size:13px;font-weight:700;text-align:center;pointer-events:none;
}
html body.tish-paper .cm-unified-header.tish-alert-header{
 grid-template-columns:minmax(0,1fr) 110px!important;
 column-gap:6px!important;
}
html body.tish-paper .cm-unified-header-actions.tish-alert-host{
 width:110px!important;min-width:110px!important;
 grid-template-columns:44px minmax(0,1fr)!important;grid-template-rows:35px 44px!important;
 gap:4px!important;justify-items:center!important;
}
html body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-language-switch{grid-column:1 / 3!important;grid-row:1!important;justify-self:end!important}
html body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-unified-header-back{grid-column:2!important;grid-row:2!important;margin:0!important;justify-self:end!important}
html body.tish-paper .cm-unified-header-actions.tish-alert-host .tish-future-alert{grid-column:1;grid-row:2;justify-self:center}
html body .branch-brand.tish-alert-host{flex-wrap:wrap;gap:7px}
html body .branch-brand.tish-alert-host>span{flex:1;min-width:0;overflow-wrap:anywhere}
html body .tish-drive-hero-actions.tish-alert-host{gap:4px!important}
html body .tish-drive-hero-actions.tish-alert-host .tish-future-alert{order:2}
html body .tish-drive-hero-actions.tish-alert-host .cm-language-switch{order:3}
html body .tish-invoice-toolbar .tish-future-alert{color:#ad2117!important}
@media(max-width:365px){
 html body.tish-paper .cm-unified-header.tish-alert-header{grid-template-columns:minmax(0,1fr) 96px!important}
 html body.tish-paper .cm-unified-header-actions.tish-alert-host{width:96px!important;min-width:96px!important;gap:3px!important}
 html body .tish-drive-hero-actions.tish-alert-host{gap:2px!important}
}
@media(prefers-reduced-motion:reduce){html body .tish-future-alert:active{scale:1}}
@media print{html body .tish-future-alert,html body .tish-future-alert-hint,html body .tish-future-alert-error{display:none!important}}

/* Compact unified top bar: brand, language, bell and back in one row. */
html[dir] body.tish-paper .cm-unified-header.tish-alert-header{
 direction:inherit!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:space-between!important;
 min-height:80px!important;padding:12px 0 8px!important;gap:7px!important;
}
html[dir] body.tish-paper .cm-unified-header.tish-alert-header .cm-unified-header-brand{
 direction:inherit!important;flex:1 1 auto!important;min-width:0!important;gap:7px!important;
}
html[dir] body.tish-paper .cm-unified-header.tish-alert-header .cm-unified-header-brand-copy{text-align:start!important}
html[dir] body.tish-paper .cm-unified-header.tish-alert-header .cm-unified-header-logo{
 width:clamp(43px,13vw,60px)!important;height:clamp(43px,13vw,60px)!important;flex:0 0 clamp(43px,13vw,60px)!important;
}
html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host{
 direction:inherit!important;display:flex!important;flex:0 0 auto!important;flex-direction:row!important;align-items:center!important;
 justify-content:flex-end!important;gap:5px!important;width:auto!important;min-width:0!important;
}
html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-language-switch{
 order:0!important;position:relative!important;inset:auto!important;min-width:60px!important;max-width:none!important;
 height:38px!important;min-height:38px!important;padding:5px 8px!important;font-size:12px!important;
}
html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host .tish-future-alert{order:1!important;flex:0 0 40px!important;width:40px!important;height:40px!important;min-width:40px!important;min-height:40px!important}
html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-unified-header-back{
 order:2!important;position:relative!important;inset:auto!important;flex:0 0 40px!important;width:40px!important;height:40px!important;min-width:40px!important;min-height:40px!important;
 padding:0!important;margin:0!important;background:transparent url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
}
html[dir="rtl"] body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-unified-header-back i{transform:rotate(180deg)!important}
html[dir="ltr"] body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-unified-header-back i{transform:none!important}
@media(max-width:365px){
 html[dir] body.tish-paper .cm-unified-header.tish-alert-header{gap:4px!important}
 html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host{gap:3px!important}
 html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host .cm-language-switch{min-width:54px!important;padding-inline:5px!important;font-size:11px!important}
}
@media print{html[dir] body.tish-paper .cm-unified-header-actions.tish-alert-host{display:none!important}}

</style>
<link rel="stylesheet" href="/assets/css/customer-menu/tish-paper-disc-reference-v1.css?v=1">
<script>
(function () {
    'use strict';
    if (window.TishFutureOrderAlerts) return;
    window.TishFutureOrderAlerts = true;
    function mount() {
        if (document.body.id === 'tishTrack' || document.querySelector('#enableOrderAlerts')) return;
        const host = document.querySelector('.cart-reference-actions,.cm-unified-header-actions,.tish-drive-hero-actions,.branch-brand,.tish-invoice-toolbar');
        if (!host) return;
        // Replace only the old cart toggle, not the tracking page's own control.
        const previous = host.querySelector('#cartOrderAlerts');
        if (previous) previous.remove();
        host.querySelector('#cartOrderAlertsHint')?.remove();
        host.classList.add('tish-alert-host');
        host.closest('.cm-unified-header')?.classList.add('tish-alert-header');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'tish-future-alert';
        button.setAttribute('aria-pressed','false');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5a1.5 1.5 0 0 1 1.5 1.5v.3c3 .7 4.8 3 4.8 6.2v3.8l2 2.5c.6.8 0 1.8-.9 1.8H4.6c-.9 0-1.5-1-.9-1.8l2-2.5v-3.8c0-3.2 1.8-5.5 4.8-6.2V4A1.5 1.5 0 0 1 12 2.5ZM9.3 20h5.4a2.8 2.8 0 0 1-5.4 0Z"/><g class="tish-alert-slash"><path d="M3 3 21 21" fill="none" stroke="#f6e7d0" stroke-width="4" stroke-linecap="round"/><path d="M3 3 21 21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></g></svg>';
        const hint = document.createElement('span');
        hint.className='tish-future-alert-hint';hint.setAttribute('role','status');hint.setAttribute('aria-live','polite');
        const language = host.querySelector('.cm-language-switch,[data-cm-language-host]');
        host.insertBefore(button,language || null);
        host.appendChild(hint);
        const key = 'tish-future-order-push-v1';
        const t = (ar,en) => document.documentElement.lang === 'en' ? en : ar;
        const supported = () => window.isSecureContext && 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
        let enabled = false, busy = false, epoch = 0, errorTimer;
        function preferred(){try{return localStorage.getItem(key)==='yes';}catch(_){return false;}}
        function render(message=''){
            const label=enabled?t('إيقاف إشعارات الطلبات الجديدة','Turn off notifications for new orders'):t('تفعيل إشعارات الطلبات الجديدة','Turn on notifications for new orders');
            button.setAttribute('aria-pressed',String(enabled));button.setAttribute('aria-label',label);
            button.setAttribute('aria-busy',String(busy));button.disabled=busy;
            button.title=message||label;
            hint.textContent=message||(enabled?t('سيتم ربط الإشعارات بالطلب عند إرساله.','Notifications will be attached when a new order is placed.'):label);
        }
        function showError(message){
            let toast=document.querySelector('.tish-future-alert-error');
            if(!toast){toast=document.createElement('div');toast.className='tish-future-alert-error';document.body.appendChild(toast);}
            toast.textContent=message;clearTimeout(errorTimer);errorTimer=setTimeout(()=>toast.remove(),4500);
        }
        async function sync(){
            if(busy)return;
            const current=++epoch;
            let state=false;
            if(preferred()&&supported()&&Notification.permission==='granted'){
                try{const reg=await navigator.serviceWorker.getRegistration('/');state=!!(await reg?.pushManager?.getSubscription());}catch(_){}
            }
            if(current!==epoch||busy)return;
            enabled=state&&preferred();render();
        }
        async function timed(promise){
            let timeout;
            try{return await Promise.race([promise,new Promise((_,reject)=>{timeout=setTimeout(()=>reject(new Error(t('تعذر تجهيز الإشعارات. حاول مرة أخرى.','Notification setup timed out. Try again.'))),8000);})]);}
            finally{clearTimeout(timeout);}
        }
        button.addEventListener('click',async()=>{
            if(busy)return;
            ++epoch;busy=true;render();
            let message='';
            try{
                if(enabled){localStorage.removeItem(key);enabled=false;}
                else{
                    if(!supported())throw new Error(t('الإشعارات غير مدعومة هنا. على iPhone ثبّت المنيو على الشاشة الرئيسية أولاً.','Notifications are unavailable here. On iPhone install the menu on your Home Screen first.'));
                    const permission=Notification.permission==='granted'?'granted':await Notification.requestPermission();
                    if(permission!=='granted')throw new Error(t('لم يتم منح إذن الإشعارات.','Notification permission was not granted.'));
                    const response=await timed(fetch(@json(route('customer-menu.push.public-key')),{cache:'no-store',credentials:'same-origin'}));
                    if(!response.ok)throw new Error(t('تعذر قراءة إعدادات الإشعارات.','Could not load notification settings.'));
                    const config=await response.json();
                    if(!config.enabled||!config.public_key)throw new Error(t('خدمة الإشعارات غير متاحة.','Notification service unavailable.'));
                    await timed(navigator.serviceWorker.register('/sw.js',{scope:'/'}));
                    const registration=await timed(navigator.serviceWorker.ready);
                    const raw=config.public_key;
                    const bytes=Uint8Array.from(atob(raw.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-raw.length%4)%4)),c=>c.charCodeAt(0));
                    const subscription=await timed(registration.pushManager.getSubscription())||await timed(registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:bytes}));
                    if(!subscription)throw new Error(t('تعذر تجهيز اشتراك الجهاز.','Could not prepare device subscription.'));
                    localStorage.setItem(key,'yes');enabled=true;
                }
            }catch(error){message=String(error.message||error);showError(message);console.warn('TISH future order alerts:',error);}
            finally{busy=false;render(message);}
        });
        window.addEventListener('pageshow',sync);
        window.addEventListener('storage',event=>{if(event.key===key)sync();});
        document.addEventListener('visibilitychange',()=>{if(!document.hidden)sync();});
        render();sync();
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});
    else mount();
})();
</script>

(() => {
'use strict';
const initial = window.TishCustomerScreenConfig;
const token = location.hash.slice(1);
const scope = initial.scope;
const $ = id => document.getElementById(id);
const screen = $('screen'), bill = $('bill'), slot = $('mediaSlot'), thanks = $('thanks');
let config = initial.settings, index = 0, mediaTimer, mediaGeneration = 0, sound = false;
let lastSeen = 0, thanksUntil = 0, lastOrder = '', lastSnapshot = '', phase = 'idle';
const money = n => '₪' + (Number.isFinite(Number(n)) ? Number(n) : 0).toFixed(2);
function applySettings() { screen.style.setProperty('--bill', (100 - config.ad_percent) + '%'); }
function playMedia() {
    const generation = ++mediaGeneration;
    clearTimeout(mediaTimer);
    slot.replaceChildren();
    const list = config.media || [];
    $('fallback').hidden = !!list.length;
    if (!list.length) return;
    const entry = list[index % list.length];
    const next = () => { if (generation !== mediaGeneration) return; index = (index + 1) % list.length; playMedia(); };
    const retry = () => { if (generation === mediaGeneration) { $('fallback').hidden = false; slot.replaceChildren(); clearTimeout(mediaTimer); mediaTimer = setTimeout(next, 3000); } };
    const media = document.createElement(entry.type === 'video' ? 'video' : 'img');
    const url = new URL(entry.url, location.origin);
    if (url.origin !== location.origin) { retry(); return; }
    media.src = url.href;
    media.addEventListener('error', retry, {once:true});
    if (entry.type === 'video') {
        media.autoplay = true; media.muted = !sound; media.playsInline = true; media.preload = 'auto';
        media.addEventListener('ended', next, {once:true});
        media.addEventListener('loadedmetadata', () => {
            if (generation !== mediaGeneration) return;
            clearTimeout(mediaTimer);
            mediaTimer = setTimeout(next, Math.max(10000, Math.min(3600000, (media.duration + 10) * 1000 || 120000)));
        }, {once:true});
        slot.append(media);
        media.play().catch(() => { media.muted = true; media.play().catch(retry); });
        mediaTimer = setTimeout(next, 120000);
    } else {
        media.alt = ''; slot.append(media);
        mediaTimer = setTimeout(next, config.image_seconds * 1000);
    }
}
function idle() {
    phase = 'idle'; screen.classList.remove('active'); bill.hidden = true; thanks.hidden = true;
    $('items').replaceChildren(); lastSnapshot = '';
}
function render(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') return;
    if (snapshot.phase === 'confirmed') {
        const order = String(snapshot.orderNumber || '').slice(0, 80);
        if (!order || order === lastOrder) return;
        lastOrder = order; thanksUntil = Date.now() + config.thanks_seconds * 1000;
        idle(); thanks.hidden = false; $('orderNumber').textContent = '#' + order;
        return;
    }
    if (!['order','checkout','idle'].includes(snapshot.phase)) return;
    if (snapshot.phase === 'idle') {
        if (Date.now() < thanksUntil) return;
        idle(); return;
    }
    thanksUntil = 0; thanks.hidden = true;
    const signature = JSON.stringify(snapshot);
    if (lastSnapshot === signature) return;
    lastSnapshot = signature; phase = snapshot.phase;
    screen.classList.add('active'); bill.hidden = false;
    $('billTitle').textContent = phase === 'checkout' ? 'تفاصيل الدفع' : 'طلبك';
    const fragment = document.createDocumentFragment();
    for (const row of (Array.isArray(snapshot.items) ? snapshot.items : []).slice(0,100)) {
        const line = document.createElement('div'); line.className = 'line';
        const info = document.createElement('div'), title = document.createElement('strong'), detail = document.createElement('small');
        title.textContent = String(row.name || '').slice(0,180);
        detail.textContent = Number(row.quantity) + ' × ' + money(row.price)
            + (Array.isArray(row.modifiers) && row.modifiers.length ? ' · ' + row.modifiers.join('، ') : '');
        info.append(title,detail);
        const amount = document.createElement('span'); amount.className = 'amount'; amount.textContent = money(Number(row.quantity)*Number(row.price));
        line.append(info,amount); fragment.append(line);
    }
    $('items').replaceChildren(fragment);
    $('subtotal').textContent = money(snapshot.subtotal);
    $('discount').textContent = money(snapshot.discount);
    $('discountRow').hidden = !(Number(snapshot.discount)>0);
    $('total').textContent = money(snapshot.total);
}
window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== window.opener || !token
        || event.data?.type !== 'tish.pos.display.snapshot' || event.data.token !== token || event.data.scope !== scope) return;
    lastSeen = Date.now(); $('connection').hidden = true; render(event.data.snapshot);
});
function hello() {
    try { if (window.opener && !window.opener.closed && token) window.opener.postMessage({type:'tish.pos.display.hello', token, scope},location.origin); } catch (_) {}
}
$('fullscreen').addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen().catch(()=>{});
    else document.documentElement.requestFullscreen?.().catch(()=>{});
});
$('sound').addEventListener('click', () => {
    sound = !sound; $('sound').setAttribute('aria-pressed', String(sound)); $('sound').textContent = sound ? 'الصوت مفعّل' : 'الصوت مكتوم';
    const video = slot.querySelector('video'); if (video) { video.muted = !sound; video.play().catch(()=>{ video.muted = true; }); }
});
setInterval(() => {
    hello();
    if (Date.now() >= thanksUntil && !thanks.hidden) idle();
    if (!lastSeen || Date.now()-lastSeen>12000) {
        if (thanks.hidden) idle();
        $('connection').hidden = false; $('connection').textContent = 'بانتظار ربط الكاشير';
    }
}, 1500);
async function refreshPlaylist() {
    try {
        const r = await fetch(initial.playlistUrl, {headers:{Accept:'application/json'},cache:'no-store'});
        if (!r.ok) return;
        const next = await r.json();
        if (!Array.isArray(next.media)) return;
        const changed = JSON.stringify(next.media) !== JSON.stringify(config.media) || next.image_seconds !== config.image_seconds;
        config = next; applySettings(); if (changed) { index=0; playMedia(); }
    } catch (_) {}
}
applySettings(); playMedia(); hello(); setInterval(refreshPlaylist,30000);
})();

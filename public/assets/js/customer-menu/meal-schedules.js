(() => {
'use strict';
const root = document.getElementById('tishMealSchedule');
const config = window.TishMealSchedule || {};
const url = document.currentScript?.dataset.statusUrl || config.url;
const CM = window.CustomerMenu;
if (!url || !CM) return;
let status = config.initial || null, start = performance.now(), refreshing = false;
let lastFetch = -Infinity, lastSignature = null;
const en = config.en ?? document.documentElement.lang === 'en';
const label = (ar, enText) => en ? enText : ar;
const now = () => status.server_ms + performance.now() - start;
function format(ms) {
    const s = Math.max(0, Math.ceil(ms / 1000));
    return [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(x => String(x).padStart(2, '0')).join(':');
}
function notify() {
    const signature = CM.PRODUCTS.map(p => [p.id, p.mealPeriod, p.available, CM.mealUnavailable(p)]).map(JSON.stringify).join('|');
    if (signature !== lastSignature) {
        lastSignature = signature;
        document.dispatchEvent(new CustomEvent('tish:meal-status-changed'));
    }
}
function render() {
    let any = false, boundary = false;
    if (status && root) {
        root.querySelectorAll('[data-meal]').forEach(card => {
            const w = status.windows[card.dataset.meal];
            if (!w) return;
            card.hidden = !w.enabled || !w.count;
            if (card.hidden) return;
            any = true;
            const t = now();
            const state = t < w.starts_ms ? 'upcoming' : t < w.ends_ms ? 'open' : 'ended';
            card.dataset.state = state;
            card.querySelector('.meal-hours').textContent = w.start + ' – ' + w.end;
            const message = card.querySelector('.meal-message'), clock = card.querySelector('.meal-clock'), cta = card.querySelector('.meal-cta');
            if (state === 'open') {
                message.textContent = w.ends_ms - t <= 30 * 60000 ? label('آخر فرصة قبل انتهاء الموعد', 'Last chance before closing') : label('متاح الآن • ينتهي خلال', 'Available now • ends in');
                clock.textContent = format(w.ends_ms - t);
                cta.textContent = label('اطلب قبل انتهاء الوقت', 'Order before closing');
            } else if (state === 'upcoming') {
                message.textContent = label('يبدأ بعد', 'Starts in');
                clock.textContent = format(w.starts_ms - t);
                cta.textContent = label('عرض الموعد', 'View meal time');
            } else {
                message.textContent = label('انتهى موعد اليوم', 'Today’s window has ended');
                clock.textContent = label('غدًا ' + w.start, 'Tomorrow ' + w.start);
                cta.textContent = label('عرض الموعد', 'View meal time');
            }
            if (state !== w.state) boundary = true;
        });
        root.hidden = !any;
    }
    notify();
    if (boundary) refresh();
}
async function refresh() {
    if (refreshing || performance.now() - lastFetch < 2500) return;
    refreshing = true;
    lastFetch = performance.now();
    try {
        const response = await fetch(url, {cache: 'no-store', headers: {Accept: 'application/json'}});
        if (!response.ok) return;
        const next = await response.json();
        if (!next.windows || !next.revision || !next.products) return;
        const changed = !status || next.revision !== status.revision;
        status = next;
        start = performance.now();
        CM.applyMealStatus(next);
        render();
        // A newly published product needs its full catalogue/price/options.
        // Only catalogue pages may reload; never reload cart or checkout.
        if (root && changed && Object.keys(next.products).some(id => !CM.product(id))) {
            const key = 'tish.meal.refresh.' + location.pathname + location.search;
            try {
                if (sessionStorage.getItem(key) !== next.revision) {
                    sessionStorage.setItem(key, next.revision);
                    location.reload();
                }
            } catch (_) { /* No reload loop when session storage is blocked. */ }
        }
    } catch (_) { /* Server-side acceptance still enforces the current schedule. */ }
    finally { refreshing = false; }
}
if (status?.products) CM.applyMealStatus(status);
render();
refresh();
setInterval(render, 1000);
setInterval(() => { if (!document.hidden) refresh(); }, 30000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();

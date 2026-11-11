const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/customer-menu/tish-paper-controls-v12.js'), 'utf8');
const styles = fs.readFileSync(path.resolve(__dirname, '../../public/assets/css/customer-menu/tish-paper-controls-v12.css'), 'utf8');

function harness(reduced = false) {
    const listeners = new Map();
    const scheduled = [];
    const make = () => {
        const state = new Set();
        return {
            hidden: false, offsetWidth: 30, state,
            classList: {
                add(k) {state.add(k);},
                remove(k) {state.delete(k);}
            }
        };
    };
    const pin = make(), cart = make(), sticky = make();
    const doc = {
        addEventListener(type, callback) {listeners.set(type,callback);},
        querySelector() {return cart;},
        getElementById() {return sticky;}
    };
    const win = {
        matchMedia: () => ({matches: reduced}),
        setTimeout(fn) {scheduled.push(fn); return scheduled.length;},
        clearTimeout() {}
    };
    vm.runInNewContext(source,{window:win,document:doc});
    return {listeners,scheduled,pin,cart,sticky};
}
test('install pin fully folds and reforms without replacing native PWA click handler', () => {
    const h = harness();
    h.listeners.get('click')({target:{closest:s=>s.includes('pwaInstallBtn')?h.pin:null}});
    assert(h.pin.state.has('paper-pin-fold'));
    h.scheduled.forEach(fn=>fn());
    assert(!h.pin.state.has('paper-pin-fold'));
});
test('cart icon animates only when customer-menu:cart-changed fires', () => {
    const h = harness();
    assert(!h.cart.state.has('paper-cart-icon-pop'));
    h.listeners.get('customer-menu:cart-changed')();
    assert(h.cart.state.has('paper-cart-icon-pop'));
    assert(h.sticky.state.has('paper-cart-icon-pop'));
});
test('reduced motion avoids unnecessary visual animation', () => {
    const h = harness(true);
    h.listeners.get('customer-menu:cart-changed')();
    h.listeners.get('click')({target:{closest:s=>s.includes('pwaInstallBtn')?h.pin:null}});
    assert(!h.cart.state.has('paper-cart-icon-pop'));
    assert(!h.pin.state.has('paper-pin-fold'));
});
test('CSS preserves paper controls and keyboard accessibility', () => {
    assert(styles.includes('favorite-disc.webp'));
    assert(styles.includes('.tish-paper-cart-icon-disc{\n  display:none!important;'));
    assert(styles.includes(':focus-visible'));
    assert(styles.includes('prefers-reduced-motion:reduce'));
    assert(styles.includes('--tish-card-primary'));
});
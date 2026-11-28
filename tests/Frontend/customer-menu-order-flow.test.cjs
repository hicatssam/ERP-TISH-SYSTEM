const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const products = [{product_id: 1, name: 'Cake', price: 10, available: true, is_variant_product: true,
    variants: [{id: 2, name: 'Large', price: 18, modifier_groups: [{id: 3, modifiers: [{id: 4, name: 'Extra', price_delta: 2}]}]}], modifier_groups: []}];
function makeClassList() {
    const names = new Set();
    return {
        add(name) { names.add(name); },
        remove(name) { names.delete(name); },
        contains(name) { return names.has(name); },
        toggle(name, force) {
            const shouldAdd = force === undefined ? !names.has(name) : Boolean(force);
            if (shouldAdd) names.add(name); else names.delete(name);
            return shouldAdd;
        },
    };
}
function cart(blocked = false, saved = {}, table = null, motion = false, clock = {now: 0}) {
    const values = new Map([['dahab_cart_B01', JSON.stringify(saved)]]);
    const stickyCart = {hidden: true, attributes: {}, classList: makeClassList(), setAttribute(name, value) { this.attributes[name] = value; }};
    const animations = [];
    if (motion) {
        stickyCart.animate = (_, options) => {
            animations.push(options.duration);
            return {finished: Promise.resolve(), cancel() {}};
        };
        stickyCart.querySelectorAll = () => [];
    }
    const stickyCount = {textContent: ''};
    const stickyTotal = {textContent: ''};
    const bodyClassList = makeClassList();
    const menuClassList = makeClassList();
    const nodes = {cmStickyCart: stickyCart, cmStickyCartCount: stickyCount, cmStickyCartTotal: stickyTotal};
    let source = fs.readFileSync(path.join(root, 'resources/views/customer-menu/partials/cart-engine.blade.php'), 'utf8');
    source = source.slice(source.indexOf('<script>') + 8, source.indexOf('</script>'));
    source = source.replace('@json($menuItems ?? [])', JSON.stringify(products))
        .replace('@json($location->code)', '"B01"')
        .replace('@json($selectedTable ?? null)', JSON.stringify(table))
        .replace(/@json\(app\([^\n]+\)/, '"₪"');
    const context = {performance: {now: () => clock.now}, window: {}, document: {body: {classList: bodyClassList}, addEventListener() {}, dispatchEvent() {},
        querySelectorAll() { return []; }, querySelector(selector) { return selector === 'main.menu-area' ? {classList: menuClassList} : null; },
        getElementById(id) { return nodes[id] || null; }},
        localStorage: {getItem(k) {return values.get(k) || null;}, setItem(k, v) {if(blocked) throw Error('blocked'); values.set(k, v);}},
        sessionStorage: {getItem(k) {return values.get(k) || null;}, setItem(k, v) {values.set(k, v);}, removeItem(k) {values.delete(k);}},
        CustomEvent: class {}, setTimeout, clearTimeout};
    vm.runInNewContext(source, context);
    const customerMenu = context.window.CustomerMenu;
    customerMenu.__testNodes = nodes;
    customerMenu.__testAnimations = animations;
    customerMenu.__testBodyClassList = bodyClassList;
    customerMenu.__testMenuClassList = menuClassList;
    return customerMenu;
}
test('variant-only add-ons keep the same price through cart reload', () => {
    const cm = cart();
    assert.equal(cm.addToCart(1, 2, {variantId: 2, modifiers: [{modifier_id: 4, quantity: 1}]}), true);
    assert.equal(cm.cartTotal(), 40);
    const reloaded = cart(false, Object.fromEntries(cm.cartRows().map(row => [row.key, row])));
    assert.equal(reloaded.cartTotal(), 40);
    assert.equal(reloaded.cartRows()[0].modifiers[0].modifier_id, 4);
});
test('mobile cart summary mirrors quantity and total, then hides when empty', () => {
    const cm = cart();
    const {cmStickyCart, cmStickyCartCount, cmStickyCartTotal} = cm.__testNodes;
    cm.addToCart(1, 2, {variantId: 2});
    assert.equal(cmStickyCart.hidden, false);
    assert.equal(cmStickyCartCount.textContent, 'قطعتان');
    assert.equal(cmStickyCartTotal.textContent, '36.00 ₪');
    assert.equal(cmStickyCart.attributes['aria-label'], 'عرض السلة، قطعتان، الإجمالي 36.00 ₪');
    assert.equal(cm.__testBodyClassList.contains('cm-has-sticky-cart'), true);
    cm.removeFromCart(cm.cartRows()[0].key);
    assert.equal(cmStickyCart.hidden, true);
    assert.equal(cm.__testBodyClassList.contains('cm-has-sticky-cart'), false);
});
test('sticky cart unfolds once and pulses only when quantity increases', () => {
    const cm = cart(false, {}, null, true);
    cm.syncBadges();
    cm.addToCart(1, 2, {variantId: 2});
    cm.syncBadges();
    cm.changeCart(cm.cartRows()[0].key, 1);
    cm.changeCart(cm.cartRows()[0].key, -1);
    assert.deepEqual(cm.__testAnimations, [420, 340]);
    assert.equal(cm.__testNodes.cmStickyCartCount.textContent, 'قطعتان');
});
test('cart quantity stops at the server limit and can be removed', () => {
    const cm = cart(); cm.addToCart(1, 50, {variantId: 2});
    const key = cm.cartRows()[0].key;
    cm.changeCart(key, 1); assert.equal(cm.cartCount(), 50);
    cm.changeCart(key, -50); assert.equal(cm.cartCount(), 0);
});
test('blocked storage does not interrupt successful checkout housekeeping', () => {
    const cm = cart(true); cm.addToCart(1, 1, {variantId: 2});
    assert.doesNotThrow(() => cm.pushOrder({url: '/menu/order/token'}));
    assert.doesNotThrow(() => cm.clearCart()); assert.equal(cm.cartCount(), 0);
});
test('retried order is stored once in history', () => {
    const cm = cart(); cm.pushOrder({url: '/track/1', location: 'B01'}); cm.pushOrder({url: '/track/1', location: 'B01'});
    assert.equal(cm.ordersHistory().length, 1);
});
async function worker(networkFails) {
    const handlers = {}; let options;
    const fresh = {ok: true, clone() { return this; }, tag: 'fresh'};
    const cached = {tag: 'old'};
    const context = {URL, Response, self: {location: {origin: 'https://example.test'}, addEventListener(type, fn) { handlers[type] = fn; }},
        caches: {async match() {return cached;}, async open() {return {async put() {}};}},
        async fetch(request, opts) {options = opts; if (networkFails) throw Error('offline'); return fresh;}};
    vm.runInNewContext(fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8'), context);
    let response;
    handlers.fetch({request: {method: 'GET', mode: 'cors', url: 'https://example.test/assets/app.js', destination: 'script'}, respondWith(p) {response = p;}});
    return {response: await response, options};
}
test('installed app uses the new script immediately when online', async () => {
    const result = await worker(false); assert.equal(result.response.tag, 'fresh'); assert.equal(result.options.cache, 'no-cache');
});
test('installed app retains cached assets when offline', async () => {
    assert.equal((await worker(true)).response.tag, 'old');
});

test('standalone app checks service worker updates too', async () => {
    let registered = false, updated = false;
    const source = fs.readFileSync(path.join(root, 'resources/views/customer-menu/partials/pwa-install.blade.php'), 'utf8').split('<script>')[1].split('</script>')[0];
    const navigator = {serviceWorker: {register(url, options) {
        assert.equal(url, '/sw.js'); assert.equal(options.updateViaCache, 'none'); registered = true;
        return Promise.resolve({update() {updated = true; return Promise.resolve();}});
    }}};
    vm.runInNewContext(source, {window: {matchMedia() {return {matches: true};}, navigator}, navigator,
        document: {getElementById() {return null;}}});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(registered, true); assert.equal(updated, true);
});

test('refresh token is reused for the same cart but changes for a changed cart', () => {
    const cm = cart(); cm.addToCart(1, 1, {variantId: 2});
    assert.equal(cm.checkoutToken('first'), 'first');
    assert.equal(cm.checkoutToken('new-page'), 'first');
    cm.changeCart(cm.cartRows()[0].key, 1);
    assert.equal(cm.checkoutToken('changed-cart'), 'changed-cart');
});
test('successful checkout clears the pending request token', () => {
    const cm = cart(); cm.addToCart(1, 1, {variantId: 2}); cm.checkoutToken('first');
    cm.clearCart(); cm.addToCart(1, 1, {variantId: 2});
    assert.equal(cm.checkoutToken('second-order'), 'second-order');
});

test('selected bank account exposes receiving details and escapes instructions', () => {
    const source = fs.readFileSync(path.join(root, 'resources/views/customer-menu/checkout/checkout.blade.php'), 'utf8');
    const fn = source.slice(source.indexOf('function renderSelectedPaymentAccount()'), source.indexOf("document.getElementById('paymentProofInput').addEventListener"));
    const target = {innerHTML: ''};
    const context = {methods: [{id: 1, accounts: [{id: 2, account_number: 'ACC-123', account_holder_name: 'Dahab', iban: 'PS-123', instructions: '<img src=x>'}]}],
        CM: cart(), document: {getElementById(id) {return id === 'selectedPaymentAccount' ? target : {value: id === 'paymentMethodId' ? '1' : '2'};}}};
    vm.runInNewContext(fn + '\nrenderSelectedPaymentAccount();', context);
    assert.match(target.innerHTML, /ACC-123/); assert.match(target.innerHTML, /Dahab/); assert.match(target.innerHTML, /PS-123/);
    assert.match(target.innerHTML, /&lt;img/); assert.doesNotMatch(target.innerHTML, /<img/);
});

test('QR table is carried across menu pages and cleared after checkout', () => {
    const cm = cart(false, {}, {id: 7, code: 'T7'});
    assert.equal(cm.selectedTable().id, 7);
    cm.clearCart(); assert.equal(cm.selectedTable(), null);
});
test('QR checkout preselects only the server-resolved table when dine-in is enabled', () => {
    const source = fs.readFileSync(path.join(root, 'resources/views/customer-menu/checkout/checkout.blade.php'), 'utf8');
    const fn = source.slice(source.indexOf('function showLockedTable()'), source.indexOf('function serviceFields()'));
    assert.match(fn, /qrTable/);
    for (const [qrTable, dineIn, expected] of [
        [{id: 7, name: 'T7'}, true, '7'],
        [null, true, ''],
        [{id: 7, name: 'T7'}, false, ''],
    ]) {
        const service = {options: [{value: 'takeaway'}, ...(dineIn ? [{value: 'dine_in'}] : [])], value: 'takeaway'};
        const input = {value: '99'};
        const hint = {hidden: true, textContent: ''};
        const nodes = {serviceType: service, tableIdInput: input, tableOneTimeHint: hint};
        vm.runInNewContext(fn + '\npreselectQrTable();', {
            qrTable,
            CM: {selectedTable() { return {id: 99}; }},
            document: {getElementById(id) {return nodes[id];}},
        });
        assert.equal(service.value, expected ? 'dine_in' : 'takeaway');
        assert.equal(input.value, expected);
        assert.equal(hint.hidden, !expected);
    }
});

test('meal cutoff blocks existing cart lines without deleting them; rescheduling restores eligibility', () => {
    const clock = {now: 0};
    const cm = cart(false, {}, null, false, clock);
    cm.applyMealStatus({server_ms: 1000, products: {'1':'breakfast'}, windows: {breakfast:{state:'open', ends_ms:2000}}});
    assert.equal(cm.addToCart(1, 1, {variantId:2}), true);
    assert.equal(cm.expiredMealRows().length, 0);
    clock.now = 1000;
    assert.equal(cm.expiredMealRows().length, 1);
    assert.equal(cm.cartCount(), 1);
    cm.applyMealStatus({server_ms: 2000, products: {'1':'breakfast'}, windows: {breakfast:{state:'open', ends_ms:6000}}});
    assert.equal(cm.expiredMealRows().length, 0);
    assert.equal(cm.cartCount(), 1);
    cm.applyMealStatus({server_ms: 2000, products: {}, windows: {breakfast:{state:'open', ends_ms:6000}}});
    assert.equal(cm.expiredMealRows().length, 1);
    cm.applyMealStatus({server_ms: 2000, products: {'1':null}, windows: {}});
    assert.equal(cm.expiredMealRows().length, 0);
    assert.equal(cm.product(1).available, true);
});

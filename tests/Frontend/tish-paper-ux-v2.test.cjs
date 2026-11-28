const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const style = read('public/assets/css/customer-menu/tish-paper-ux-v2.css');
const cart = read('resources/views/customer-menu/cart/cart.blade.php');
const checkout = read('resources/views/customer-menu/checkout/checkout.blade.php');

test('paper UX stylesheet is loaded last on cart and checkout', () => {
    for (const source of [cart, checkout]) {
        assert.match(source, /tish-paper-ux-v2\.css/);
        assert.ok(source.indexOf('tish-paper-ux-v2.css') > source.indexOf('tish-drive-ui-fixes-v1.css'));
    }
});
test('cart controls have adequate mobile tap size and unambiguous numeric quantity', () => {
    for (const action of ['inc', 'dec', 'remove']) {
        assert.match(style, new RegExp('button\\[data-' + action + '\\]'));
        assert.match(cart, new RegExp('data-' + action + '='));
    }
    assert.match(style, /font-variant-numeric:tabular-nums/);
    assert.match(style, /min-width:38px!important/);
    assert.match(style, /grid-template-rows:96px minmax\(64px,auto\) 45px 48px/);
    assert.match(cart, /CM\.changeCart\(inc\.dataset\.inc, 1\)/);
    assert.match(cart, /CM\.changeCart\(dec\.dataset\.dec, -1\)/);
});
test('checkout form uses accessible dark content text inside branded paper input background', () => {
    assert.match(style, /input\[name="name"\]/);
    assert.match(style, /input\[name="phone"\]/);
    assert.match(style, /textarea\[name="notes"\]/);
    assert.match(style, /background-size:100% 100%!important/);
    assert.match(style, /font-size:16px!important/);
    assert.match(style, /box-shadow:none!important/);
    assert.match(checkout, /name="service_type"/);
});
test('selected service, table and payment use same paper artwork, without red rectangle', () => {
    assert.match(style, /checkout-paper-choice\[aria-pressed="true"\]/);
    assert.match(style, /\.table-card\.selected/);
    assert.match(style, /\.payment-card\.active/);
    assert.match(style, /nav-active\.webp/);
    assert.match(style, /:focus-visible/);
    assert.match(checkout, /function preselectQrTable/);
    assert.match(checkout, /tableInput\.value = String\(qrTable\.id\)/);
    assert.doesNotMatch(checkout, /function selectCheckoutTable/);
    assert.match(checkout, /function choosePayment/);
    assert.match(checkout, /serviceFields\(\)/);
});

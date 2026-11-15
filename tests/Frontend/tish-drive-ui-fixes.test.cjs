const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const styles = read('public/assets/css/customer-menu/tish-drive-ui-fixes-v1.css');
const show = read('resources/views/customer-menu/show.blade.php');
const checkout = read('resources/views/customer-menu/checkout/checkout.blade.php');
const cart = read('resources/views/customer-menu/cart/cart.blade.php');
const orders = read('resources/views/customer-menu/my-orders.blade.php');
const topbar = read('resources/views/customer-menu/partials/topbar.blade.php');

test('each affected customer-menu screen loads the screenshot refinements', () => {
    for (const source of [show, checkout, cart, orders]) {
        assert.match(source, /tish-drive-ui-fixes-v1\.css/);
    }
});
test('checkout payment tiles retain payment method IDs and selection handling', () => {
    assert.match(styles, /\.payment-methods\s*\{/);
    assert.match(styles, /repeat\(2,minmax\(0,1fr\)\)/);
    assert.match(styles, /repeat\(3,minmax\(0,1fr\)\)/);
    assert.match(checkout, /data-pay="\$\{m\.id\}"/);
    assert.match(checkout, /function choosePayment\(id\)/);
    assert.match(checkout, /paymentMethodId/);
    assert.match(checkout, /cart-line-total/);
    assert.match(checkout, /cart-line-meta/);
});
test('search focus is toned down while focus-visible is still accessible', () => {
    assert.match(styles, /\.search input:focus-visible/);
    assert.match(styles, /outline:2px solid/);
    assert.match(styles, /\.search input:focus\s*\{/);
});
test('hero brand emblem is enlarged and screenshot spark decoration removed', () => {
    assert.match(styles, /\.reference-logo-square/);
    assert.match(styles, /\.tish-paper-brand-hero \.paper-stamp/);
    assert.match(styles, /class\*="spark"/);
});
test('promo slider supports swipe and preserves dot and autoplay controls', () => {
    assert.match(show, /track\.addEventListener\('pointerdown'/);
    assert.match(show, /track\.addEventListener\('pointerup'/);
    assert.match(show, /showBanner\(bannerIndex \+ \(deltaX < 0 \? 1 : -1\)\)/);
    assert.match(show, /dot\.addEventListener\('click'/);
    assert.match(show, /syncBannerTimer\(\)/);
});
test('orders frame cleaned and cart switched to round image cards', () => {
    assert.match(styles, /body\.tish-paper\.cm-my-orders::after/);
    assert.match(styles, /body\.tish-paper\.tish-cart-page #cartItems \.cart-thumb/);
    assert.match(styles, /grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/);
    assert.match(cart, /data-inc/);
    assert.match(cart, /data-dec/);
    assert.match(cart, /data-remove/);
});
test('repeated pin and branch switch removed without changing branch-entry flow', () => {
    assert.doesNotMatch(show, /<div class="tish-home-install">/);
    assert.doesNotMatch(topbar, /تغيير الفرع/);
    assert.match(styles, /#pwaInstallBtn\.tish-paper-pin-control/);
    assert.match(show, /branch-entry\.js/);
});

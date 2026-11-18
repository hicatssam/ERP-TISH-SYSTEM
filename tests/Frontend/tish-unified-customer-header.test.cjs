const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');

const partial = read('resources/views/customer-menu/partials/unified-header.blade.php');
const css = read('public/assets/css/customer-menu/tish-unified-header-v1.css');
const i18n = read('public/assets/js/customer-menu/i18n.js');

const pages = [
 'cart/cart.blade.php',
 'favorites.blade.php',
 'checkout/checkout.blade.php',
 'products.blade.php',
 'product.blade.php',
];
test('all navigable public TISH product/cart pages use one shared header', () => {
 const topbar = read('resources/views/customer-menu/partials/topbar.blade.php');
 assert.match(topbar, /partials\.unified-header/);
 for (const page of pages) {
  const file = read('resources/views/customer-menu/' + page);
  assert.match(file, /partials\.topbar/, page + ' must use shared header');
 }
 for (const page of ['my-orders.blade.php','track.blade.php']) {
  const file = read('resources/views/customer-menu/' + page);
  assert.match(file, /partials\.unified-header/, page + ' must use the same header');
 }
});
test('brand is fixed left, language above back on right, irrespective of RTL/LTR', () => {
 assert.match(partial, /class="cm-unified-header-brand"/);
 assert.match(partial, /class="cm-unified-header-logo"/);
 assert.match(partial, /class="cm-unified-header-brand-copy"/);
 assert.match(partial, /data-cm-language-host/);
 assert.match(partial, /class="cm-unified-header-back/);
 assert.match(css, /direction:ltr!important/);
 assert.match(css, /grid-template-columns:minmax\(0,1fr\) 68px/);
 assert.match(css, /\.cm-language-switch\s*\{[^}]+grid-row:1!important/s);
 assert.match(css, /\.cm-unified-header-back\s*\{[^}]+grid-row:2!important/s);
});
test('language behavior still targets the shared button host before legacy header locations', () => {
 assert.match(i18n, /document\.querySelector\('\[data-cm-language-host\]'\)/);
 assert.match(i18n, /document\.cookie = cookieName/);
 assert.match(i18n, /url\.searchParams\.set\('lang',next\)/);
});
test('tracking continues back to orders; checkout continues back to cart', () => {
 const tracking = read('resources/views/customer-menu/track.blade.php');
 const checkout = read('resources/views/customer-menu/checkout/checkout.blade.php');
 assert.match(tracking, /backUrl' => route\('customer-menu\.my-orders'/);
 assert.match(checkout, /backUrl' => route\('customer-menu\.cart'/);
});

/* node --test tests/js/product-circle-card.test.cjs
 * UI-only tests: no real DB, billing or Laravel state is altered.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');
const component = readFileSync(resolve(__dirname, '../../public/assets/js/customer-menu/tish-product-circle-card-v8.js'), 'utf8');
function mock() {
  const state = { added: [], favorites: new Set(['11']) };
  const cm = {
    esc: text => String(text ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch])),
    money: value => Number(value).toFixed(2) + ' ₪',
    isFavorite: id => state.favorites.has(String(id)),
    image: (p, c) => `<img class="${c} tish-photo-cutout" src="/photo.webp" alt="${p.name}">`,
    addToCart: (id, qty) => { state.added.push({ id, qty }); return true; },
  };
  const window = { CustomerMenu: cm, location: { href: '' }, setTimeout: callback => callback() };
  vm.runInNewContext(component, { window });
  return { api: window.TishProductCircleCard, window, state };
}
function sample(overrides = {}) {
  return { id: 11, name: 'برجر كلاسيك', category_name: 'برجر لحم', price: 22, available: true, requiresChoices: false, ...overrides };
}
test('all pieces of the circular paper card render and use dynamic product data', () => {
  const { api } = mock();
  const html = api.render(sample(), { isPopular: true, productUrl: id => '/menu/B01/product/' + id });
  for (const cls of ['tish-circle-paper', 'tish-circle-favorite', 'tish-circle-badge--popular', 'tish-circle-image', 'tish-circle-name', 'tish-circle-category', 'tish-circle-price', 'tish-circle-add']) {
    assert.ok(html.includes(cls), `Missing ${cls}`);
  }
  assert.match(html, /22\.00 ₪/);
  assert.match(html, /برجر كلاسيك/);
  assert.match(html, /برجر لحم/);
  assert.match(html, /aria-pressed="true"/);
  assert.match(html, /data-add="11"/);
});
test('popular badge only appears for actually ranked available products', () => {
  const { api } = mock();
  assert.doesNotMatch(api.render(sample(), {}), /الأكثر طلبًا/);
  assert.match(api.render(sample(), { isPopular: true }), /الأكثر طلبًا/);
  assert.doesNotMatch(api.render(sample({available:false}), {isPopular:true}), /الأكثر طلبًا/);
  assert.match(api.render(sample({available:false})), /disabled/);
});
test('long and hostile names escape correctly', () => {
  const { api } = mock();
  const html = api.render(sample({name:'صوص <سبيشال> "x" & لحمة'}));
  assert.ok(html.includes('&lt;سبيشال&gt;'));
  assert.ok(html.includes('&quot;x&quot;'));
  assert.ok(html.includes('&amp;'));
});
test('existing cart is called only once; repeated taps are guarded', () => {
  const { api, state } = mock();
  const label = { textContent: 'إضافة للسلة' };
  const icon = { className: 'fa-solid fa-cart-plus' };
  const button = {
    disabled: false, dataset: {}, isConnected: true,
    classList: { add() {}, remove() {} },
    querySelector: selector => selector === 'i' ? icon : label,
  };
  assert.equal(api.addFromButton(button, sample(), '/product/11'), true);
  assert.equal(state.added.length, 1);
  assert.equal(label.textContent, 'إضافة للسلة');
  assert.equal(button.disabled, false);
  button.dataset.busy = '1';
  assert.equal(api.addFromButton(button, sample(), '/product/11'), false);
  assert.equal(state.added.length, 1);
});
test('products with options still go to detail page, not directly to cart', () => {
  const { api, window, state } = mock();
  const button = { disabled: false, dataset: {} };
  assert.equal(api.addFromButton(button, sample({requiresChoices:true}), '/menu/B01/product/11'), false);
  assert.equal(window.location.href, '/menu/B01/product/11');
  assert.equal(state.added.length, 0);
});
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const home = fs.readFileSync(path.join(root, 'resources/views/customer-menu/show.blade.php'), 'utf8');
const svg = fs.readFileSync(path.join(root, 'public/assets/tish-paper/brand/tish-drive-hero-clean.svg'), 'utf8');

test('TISH homepage uses the self-contained PSD-derived hero image, not the old black-placeholder art', () => {
  assert.match(home, /tish-drive-hero-clean\.svg/);
  assert.doesNotMatch(home, /asset\('assets\/tish-paper\/brand\/tish-drive-hero\.png'\)/);
  assert.match(svg, /viewBox="0 0 630 360"/);
  const match = svg.match(/xlink:href="data:image\/webp;base64,([A-Za-z0-9+/=]+)"/);
  assert.ok(match, 'The cleaned artwork must be embedded, not fetched from a private Drive URL');
  const image = Buffer.from(match[1], 'base64');
  assert.ok(image.length > 10000, 'The full 630x360 artwork should be preserved');
  assert.equal(image.subarray(0, 4).toString('ascii'), 'RIFF');
  assert.equal(image.subarray(8, 12).toString('ascii'), 'WEBP');
});

test('existing cart and favorites overlays remain clickable and language remains attached', () => {
  assert.match(home, /class="tish-drive-hero-actions"/);
  assert.match(home, /class="icon-btn tish-drive-hero-cart"/);
  assert.match(home, /class="icon-btn tish-drive-hero-fav"/);
  assert.match(home, /id="cartBadgeTop"/);
  assert.match(home, /id="favBadgeTop"/);
  const i18n = fs.readFileSync(path.join(root, 'public/assets/js/customer-menu/i18n.js'), 'utf8');
  assert.match(i18n, /body\.reference-home \.tish-drive-hero-actions/);
});

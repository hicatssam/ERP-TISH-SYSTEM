const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const i18n = fs.readFileSync(path.join(root, 'public/assets/js/customer-menu/i18n.js'), 'utf8');
const hero = fs.readFileSync(path.join(root, 'public/assets/css/customer-menu/tish-drive-home-hero-v1.css'), 'utf8');
const layout = fs.readFileSync(path.join(root, 'resources/views/customer-menu/show.blade.php'), 'utf8');

test('TISH home language toggle lives inside same hero control layer as favorites', () => {
    assert.match(i18n, /document\.querySelector\('body\.reference-home \.tish-drive-hero-actions'\)/);
    assert.match(layout, /class="icon-btn tish-drive-hero-fav"/);
    assert.match(layout, /class="tish-drive-hero-actions"/);
});

test('language selector stacks above favorites and remains clickable', () => {
    const language = hero.match(/\.tish-drive-hero-actions \.cm-language-switch\{([^}]+)\}/);
    const favorites = hero.match(/\.tish-drive-hero-actions \.tish-drive-hero-fav\{\s*top:([^;]+);/);
    assert.ok(language, 'Language position must be scoped to TISH hero');
    assert.match(language[1], /top:2%!important/);
    assert.match(language[1], /pointer-events:auto/);
    assert.ok(favorites, 'Favorites must be offset below language selector');
    assert.match(favorites[1], /calc\(2% \+ 43px\)/);
});

test('language switching remains cookie and URL based', () => {
    assert.match(i18n, /button\.addEventListener\('click'/);
    assert.match(i18n, /url\.searchParams\.set\('lang',next\)/);
    assert.match(i18n, /document\.cookie = cookieName/);
});

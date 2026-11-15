const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const pos = fs.readFileSync(path.join(root, 'resources/views/restaurant/pos/index.blade.php'), 'utf8');
const service = fs.readFileSync(path.join(root, 'app/Services/Restaurant/RestaurantPosCatalogService.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'app/Http/Controllers/PwaController.php'), 'utf8');
const routes = fs.readFileSync(path.join(root, 'routes/pwa.php'), 'utf8');

test('POS starts with server catalogue and retains full branch/user scoped snapshot', () => {
    assert.match(pos, /let products = @json\(\$productPayload\)/);
    assert.match(pos, /tish_pos_catalog_v2_\$\{cashierUserId\}_\$\{locationId\}/);
    assert.match(pos, /sessionStorage\.getItem\(catalogStorageKey\)/);
    assert.match(pos, /sessionStorage\.setItem\(catalogStorageKey/);
    assert.match(pos, /catalogTtlMs = 5 \* 60 \* 1000/);
    assert.match(pos, /completeCatalog && allProducts\.length/);
});
test('POS filters cached complete catalog without querying server on each tab', () => {
    assert.match(pos, /function filterLoadedCatalog\(\)/);
    assert.match(pos, /if \(!forceServer && !append && filterLoadedCatalog\(\)\)/);
    assert.match(pos, /function warmCatalog\(\)/);
    assert.match(service, /'category_id' =>/);
    assert.match(service, /'brand_id' =>/);
});
test('explicit refresh re-fetches live catalogue and existing checkout stays server-backed', () => {
    assert.match(pos, /id="rbRefreshCatalog"/);
    assert.match(pos, /sessionStorage\.removeItem\(catalogStorageKey\)/);
    assert.match(pos, /loadCatalog\(1, false, true\)/);
    assert.match(pos, /form\.addEventListener\('submit'/);
    assert.match(pos, /submitOrder\(/);
});
test('staff and cashier installation icons use TISH circular logo source', () => {
    assert.match(controller, /function cashierIcon\(int \$size\)/);
    assert.match(controller, /assets\/tish-paper\/brand\/logo-disc\.webp/);
    assert.match(controller, /imagesetpixel\(\$canvas, \$x, \$y, \$transparent\)/);
    assert.match(routes, /name\('pwa\.cashier-icon'\)/);
    assert.equal((controller.match(/'src' => route\('pwa\.cashier-icon'/g) || []).length, 4);
});

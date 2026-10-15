const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {makeReport} = require('../../scripts/audit-mobile-ux.cjs');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('mobile UX auditor walks every Blade template across all application modules', () => {
    const report = makeReport();
    assert.ok(report.checkedBlades >= 300, 'Unexpectedly incomplete Blade inventory');
    for (const section of ['admin', 'sales', 'finance', 'inventory', 'restaurant', 'procurement', 'production']) {
        assert.ok(report.modules[section] > 0, 'Missing module from audit: ' + section);
    }
    assert.equal(report.pages.length, report.checkedBlades);
    assert.ok(report.reviewQueue.every(row => row.findings.length));
});

test('systemwide tables keep horizontal scrolling instead of clipping action columns on phones', () => {
    const css = read('public/assets/css/app.css');
    assert.match(css, /\.dahab-content \.table-wrap[\s\S]*?overflow-x:\s*auto/);
    assert.match(css, /-webkit-overflow-scrolling:\s*touch/);
    assert.match(css, /\.dahab-confirm-box[\s\S]*?max-height:\s*min\(90dvh/);
    assert.match(css, /safe-area-inset-bottom/);
});

test('shared action dialog uses native showModal, responsive height and accessible close controls', () => {
    const source = read('resources/views/components/action-dialog.blade.php');
    assert.match(source, /<dialog\b/);
    assert.match(source, /dialog\.showModal\(\)/);
    assert.match(source, /max-height:\s*calc\(100dvh/);
    assert.match(source, /erp-action-dialog-close[^\n]*44px/);
    assert.match(source, /addEventListener\('close'/);
});

test('centralized confirmations trap keyboard focus and prevent rapid double-tap actions', () => {
    const script = read('public/assets/js/app.js');
    assert.match(script, /event\.key !== 'Tab'/);
    assert.match(script, /first\.focus\(\)/);
    assert.match(script, /last\.focus\(\)/);
    assert.match(script, /this\.disabled = true/);
});

test('cashier has one in-app new-order popup and inline error feedback instead of browser dialogs', () => {
    const pos = read('resources/views/restaurant/pos/index.blade.php');
    assert.match(pos, /id="rbNewOrderConfirm"/);
    assert.match(pos, /async function clearOrder/);
    assert.match(pos, /await confirmNewOrder\(\)/);
    assert.doesNotMatch(pos, /\bwindow\.(?:confirm|alert|prompt)\s*\(/);
    assert.match(pos, /\.rb-modal-dialog[\s\S]*?90dvh/);
});

test('special cake and product forms have responsive, focus-restoring popups', () => {
    const cake = read('resources/views/sales/cake-orders/show.blade.php');
    const products = read('resources/views/admin/products/index.blade.php');
    assert.doesNotMatch(cake, /onclick="return confirm\(/);
    assert.match(cake, /statusModalTrigger\.focus/);
    assert.match(cake, /status-modal-dialog[\s\S]*?100dvh/);
    assert.match(products, /productCreateTrigger\.focus/);
    assert.match(products, /100dvh/);
});

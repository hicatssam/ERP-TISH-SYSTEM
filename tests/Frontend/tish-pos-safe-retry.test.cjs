const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/restaurant/pos/index.blade.php'), 'utf8');
const request = fs.readFileSync(path.join(__dirname, '../../app/Http/Requests/Restaurant/StoreRestaurantPosOrderRequest.php'), 'utf8');
const service = fs.readFileSync(path.join(__dirname, '../../app/Services/Restaurant/RestaurantOrderService.php'), 'utf8');

test('POS sends request token as form field and server validates UUID', () => {
    assert.match(view, /name="pos_request_token" id="rbPosRequestToken"/);
    assert.match(request, /'pos_request_token'\s*=>\s*\['nullable', 'uuid'\]/);
    assert.match(service, /'public_request_token'\]\s*=\s*\$token/);
});

test('network retries reuse request token for identical cart and checkout', () => {
    assert.match(view, /function checkoutFingerprint\(\)/);
    assert.match(view, /function checkoutRequestToken\(\)/);
    assert.match(view, /attempt\.fingerprint !== fingerprint/);
    assert.match(view, /sessionStorage\.setItem\(posAttemptKey/);
    assert.match(view, /checkoutRequestToken\(\)/);
    assert.match(service, /pos_payload_hash/);
});

test('successful checkout remains locked until redirect and clears token', () => {
    assert.match(view, /rbCheckoutSucceeded = true/);
    assert.match(view, /rbSubmitting = rbCheckoutSucceeded/);
    assert.match(view, /button\.disabled = rbCheckoutSucceeded/);
    assert.match(view, /sessionStorage\.removeItem\(posAttemptKey\)/);
});

test('failure warns cashier to inspect previous orders before retry', () => {
    assert.match(view, /افحص الطلبات قبل إعادة المحاولة/);
    assert.match(view, /const response = await fetch\(/);
    assert.match(view, /'X-Requested-With': 'XMLHttpRequest'/);
});

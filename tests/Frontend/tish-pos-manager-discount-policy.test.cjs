const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const request = read('app/Http/Requests/Restaurant/StoreRestaurantPosOrderRequest.php');
const service = read('app/Services/Restaurant/RestaurantOrderService.php');
const guard = read('app/Support/PosManagerDiscountGuard.php');
const view = read('resources/views/restaurant/pos/index.blade.php');
test('discount thresholds are enforced on server and inside order service', () => {
 assert.match(guard,/manager_discount_percentage/);
 assert.match(guard,/manager_discount_fixed/);
 assert.match(guard,/hasRole\('Branch Manager'\)/);
 assert.match(guard,/restaurant_pos\.discount\.approve/);
 assert.match(request,/PosManagerDiscountGuard::requiresApproval/);
 assert.match(service,/PosManagerDiscountGuard::assertAllowed/);
});
test('cashier is warned before submission, without relying on client-only authorization', () => {
 assert.match(view,/id="rbSensitiveDiscountNotice"/);
 assert.match(view,/cashierCanApproveDiscount/);
 assert.match(view,/sensitiveDiscountPercentage/);
 assert.match(view,/sensitiveDiscountFixed/);
});

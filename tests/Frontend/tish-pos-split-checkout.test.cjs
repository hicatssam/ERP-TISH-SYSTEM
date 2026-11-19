const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const view = read('resources/views/restaurant/pos/index.blade.php');
const request = read('app/Http/Requests/Restaurant/StoreRestaurantPosOrderRequest.php');
const service = read('app/Services/Restaurant/RestaurantOrderService.php');
const controller = read('app/Http/Controllers/Restaurant/RestaurantPosController.php');
test('POS has accessible responsive two-tender UI with amount reference and noncash receipt confirmation', () => {
 assert.match(view,/id="rbSplitTenderToggle"/);
 assert.match(view,/id="rbSplitTenderPanel"/);
 assert.match(view,/name="split_payments\[\{\{ \$i \}\}\]\[amount\]"/);
 assert.match(view,/data-split-method/);
 assert.match(view,/data-split-amount/);
 assert.match(view,/data-split-reference/);
 assert.match(view,/data-split-confirm/);
 assert.match(view,/\.rb-split-grid\{display:grid/);
 assert.match(view,/@media\(max-width:680px\)/);
});
test('checkout requires both different methods, exact cent balance and collected noncash confirmation', () => {
 assert.match(view,/splitMode\(\)/);
 assert.match(view,/getTotals\(\)\.total \* 100/);
 assert.match(view,/splitMethods\[0\]\.value === splitMethods\[1\]\.value/);
 assert.match(view,/splitConfirmations\[i\]\.checked/);
 assert.match(view,/paymentMethod\.disabled = true/);
 assert.match(view,/splitTenderToggle\.checked = Boolean\(fields\.split_tender_enabled\)/);
});
test('cash split tenders hide and disable the noncash confirmation and reference controls', () => {
 assert.match(view,/splitReferences\[i\]\.disabled = !needsReference/);
 assert.match(view,/splitReferences\[i\]\.style\.display = needsReference/);
 assert.match(view,/splitConfirmations\[i\]\.disabled = !nonCash/);
 assert.match(view,/splitConfirmations\[i\]\.parentElement\.style\.display = nonCash/);
});
test('invoice and print cannot silently bypass immediate settlement', () => {
 assert.match(request,/\$this->input\('pos_action'\) === 'bill_print'/);
 assert.match(request,/\$arrangement !== PaymentArrangement::PayNow->value/);
});
test('four POS actions distinguish draft, unpaid kitchen print, payment and paid invoice print', () => {
 assert.match(view,/id="rbKotPrintBtn"/);
 assert.match(view,/id="rbSaveDraftBtn"/);
 assert.match(view,/id="rbBillPaymentBtn"/);
 assert.match(view,/id="rbBillPrintBtn"/);
 assert.match(view,/billPaymentBtn.addEventListener\('click', \(\) => openCheckout\('bill_payment'\)\)/);
 assert.match(view,/billPrintBtn.addEventListener\('click', \(\) => openCheckout\('bill_print'\)\)/);
 assert.match(view,/kotPrintBtn.addEventListener\('click', \(\) => submitOrder\('kot_print'\)\)/);
 assert.match(view,/saveDraftBtn.addEventListener\('click', saveLocalDraft\)/);
 assert.match(view,/localStorage.setItem\(draftKey, JSON.stringify\(serializeDraft\(\)\)\)/);
 assert.match(view,/printingInvoice && option.value !== 'pay_now'/);
 assert.match(view,/if \(printingInvoice\) paymentArrangement.value = 'pay_now'/);
 assert.match(view,/pendingAction === 'bill_print' && paymentArrangement.value !== 'pay_now'/);
 assert.match(view,/\[\.\.\.paymentArrangement.options\].forEach\(option => \{ option.disabled = false; \}\)/);
});
test('server enforces immediate two-entry mixed payment and atomic full settlement', () => {
 assert.match(request,/'split_payments' => \['nullable', 'array', 'size:2'\]/);
 assert.match(request,/PaymentArrangement::PayNow->value/);
 assert.match(request,/requires_verification/);
 assert.match(service,/\$splitPayments = \$data\['split_payments'\]/);
 assert.match(service,/\$expectedCents/);
 assert.match(service,/\$actualCents !== \$expectedCents/);
 assert.match(service,/app\(PaymentService::class\)->recordPayment/);
 assert.match(controller,/foreach \(\(\$data\['split_payments'\] \?\? \[\]\) as \$index => \$part\)/);
});

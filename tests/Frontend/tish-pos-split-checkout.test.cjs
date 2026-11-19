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
test('POS four action cards expose clear labels, icons, sublabels, and accessible mobile grid', () => {
 assert.match(view,/<span class="rb-action-title">إرسال للمطبخ<\/span>/);
 assert.match(view,/<small class="rb-action-subtitle">بدون دفع<\/small>/);
 assert.match(view,/<span class="rb-action-title">حفظ مسودة<\/span>/);
 assert.match(view,/<small class="rb-action-subtitle">استكمال لاحقًا<\/small>/);
 assert.match(view,/<span class="rb-action-title">تحصيل الدفع<\/span>/);
 assert.match(view,/<small class="rb-action-subtitle">نقدي \/ بطاقة \/ مختلط<\/small>/);
 assert.match(view,/<span class="rb-action-title">إتمام وطباعة<\/span>/);
 assert.match(view,/<small class="rb-action-subtitle">بعد التحقق من الدفع<\/small>/);
 assert.match(view,/\.rb-action:focus-visible/);
 assert.match(view,/\.rb-order-actions \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/);
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

test('POS quick customer supports full details, individual default, company fields and authorized credit', () => {
 assert.match(view, /id="rbQuickCustomerType"/);
 assert.match(view, /<option value="individual" selected>فرد<\/option>/);
 for (const field of ['rbQuickCustomerSecondaryPhone','rbQuickCustomerContactPerson',
    'rbQuickCustomerTaxNumber','rbQuickCustomerAddress','rbQuickCustomerNotes',
    'rbQuickCustomerScope','rbQuickCustomerAllowCredit','rbQuickCustomerCreditLimit',
    'rbQuickCustomerBillingCycle','rbQuickCustomerPaymentTerms']) {
    assert.ok(view.includes('id="' + field + '"'), field);
 }
 assert.match(view, /quickCustomerType.value = 'individual'/);
 assert.match(view, /syncQuickCustomerFields\(\)/);
 assert.match(view, /customer_type: quickCustomerType.value/);
 assert.match(view, /customerSelect.value = String\(customer.id\)/);
});

test('POS stays on cashier screen after checkout and previews local/server drafts without redirect', () => {
 assert.match(view, /window\.location\.assign\(url\.toString\(\)\)/);
 assert.match(view, /const url = new URL\(cashierPosUrl, window\.location\.origin\)/);
 assert.doesNotMatch(view, /window\.location\.href\s*=\s*\n\s*data\?\.redirect_url/);
 assert.match(view, /id="rbDraftPreviewModal"/);
 assert.match(view, /function openDraftPreview\(draft, kind\)/);
 assert.match(view, /function loadServerDrafts\(page = 1\)/);
 assert.match(view, /loadServerDrafts\(\)/);
 assert.match(view, /openDraftPreview\(order, 'server'\)/);
 assert.match(view, /openDraftPreview\(current, 'local'\)/);
 assert.match(view, /draftPreviewRestore\.addEventListener\('click'/);
 assert.match(view, /draftPreviewModal\.hidden = false/);
 assert.match(view, /draftRequest \|\| drawerMode !== 'draft'/);
});

test('POS does not silently expose empty payment-method dropdowns as valid split checkout', () => {
 assert.match(view, /paymentMethods->isEmpty\(\)/);
 assert.match(view, /eligibleSplitMethods->count\(\) < 2/);
 assert.match(view, /id="rbSplitTenderToggle" @disabled/);
 assert.match(view, /لا توجد طرق دفع نشطة ومتاحة/);
 assert.match(view, /لا توجد طريقتا دفع مختلط متاحتان/);
 assert.match(view, /paymentMethod\.options\.length <= 1/);
});

test('POS stage B edits only unpaid server drafts in the popup without creating a second order', () => {
 assert.match(view, /id="rbDraftPreviewSave"/);
 assert.match(view, /const draftEditUrlPattern = @json/);
 assert.match(view, /draftPreviewSave\.hidden = kind !== 'server' \|\| !draft\.editable/);
 assert.match(view, /draftPreviewSave\.addEventListener\('click', async/);
 assert.match(view, /method: 'PATCH'/);
 assert.match(view, /'X-CSRF-TOKEN'/);
 assert.match(view, /data-draft-quantity/);
 assert.match(view, /data-draft-notes/);
 assert.match(view, /if \(!activeServerDraft\?\.editable \|\| draftPreviewSave\.disabled\) return/);
});

test('POS stage B draft cancellation requests a reason and never directly erases orders', () => {
 assert.match(view, /id="rbDraftPreviewCancel"/);
 assert.match(view, /id="rbDraftCancelReason"/);
 assert.match(view, /draftPreviewCancel\.hidden = kind !== 'server' \|\| !draft\.cancelable/);
 assert.match(view, /draftPreviewCancel\.addEventListener\('click', async/);
 assert.match(view, /cancellation_reason: reason/);
 assert.match(view, /reason\.length < 8/);
 assert.match(view, /pendingDraftCancellation = true/);
 assert.match(view, /draftPreviewCancel\.textContent = 'تأكيد إلغاء المسودة'/);
 assert.doesNotMatch(view, /window\.confirm\(/);
});

test('POS stage B cancels one draft item only before kitchen without emptying the order', () => {
 assert.match(view, /remove\.dataset\.draftRemove = String\(item\.id\)/);
 assert.match(view, /const removedIds = activeServerDraft\.items/);
 assert.match(view, /remove_item_ids: removedIds/);
 assert.match(view, /if \(!items\.length\)/);
 assert.match(view, /لا يمكن حذف جميع العناصر/);
});

test('POS UX touch controls are at least 44px and mobile cart totals stay in sync', () => {
 assert.match(view, /\.rb-pos \.rb-qty-btn \{width:44px;height:44px;min-width:44px;min-height:44px/);
 assert.match(view, /\.rb-pos \.rb-cart-remove \{min-width:44px;min-height:44px/);
 assert.match(view, /\.rb-pos \.rb-add-note \{min-height:44px/);
 assert.match(view, /id="rbMobileCartBar"/);
 assert.match(view, /id="rbMobileCheckoutBtn"/);
 assert.match(view, /id="rbMobileCartTotal"/);
 assert.match(view, /id="rbMobileCartCount"/);
 assert.match(view, /document\.getElementById\('rbMobileCartTotal'\)\.textContent = money\(totals\.total\)/);
 assert.match(view, /document\.getElementById\('rbMobileCheckoutBtn'\)\.disabled = !cart\.size/);
 assert.match(view, /@media\(max-width:820px\)/);
 assert.match(view, /position:fixed;bottom:0;left:0;right:0/);
 assert.match(view, /rbMobileCheckoutBtn'\)\.addEventListener\('click'/);
});
test('POS UX quick cash only preselects configured cash and retains normal validation and explicit confirmation', () => {
 assert.match(view, /id="rbQuickCashBtn"/);
 assert.match(view, /id="rbQuickCashExpand"/);
 assert.match(view, /const cash = \[\.\.\.paymentMethod\.options\]\.find\(option => option\.value && option\.dataset\.type === 'cash' && !option\.disabled\)/);
 assert.match(view, /if \(!payNow \|\| !cash\)/);
 assert.match(view, /paymentArrangement\.value = 'pay_now'/);
 assert.match(view, /splitTenderToggle\.checked = false/);
 assert.match(view, /paymentMethod\.value = cash\.value/);
 assert.match(view, /const canCompact = !discountAlreadyEntered && !notesAlreadyEntered/);
 assert.match(view, /checkoutModal\.classList\.toggle\('rb-quick-cash', canCompact\)/);
 assert.match(view, /checkoutModal\.classList\.remove\('rb-quick-cash'\)/);
 assert.match(view, /\[data-quick-cash-optional\]/);
 assert.match(view, /!validatePaymentFields\(\)/);
 const start = view.indexOf("quickCashBtn.addEventListener('click'");
 const end = view.indexOf("quickCashExpand.addEventListener('click'", start);
 assert.ok(start >= 0 && end > start);
 assert.doesNotMatch(view.slice(start, end), /submitOrder\(/);
});

test('POS peak shift can search older receipts and reprint through authorized read-only route', () => {
 assert.match(view, /id="rbReceiptsBtn"/);
 assert.match(view, /route\('restaurant\.pos\.receipt-search'\)/);
 assert.match(view, /route\('restaurant\.pos\.orders\.invoice-print', \$order\)/);
 assert.match(view, /receiptsBtn\.addEventListener\('click', \(\) => openDrawer\('receipts'\)\)/);
 assert.match(view, /async function loadReceiptSearch\(\)/);
 assert.match(view, /mode === 'receipts'/);
 assert.match(view, /card\.href = order\.print_url/);
});
test('POS preflights unavailable menu items but preserves retry tokens after ambiguous server completion', () => {
 assert.match(view, /route\('restaurant\.pos\.cart-availability'\)/);
 assert.match(view, /async function checkCartAvailability\(\)/);
 assert.match(view, /data\.unavailable_ids\.length/);
 assert.match(view, /hadMatchingCheckoutAttempt = saved\?\.fingerprint === checkoutFingerprint\(\)/);
 assert.match(view, /if \(!hadMatchingCheckoutAttempt\) \{/);
 assert.match(view, /await checkCartAvailability\(\)/);
 assert.match(view, /sessionStorage\.removeItem\(posAttemptKey\)/);
 const i = view.indexOf('async function submitOrder(action)');
 const j = view.indexOf('const response = await fetch(\n            form.action', i);
 const check = view.indexOf('await checkCartAvailability()', i);
 assert.ok(i >= 0 && check > i && j > check, 'availability checked before order POST');
});

test('POS permits privileged cancellation of a queued unpaid KDS line without leaving cashier', () => {
 assert.match(view, /id="rbQueuedCancelModal"/);
 assert.match(view, /id="rbQueuedCancelItem"/);
 assert.match(view, /id="rbQueuedCancelReason"/);
 assert.match(view, /data-queued-cancel-order/);
 assert.match(view, /route\('restaurant\.pos\.orders\.queued-lines'/);
 assert.match(view, /route\('restaurant\.pos\.orders\.cancel-queued-line'/);
 assert.match(view, /async function openQueuedCancel\(orderId\)/);
 assert.match(view, /queuedCancelPending = true/);
 assert.match(view, /queuedCancelConfirm\.textContent = 'تأكيد إلغاء العنصر'/);
 assert.match(view, /method:'POST'/);
 assert.match(view, /body:JSON\.stringify\(\{order_item_id:Number\(queuedCancelSelect\.value\),cancellation_reason:reason\}\)/);
 assert.match(view, /data\.items \|\| \[\]/);
 assert.match(view, /option\.disabled = !item\.eligible/);
 assert.doesNotMatch(view, /window\.confirm\(/);
});

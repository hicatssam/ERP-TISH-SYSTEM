const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const view = fs.readFileSync(
  path.join(__dirname, '../../resources/views/restaurant/pos/index.blade.php'), 'utf8'
);

// Exercise the same pure helpers used by the real POS JavaScript, not mock
// copies of their implementation.
function loadHelpers(logs = []) {
  const start = view.indexOf('function validatePosCheckoutConfirmation(');
  const end = view.indexOf('async function submitOrder(action)', start);
  assert.ok(start >= 0 && end > start, 'POS helper functions must be present');
  const snippet = view.slice(start, end);
  const context = {console: {warn: (...args) => logs.push(args)}};
  vm.createContext(context);
  vm.runInContext(snippet, context);
  return context;
}

test('POS-NET-01: successful checkout requires positive persisted order identity', () => {
  const {validatePosCheckoutConfirmation: validate} = loadHelpers();
  assert.doesNotThrow(() => validate({ok:true}, {ok:true,order_id:12}));
  for (const result of [null, {}, {ok:true}, {ok:false,order_id:12},
                        {ok:true,order_id:0}, {ok:true,order_id:'unknown'}]) {
    assert.throws(() => validate({ok:true},result), /لم يصل تأكيد حفظ الطلب/);
  }
});

test('POS-NET-02: 422 validation response is handled by existing error UI, not as ambiguous success', () => {
  const {validatePosCheckoutConfirmation: validate} = loadHelpers();
  assert.doesNotThrow(() => validate({ok:false}, {errors:{items:['Quantity invalid']}}));
});

test('POS-NET-03: failed request keeps the retry key; only confirmed success removes it', () => {
  const submit = view.slice(view.indexOf('async function submitOrder(action)'),
                            view.indexOf('    // -----------------------------\n    // Product listeners', view.indexOf('async function submitOrder(action)')));
  assert.match(submit,/validatePosCheckoutConfirmation\(response, data\)/);
  const verifiedAt = submit.indexOf('validatePosCheckoutConfirmation(response, data)');
  const rotateAt = submit.indexOf('sessionStorage.removeItem(posAttemptKey)');
  assert.ok(verifiedAt >= 0 && rotateAt > verifiedAt);
  assert.match(submit,/catch \(error\) \{[\s\S]*?افحص الطلبات قبل إعادة المحاولة/);
  assert.match(view,/attempt\.fingerprint !== fingerprint/);
});

test('POS-PRINT-01: blocked popup leaves sale intact and reports preview failure', () => {
  const {safePosPrintNavigation: navigate} = loadHelpers();
  assert.equal(navigate(null, '/receipt/123'), false);
  assert.match(view,/تم حفظ الطلب، لكن تعذرت معاينة الطباعة/);
});

test('POS-PRINT-02: valid receipt opens, and missing URL closes only the preview', () => {
  const {safePosPrintNavigation: navigate} = loadHelpers();
  let closed = false;
  const w = {location:{href:''},close(){closed=true;}};
  assert.equal(navigate(w,'/receipts/123'),true);
  assert.equal(w.location.href,'/receipts/123');
  assert.equal(closed,false);
  assert.equal(navigate(w,null),false);
  assert.equal(closed,true);
});

test('POS-PRINT-03: navigation exception does not turn a saved payment into a failed sale', () => {
  const logs = [];
  const {safePosPrintNavigation: navigate} = loadHelpers(logs);
  let closed = false;
  const w = {
    location: {set href(url){throw new Error('Printer navigation blocked')}},
    close(){closed=true}
  };
  assert.equal(navigate(w,'/receipts/123'),false);
  assert.equal(closed,true);
  assert.equal(logs.length,1);
  assert.match(view,/rbCheckoutSucceeded = true/);
});

test('POS-RESPONSIVE-01: POS defines tablet and phone breakpoints', () => {
  assert.match(view,/@media\s*\(max-width:\s*820px\)/);
  assert.match(view,/@media\s*\(max-width:\s*560px\)/);
  assert.match(view,/id="rbBillPaymentBtn"/);
  assert.match(view,/id="rbCheckoutContinueBtn"/);
});

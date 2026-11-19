const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/restaurant/pos/index.blade.php'), 'utf8');

function draftKeyFor(userId, locationId) {
  const snippet = view.match(/const draftKey = (\x60[^\x60]+\x60);/)?.[1];
  assert.ok(snippet, 'draft must have explicit per-cashier key');
  const context = {cashierUserId:userId,locationId};
  vm.createContext(context);
  return vm.runInContext(snippet, context);
}
test('POS-SHIFT-01: drafts on a shared terminal are isolated by cashier and branch', () => {
  const a = draftKeyFor(7, 33);
  assert.equal(a, 'restaurant_pos_draft_v3_7_33');
  assert.notEqual(a, draftKeyFor(8,33));
  assert.notEqual(a, draftKeyFor(7,34));
  assert.match(view, /localStorage\.removeItem\(\x60restaurant_pos_draft_\$\{locationId\}\x60\)/);
});
test('POS-SHIFT-02: old shared branch drafts are never silently adopted by a second cashier', () => {
  assert.doesNotMatch(view, /localStorage\.getItem\(\x60restaurant_pos_draft_\$\{locationId\}\x60\)/);
  assert.match(view, /raw = localStorage\.getItem\(draftKey\)/);
  assert.match(view, /function restoreLocalDraft\(\)/);
  assert.match(view, /loadProductsByIds\(draft\.items\.map/);
});
test('POS-POWER-01: current cashier can save and restore cart after browser reload', () => {
  assert.match(view, /localStorage\.setItem\(draftKey, JSON\.stringify\(serializeDraft\(\)\)\)/);
  assert.match(view, /function restoreLocalDraft\(\)/);
  assert.match(view, /renderLocalDraftCard\(\)/);
  assert.match(view, /sessionStorage\.setItem\(posAttemptKey/);
  assert.match(view, /sessionStorage\.removeItem\(posAttemptKey\)/);
});
test('POS-NET-04: ambiguous fetch failure retains request token, preserves cashier draft and warns to inspect server records', () => {
  const start = view.indexOf('async function submitOrder(action)');
  const end = view.indexOf('    // -----------------------------\n    // Product listeners',start);
  assert.ok(start > -1 && end > start);
  const submit = view.slice(start,end);
  const failed = submit.slice(submit.indexOf('} catch (error) {',submit.indexOf('validatePosCheckoutConfirmation(response, data);')));
  assert.match(failed, /localStorage\.setItem\(draftKey, JSON\.stringify\(serializeDraft\(\)\)\)/);
  assert.match(failed, /افحص الطلبات المسجلة أولًا/);
  assert.doesNotMatch(failed.slice(0,failed.indexOf('} finally {')), /sessionStorage\.removeItem\(posAttemptKey\)/);
});
test('POS-POWER-02: storage exceptions do not show false success for manual save', () => {
  assert.match(view, /تعذر حفظ المسودة على الجهاز/);
  assert.match(view, /try \{ raw = localStorage\.getItem\(draftKey\); \} catch \(_\) \{ return null; \}/);
});

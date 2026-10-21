const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/js/daily-order-form.js'), 'utf8');
function setup({modal = true, valid = true} = {}) {
    let submit, ready, pageshow, confirm;
    let confirmations = 0, submissions = 0;
    const button = {textContent: 'إرسال الطلب', disabled: false};
    const error = {hidden: true, focus() {this.focused = true;}};
    const attributes = {};
    const form = {
        dataset: {},
        querySelectorAll: () => [button], querySelector: () => error,
        addEventListener: (_, handler) => {submit = handler;},
        reportValidity: () => valid,
        setAttribute: (name, value) => {attributes[name] = value;},
        removeAttribute: name => {delete attributes[name];},
        requestSubmit: () => send(),
    };
    function send(prevented = false) {
        const event = {submitter: button, defaultPrevented: prevented, preventDefault() {this.defaultPrevented = true;}};
        submit(event);
        if (!event.defaultPrevented) submissions++;
        return event;
    }
    const context = {
        document: {querySelectorAll: () => [form], getElementById: () => modal ? {} : null, addEventListener: (_, handler) => {ready = handler;}},
        window: {addEventListener: (_, handler) => {pageshow = handler;}, openConfirmModal: options => {confirmations++; confirm = options.onConfirm;}},
    };
    vm.runInNewContext(source, context);
    ready();
    return {send, accept: () => confirm(), restore: () => pageshow(), button, error, attributes,
        counts: () => ({confirmations, submissions})};
}
test('valid order waits for confirmation, then submits once and shows loading', () => {
    const page = setup();
    page.send();
    assert.deepEqual(page.counts(), {confirmations: 1, submissions: 0});
    assert.equal(page.button.disabled, false);
    page.accept();
    page.send();
    page.accept();
    assert.deepEqual(page.counts(), {confirmations: 1, submissions: 1});
    assert.equal(page.button.disabled, true);
    assert.equal(page.attributes['aria-busy'], 'true');
});
test('page validation failure does not open a popup or lock the form', () => {
    const page = setup();
    page.send(true);
    assert.deepEqual(page.counts(), {confirmations: 0, submissions: 0});
    assert.equal(page.button.disabled, false);
});
test('native invalid input does not open confirmation', () => {
    const page = setup({valid: false});
    page.send();
    assert.deepEqual(page.counts(), {confirmations: 0, submissions: 0});
});
test('missing popup fails closed with an inline error', () => {
    const page = setup({modal: false});
    page.send();
    assert.deepEqual(page.counts(), {confirmations: 0, submissions: 0});
    assert.equal(page.error.hidden, false);
    assert.equal(page.error.focused, true);
});
test('cancelled confirmation leaves the form editable; back navigation clears loading', () => {
    const page = setup();
    page.send(); // dismiss without invoking accept
    assert.equal(page.button.disabled, false);
    page.send();
    page.accept();
    page.restore();
    assert.equal(page.button.disabled, false);
    assert.equal(page.button.textContent, 'إرسال الطلب');
    assert.equal(page.attributes['aria-busy'], undefined);
    page.send();
    assert.deepEqual(page.counts(), {confirmations: 3, submissions: 1});
});

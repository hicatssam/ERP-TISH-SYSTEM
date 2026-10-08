const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function screen(name, options = {}) {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/attendance/face-' + name + '.blade.php'), 'utf8');
    const script = source.match(/<script>([\s\S]*?)<\/script>/)[1]
        .replace(/@json\([^\n]+\)/g, '"endpoint"')
        .replace(/\{\{ \(int\) \$location->id \}\}/g, '1');
    const elements = new Map();
    const element = () => ({
        disabled: false, textContent: '', className: '', checked: false, style: {}, children: [],
        classList: { add() {}, remove() {}, toggle() {} },
        events: {}, addEventListener(name, fn) { this.events[name] = fn; },
        replaceChildren() { this.children = []; }, append(...nodes) { this.children.push(...nodes); },
        videoWidth: 640, videoHeight: 480, readyState: 2,
        async play() {}, getContext() { return { drawImage() {} }; },
        toDataURL() { return 'data:image/jpeg;base64,photo'; },
    });
    const get = id => {
        if (!elements.has(id)) elements.set(id, element());
        return elements.get(id);
    };
    let stopped = 0;
    const requests = [];
    const context = {
        document: { getElementById: get, createElement: element, addEventListener(event, fn) { fn(); } },
        window: { isSecureContext: true, addEventListener() {}, location: { reload() {} } },
        navigator: { mediaDevices: { async getUserMedia() {
            if (options.cameraError) throw Object.assign(new Error('camera error'), { name: options.cameraError });
            return { getTracks: () => [{ stop() { stopped++; } }] };
        } } },
        async fetch(url, request) {
            requests.push(request);
            if (request.body && options.punchError) return { ok: false, async json() { return { errors: { liveness: [options.punchError] } }; } };
            return { ok: true, async json() { return request.body
                ? { ok: true, action: 'check_in', message: 'تم التسجيل', employee: { name: 'موظف' }, attendance: { check_in_at: '09:00' } }
                : { ok: true, token: 'challenge' }; } };
        },
        Intl, setInterval() {}, setTimeout(fn) { options.reload = fn; },
    };
    vm.runInNewContext(script, context);
    return { get, requests, stopped: () => stopped };
}

test('kiosk completes one punch, retains success, and starts another employee without submitting twice', async () => {
    const ui = screen('kiosk');
    const action = ui.get('kioskAction');
    await action.events.click();
    assert.equal(action.textContent, 'التقاط اللقطة الأمامية');
    await action.events.click();
    await action.events.click();
    assert.equal(ui.requests.length, 2);
    assert.equal(ui.stopped(), 1);
    assert.match(ui.get('kioskResult').innerHTML, /تم تسجيل الحضور/);
    assert.equal(action.textContent, 'تسجيل موظف آخر');
    await action.events.click();
    assert.equal(ui.requests.length, 2);
    assert.equal(action.textContent, 'بدء التحقق');
    assert.equal(ui.get('kioskResult').className, 'kiosk-result');
});

test('camera permission failure explains recovery and does not request a server challenge', async () => {
    const ui = screen('kiosk', { cameraError: 'NotAllowedError' });
    await ui.get('kioskAction').events.click();
    assert.equal(ui.requests.length, 0);
    assert.match(ui.get('kioskResult').innerHTML, /اسمح باستخدام الكاميرا/);
    assert.equal(ui.get('kioskAction').disabled, false);
});

test('enrollment requires three photos and consent, shows previews, and clears them on reset', async () => {
    const ui = screen('enroll');
    await ui.get('startCameraButton').events.click();
    for (let i = 0; i < 3; i++) ui.get('captureButton').events.click();
    assert.equal(ui.get('faceThumbnails').children.length, 3);
    assert.equal(ui.get('saveFaceButton').disabled, true);
    ui.get('faceConsent').checked = true;
    ui.get('faceConsent').events.change();
    assert.equal(ui.get('saveFaceButton').disabled, false);
    ui.get('resetCaptureButton').events.click();
    assert.equal(ui.get('faceThumbnails').children.length, 0);
    assert.equal(ui.get('saveFaceButton').disabled, true);
    assert.equal(ui.stopped(), 1);
});

test('kiosk refuses an empty camera frame before sending a punch', async () => {
    const ui = screen('kiosk');
    await ui.get('kioskAction').events.click();
    ui.get('kioskVideo').videoWidth = 0;
    await ui.get('kioskAction').events.click();
    assert.equal(ui.requests.length, 1);
    assert.match(ui.get('kioskResult').innerHTML, /انتظر حتى تظهر صورة الكاميرا/);
});

test('rejected movement keeps the server reason visible and clears the used challenge before retry', async () => {
    const ui = screen('kiosk', { punchError: 'لم يتم اكتشاف حركة رأس كافية' });
    const action = ui.get('kioskAction');
    await action.events.click();
    await action.events.click();
    await action.events.click();
    assert.match(ui.get('kioskResult').innerHTML, /لم يتم اكتشاف حركة رأس كافية/);
    assert.equal(ui.get('stepTitle').textContent, 'لم يتم تسجيل الدوام');
    assert.equal(action.textContent, 'إعادة بدء التحقق');
    assert.equal(ui.stopped(), 1);
    await action.events.click();
    assert.equal(ui.requests.length, 3);
    assert.equal(action.textContent, 'التقاط اللقطة الأمامية');
});

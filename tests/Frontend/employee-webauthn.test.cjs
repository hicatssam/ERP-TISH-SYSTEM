const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/employee-webauthn.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function surface({secure = true, available = true, credentialError = null} = {}) {
    const elements = Object.fromEntries(['myWebauthnRegister', 'myWebauthnPunch', 'myWebauthnStatus', 'myWebauthnLabel'].map(id => [id, {
        disabled: true, value: id === 'myWebauthnLabel' ? 'My phone' : '', textContent: '', handlers: {},
        addEventListener(event, handler) { this.handlers[event] = handler; },
    }]));
    elements.myWebauthn = {dataset: {registerOptions: '/register/options', register: '/register', punchOptions: '/punch/options', punch: '/punch'}};
    const requests = [], prompts = [], timers = [];
    const bytes = new Uint8Array([0, 128, 255]).buffer;
    const navigator = {credentials: {
        async create(input) {
            prompts.push({type: 'create', input});
            if (credentialError) throw credentialError;
            return {type: 'public-key', rawId: bytes, response: {clientDataJSON: bytes, attestationObject: bytes}};
        },
        async get(input) {
            prompts.push({type: 'get', input});
            if (credentialError) throw credentialError;
            return {type: 'public-key', rawId: bytes, response: {clientDataJSON: bytes, authenticatorData: bytes, signature: bytes, userHandle: null}};
        },
    }};
    const key = {challenge: 'AID_', user: {id: 'AID_'}, excludeCredentials: [{id: 'AID_', type: 'public-key'}], allowCredentials: [{id: 'AID_', type: 'public-key'}]};
    const context = {
        window: {isSecureContext: secure, PublicKeyCredential: {}, setTimeout: callback => timers.push(callback), location: {reload() {}}},
        PublicKeyCredential: {async isUserVerifyingPlatformAuthenticatorAvailable() { return available; }},
        document: {getElementById: id => elements[id], querySelector: () => ({content: 'csrf-test'})},
        navigator, Uint8Array, atob, btoa, setTimeout,
        async fetch(url, options) {
            requests.push({url, options, body: JSON.parse(options.body)});
            return {ok: true, async json() { return url.endsWith('/options') ? {token: 'test-token', publicKey: key} : {message: 'Saved'}; }};
        },
    };
    vm.runInNewContext(source, context);
    return {elements, requests, prompts, timers, context};
}

test('unsupported or insecure devices leave verification disabled', async () => {
    for (const config of [{secure: false}, {available: false}]) {
        const ui = surface(config);
        await tick();
        assert.equal(ui.elements.myWebauthnRegister.disabled, true);
        assert.equal(ui.elements.myWebauthnPunch.disabled, true);
        assert.equal(ui.requests.length, 0);
    }
});

test('registration converts binary options and sends attestation with same token and CSRF', async () => {
    const ui = surface();
    await tick();
    await ui.elements.myWebauthnRegister.handlers.click();
    assert.equal(ui.prompts[0].type, 'create');
    assert.deepEqual(Array.from(ui.prompts[0].input.publicKey.challenge), [0, 128, 255]);
    assert.deepEqual(Array.from(ui.prompts[0].input.publicKey.user.id), [0, 128, 255]);
    assert.deepEqual(Array.from(ui.prompts[0].input.publicKey.excludeCredentials[0].id), [0, 128, 255]);
    assert.deepEqual(ui.requests[0].body, {label: 'My phone'});
    assert.equal(ui.requests[1].body.token, 'test-token');
    assert.equal(ui.requests[1].body.response.attestationObject, 'AID_');
    assert.equal(ui.requests[1].options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.equal(ui.elements.myWebauthnRegister.disabled, true);
    assert.equal(ui.elements.myWebauthnStatus.textContent, 'Saved');
});

test('attendance serializes signed assertion and nullable user handle', async () => {
    const ui = surface();
    await tick();
    await ui.elements.myWebauthnPunch.handlers.click();
    assert.equal(ui.prompts[0].type, 'get');
    assert.deepEqual(Array.from(ui.prompts[0].input.publicKey.allowCredentials[0].id), [0, 128, 255]);
    assert.equal(ui.requests[1].url, '/punch');
    assert.equal(ui.requests[1].body.response.signature, 'AID_');
    assert.equal(ui.requests[1].body.response.userHandle, null);
    assert.equal('attestationObject' in ui.requests[1].body.response, false);
});

test('canceled device prompt sends no verification and permits retry', async () => {
    const ui = surface({credentialError: {name: 'NotAllowedError'}});
    await tick();
    await ui.elements.myWebauthnPunch.handlers.click();
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.elements.myWebauthnPunch.disabled, false);
    assert.match(ui.elements.myWebauthnStatus.textContent, /إلغاء/);
});

test('double clicking starts only one ceremony', async () => {
    const ui = surface();
    await tick();
    const first = ui.elements.myWebauthnPunch.handlers.click();
    const second = ui.elements.myWebauthnRegister.handlers.click();
    await Promise.all([first, second]);
    assert.equal(ui.prompts.length, 1);
    assert.equal(ui.requests.length, 2);
});

test('missing label avoids starting a registration request', async () => {
    const ui = surface();
    await tick();
    ui.elements.myWebauthnLabel.value = '  ';
    await ui.elements.myWebauthnRegister.handlers.click();
    assert.equal(ui.requests.length, 0);
    assert.match(ui.elements.myWebauthnStatus.textContent, /اسم/);
});

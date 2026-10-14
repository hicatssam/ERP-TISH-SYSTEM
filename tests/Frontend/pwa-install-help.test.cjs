const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const layout = fs.readFileSync(
    path.join(__dirname, '../../resources/views/layouts/app.blade.php'),
    'utf8'
);
const scripts = Array.from(
    layout.matchAll(/<script>([\s\S]*?)<\/script>/g),
    match => match[1]
);
const source = scripts
    .find(script => script.includes("const installButton = document.getElementById('installAppButton')"))
    .replace("@json(asset('sw.js'))", "'/sw.js'");
const earlySource = scripts
    .find(script => script.includes("window.dahabPwaInstall = window.dahabPwaInstall ||"));

function installEvent(outcome = 'accepted') {
    let prompted = 0;

    return {
        get prompted() { return prompted; },
        preventDefault() {},
        prompt() { prompted++; return Promise.resolve({outcome}); },
        userChoice: Promise.resolve({outcome}),
    };
}

function surface({
    secure = true,
    hostname = 'dahabsweets.systems',
    ua = 'Android Chrome/140',
    standalone = false,
    savedPrompt = null,
} = {}) {
    const elements = {};

    for (const id of [
        'installAppButton', 'pwaInstallHelp', 'pwaInstallHelpTitle',
        'pwaInstallHelpMessage', 'pwaInstallHelpClose', 'pwaInstallHelpHint',
    ]) {
        elements[id] = {
            hidden: true, textContent: '', handlers: {}, open: false,
            disabled: false, dataset: {},
            addEventListener(type, fn) { this.handlers[type] = fn; },
            setAttribute(name, value) { this[name] = value; },
            showModal() { this.open = true; },
            close() { this.open = false; },
        };
    }

    const listeners = {}, navigator = {userAgent: ua};
    const state = {prompt: savedPrompt, installed: false};
    const window = {
        dahabPwaInstall: state,
        isSecureContext: secure,
        navigator,
        matchMedia: () => ({matches: standalone}),
        addEventListener: (type, fn) => { listeners[type] = fn; },
    };

    vm.runInNewContext(source, {
        document: {getElementById: id => elements[id]},
        navigator, location: {hostname}, console, window,
    });

    return {elements, listeners, state};
}

test('Android button waits for a real native prompt rather than opening settings instructions', async () => {
    const {elements} = surface();

    assert.equal(elements.installAppButton.hidden, false);
    assert.equal(elements.installAppButton.disabled, true);
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelp.open, false);
});

test('Android remote HTTP cannot install but never redirects users to settings', async () => {
    const {elements} = surface({secure: false, hostname: '192.168.1.5'});

    assert.equal(elements.installAppButton.disabled, true);
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelp.open, false);
});

test('iOS keeps Safari home-screen instructions (native install prompt unavailable)', async () => {
    const {elements} = surface({ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)'});

    assert.equal(elements.installAppButton.hidden, false);
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelp.open, true);
    assert.match(elements.pwaInstallHelpTitle.textContent, /iPhone/);
});

test('Android install event is prompted on first tap and accepted install hides button', async () => {
    const {elements, listeners} = surface();
    const event = installEvent();

    listeners.beforeinstallprompt(event);
    assert.equal(elements.installAppButton.disabled, false);
    await elements.installAppButton.handlers.click();

    assert.equal(event.prompted, 1);
    assert.equal(elements.installAppButton.hidden, true);
    assert.equal(elements.pwaInstallHelp.open, false);
});

test('dismissed Android prompt is not reused and no browser instructions are shown', async () => {
    const {elements, listeners} = surface();
    const event = installEvent('dismissed');

    listeners.beforeinstallprompt(event);
    await elements.installAppButton.handlers.click();
    await elements.installAppButton.handlers.click();

    assert.equal(event.prompted, 1);
    assert.equal(elements.installAppButton.disabled, true);
    assert.equal(elements.pwaInstallHelp.open, false);
});

test('install prompt captured before toolbar initialization opens directly on tap', async () => {
    const event = installEvent();
    const {elements} = surface({savedPrompt: event});

    assert.equal(elements.installAppButton.disabled, false);
    await elements.installAppButton.handlers.click();

    assert.equal(event.prompted, 1);
    assert.equal(elements.installAppButton.hidden, true);
});

test('installed app hides its install button even when browser exposes an old prompt', () => {
    const {elements} = surface({standalone: true, savedPrompt: installEvent()});

    assert.equal(elements.installAppButton.hidden, true);
});

test('early capture script retains the native event before the toolbar script mounts', () => {
    const listeners = {}, dispatched = [];
    const win = {
        addEventListener: (type, handler) => { listeners[type] = handler; },
        dispatchEvent: event => { dispatched.push(event.type); },
    };
    vm.runInNewContext(earlySource, {
        window: win,
        Event: class { constructor(type) { this.type = type; } },
    });

    const event = installEvent();
    listeners.beforeinstallprompt(event);

    assert.equal(win.dahabPwaInstall.prompt, event);
    assert.deepEqual(dispatched, ['dahab:pwa-install-ready']);
});

test('hidden install button cannot be displayed by its base flex class', () => {
    const css = layout;
    assert.match(css, /\.install-app-btn\[hidden\]\s*\{\s*display:\s*none\s*!important/);
});

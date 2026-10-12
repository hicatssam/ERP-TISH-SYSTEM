const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const layout = fs.readFileSync(path.join(__dirname, '../../resources/views/layouts/app.blade.php'), 'utf8');
const scripts = Array.from(layout.matchAll(/<script>([\s\S]*?)<\/script>/g), match => match[1]);
const source = scripts.find(script => script.includes("const installButton = document.getElementById('installAppButton')"))
    .replace("@json(asset('sw.js'))", "'/sw.js'");

function surface({secure = true, hostname = 'dahabsweets.systems', ua = 'Android Chrome/140', standalone = false} = {}) {
    const elements = {};
    for (const id of ['installAppButton', 'pwaInstallHelp', 'pwaInstallHelpTitle', 'pwaInstallHelpMessage', 'pwaInstallHelpClose', 'pwaInstallHelpHint']) {
        elements[id] = {hidden: true, textContent: '', handlers: {}, open: false,
            addEventListener(type, fn) { this.handlers[type] = fn; },
            showModal() { this.open = true; }, close() { this.open = false; }};
    }
    const listeners = {}, navigator = {userAgent: ua};
    vm.runInNewContext(source, {
        document: {getElementById: id => elements[id]}, navigator, location: {hostname}, console,
        window: {isSecureContext: secure, navigator, matchMedia: () => ({matches: standalone}),
            addEventListener: (type, fn) => { listeners[type] = fn; }},
    });
    return {elements, listeners};
}

test('HTTPS Android help does not display an insecure connection warning', async () => {
    const {elements} = surface();
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelp.open, true);
    assert.equal(elements.pwaInstallHelpHint.hidden, true);
    assert.match(elements.pwaInstallHelpTitle.textContent, /Android/);
});

test('insecure remote HTTP shows the warning', async () => {
    const {elements} = surface({secure: false, hostname: '192.168.1.5'});
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelpHint.hidden, false);
    assert.match(elements.pwaInstallHelpTitle.textContent, /رابط آمن/);
});

test('localhost does not show the network HTTP warning', async () => {
    const {elements} = surface({secure: false, hostname: 'localhost'});
    await elements.installAppButton.handlers.click();
    assert.equal(elements.pwaInstallHelpHint.hidden, true);
});

test('Samsung Internet receives instructions for its own browser', async () => {
    const {elements} = surface({ua: 'Android SamsungBrowser/28.0 Chrome/130'});
    await elements.installAppButton.handlers.click();
    assert.match(elements.pwaInstallHelpTitle.textContent, /Samsung Internet/);
    assert.equal(elements.pwaInstallHelpHint.hidden, true);
});

test('a browser install event opens the native prompt and hides the button after acceptance', async () => {
    const {elements, listeners} = surface();
    let prompted = 0;
    listeners.beforeinstallprompt({preventDefault() {}, async prompt() { prompted++; }, userChoice: Promise.resolve({outcome: 'accepted'})});
    await elements.installAppButton.handlers.click();
    assert.equal(prompted, 1);
    assert.equal(elements.installAppButton.hidden, true);
    assert.equal(elements.pwaInstallHelp.open, false);
});

test('installed app hides the install button', () => {
    const {elements} = surface({standalone: true});
    assert.equal(elements.installAppButton.hidden, true);
});

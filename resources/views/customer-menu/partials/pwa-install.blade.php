{{-- Installation is native where the browser exposes a prompt, manual otherwise. --}}
<button type="button" class="pwa-install-btn" id="pwaInstallBtn" hidden>
    <i class="fa-solid fa-arrow-down-to-bracket" aria-hidden="true"></i>
    <span>ثبّت التطبيق</span>
</button>

<div class="pwa-ios-sheet" id="pwaIosSheet" role="dialog" aria-modal="true" aria-labelledby="pwaInstallTitle" hidden>
    <div class="pwa-ios-card">
        <button type="button" class="pwa-ios-close" id="pwaIosClose" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
        <h3 id="pwaInstallTitle">تثبيت تطبيق المنيو</h3>
        <ol id="pwaInstallSteps"></ol>
    </div>
</div>

<script>
(function () {
    'use strict';

    const btn = document.getElementById('pwaInstallBtn');
    const sheet = document.getElementById('pwaIosSheet');
    const close = document.getElementById('pwaIosClose');
    const title = document.getElementById('pwaInstallTitle');
    const steps = document.getElementById('pwaInstallSteps');
    if ('serviceWorker' in navigator && window.isSecureContext !== false) {
        navigator.serviceWorker.register('/sw.js', {updateViaCache: 'none'})
            .then(function (registration) { return registration.update(); })
            .catch(function () { /* Install help remains accessible if registration fails. */ });
    }


    if (!btn || !sheet || !close || !title || !steps) return;

    const state = window.dahabCustomerPwa || (window.dahabCustomerPwa = {prompt: null, installed: false});
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const ua = navigator.userAgent || '';
    const isIos = /iPad|iPhone|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /Android/i.test(ua);
    const isMac = /Macintosh|Mac OS X/i.test(ua) && !isIos;
    const isFirefox = /Firefox|FxiOS/i.test(ua);
    const isSafari = /Safari/i.test(ua) && !/Chrome|CriOS|Edg|OPR|FxiOS/i.test(ua);
    const isSamsung = /SamsungBrowser/i.test(ua);

    function syncButton() {
        btn.hidden = standalone() || state.installed;
    }

    function openHelp() {
        let heading = 'تثبيت تطبيق المنيو';
        let instructions;

        if (!window.isSecureContext) {
            instructions = ['افتح المنيو عبر اتصال HTTPS آمن، ثم استخدم خيار تثبيت التطبيق من المتصفح.'];
        } else if (isIos) {
            heading = 'إضافة المنيو للشاشة الرئيسية';
            instructions = [
                'افتح قائمة المشاركة في المتصفح (رمز المشاركة أو قائمة الخيارات).',
                'اختر «إضافة إلى الشاشة الرئيسية» (Add to Home Screen).',
                'اضغط «إضافة» لتظهر أيقونة المنيو على جهازك.'
            ];
        } else if (isSamsung) {
            instructions = ['افتح قائمة متصفح Samsung Internet.', 'اختر «إضافة الصفحة إلى» ثم «الشاشة الرئيسية» أو «تثبيت التطبيق» إذا كان متاحًا.'];
        } else if (isAndroid) {
            instructions = ['افتح قائمة المتصفح (⋮).', 'اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية» حسب المتصفح.'];
        } else if (isMac && isSafari) {
            instructions = ['من قائمة File في Safari اختر Add to Dock (على الإصدارات التي تدعمها).', 'أكّد إضافة التطبيق إلى Dock.'];
        } else if (isFirefox) {
            instructions = ['قد لا يدعم متصفحك تثبيت الموقع كتطبيق مستقل على الكمبيوتر.', 'يمكنك حفظ المنيو كاختصار أو فتحه في Chrome أو Edge لتجربة خيار التثبيت.'];
        } else {
            instructions = ['افتح قائمة المتصفح (⋮ أو …) أو خيار التثبيت بجوار شريط العنوان.', 'اختر «تثبيت التطبيق» أو «Create shortcut» بحسب المتصفح.'];
        }

        title.textContent = heading;
        steps.replaceChildren();
        instructions.forEach(function (instruction) {
            const item = document.createElement('li');
            item.textContent = instruction;
            steps.appendChild(item);
        });
        sheet.hidden = false;
        sheet.classList.add('open');
        close.focus();
    }

    function closeHelp() {
        sheet.classList.remove('open');
        sheet.hidden = true;
        btn.focus();
    }

    syncButton();
    window.addEventListener('dahab:customer-pwa-ready', syncButton);
    window.addEventListener('dahab:customer-pwa-installed', syncButton);
    window.addEventListener('appinstalled', function () { state.installed = true; state.prompt = null; syncButton(); });
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        state.prompt = event;
        syncButton();
    });

    btn.addEventListener('click', async function () {
        const prompt = state.prompt;
        if (prompt) {
            state.prompt = null;
            try {
                await prompt.prompt();
                const result = await prompt.userChoice;
                if (result && result.outcome === 'accepted') {
                    syncButton();
                    return;
                }
            } catch (error) {
                // Fall back to browser-specific instructions.
            }
        }
        openHelp();
    });

    close.addEventListener('click', closeHelp);
    sheet.addEventListener('click', function (event) { if (event.target === sheet) closeHelp(); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !sheet.hidden) closeHelp();
    });
})();
</script>

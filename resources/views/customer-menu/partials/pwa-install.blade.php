{{-- Installation is native where the browser exposes a prompt, manual otherwise. --}}
<style>
/* Reference artwork cropped from the supplied TISH design; install behaviour is unchanged. */
html body:is(#tishCart,.tish-paper,:not(.tish-paper)) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){
 position:fixed!important;inset:auto!important;left:max(8px,calc((100vw - 980px)/2 + 8px))!important;
 bottom:calc(16px + env(safe-area-inset-bottom,0px))!important;z-index:48!important;
 display:grid!important;place-items:center!important;box-sizing:border-box!important;
 width:82px!important;height:82px!important;min-width:82px!important;min-height:82px!important;max-width:82px!important;
 padding:0!important;margin:0!important;border:0!important;border-radius:50%!important;
 background:none!important;box-shadow:none!important;clip-path:none!important;overflow:visible!important;
 filter:drop-shadow(0 4px 3px #4e281b60)!important;cursor:pointer;
}
html body:is(#tishCart,.tish-paper,:not(.tish-paper)):has(.bottom-nav) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){
 bottom:calc(92px + env(safe-area-inset-bottom,0px))!important;
}
html body:is(#tishCart,.tish-paper,:not(.tish-paper)):has(.cm-sticky-cart:not([hidden])) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){
 bottom:calc(174px + env(safe-area-inset-bottom,0px))!important;
}
html body:is(#tishCart,.tish-paper,:not(.tish-paper)) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin::before,
html body:is(#tishCart,.tish-paper,:not(.tish-paper)) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin::after{content:none!important;display:none!important}
html body:is(#tishCart,.tish-paper,:not(.tish-paper)) #pwaInstallBtn.tish-reference-pin .tish-reference-pin-art{
 display:block!important;width:100%!important;height:100%!important;max-width:none!important;object-fit:contain!important;
 pointer-events:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;
}
html body #pwaInstallBtn.tish-reference-pin[hidden],
html.cm-intro-open body #pwaInstallBtn.tish-reference-pin{display:none!important}
html body #pwaInstallBtn.tish-reference-pin:focus-visible{outline:2px solid #a91f17!important;outline-offset:4px!important}
@media(max-width:365px){
 html body:is(#tishCart,.tish-paper,:not(.tish-paper)) #pwaInstallBtn.tish-paper-pin-control.tish-reference-pin:not([hidden]){
 width:74px!important;height:74px!important;min-width:74px!important;min-height:74px!important;max-width:74px!important;
 }
}
@media print{html body #pwaInstallBtn.tish-reference-pin{display:none!important}}
</style>
<button type="button" class="pwa-install-btn tish-paper-pin-control tish-reference-pin" id="pwaInstallBtn" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Install menu' : 'ثبّت المنيو' }}" title="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Install menu' : 'ثبّت المنيو' }}" hidden>
    <img class="tish-reference-pin-art" src="{{ asset('assets/tish-paper/decor/install-pin-reference.webp') }}?v=1" width="128" height="128" alt="" aria-hidden="true">
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
        const fold = btn.getAnimations?.().find(animation => animation.animationName === 'tish-paper-complete-fold');
        if (fold) await fold.finished.catch(() => {});
        openHelp();
    });

    close.addEventListener('click', closeHelp);
    sheet.addEventListener('click', function (event) { if (event.target === sheet) closeHelp(); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !sheet.hidden) closeHelp();
    });
})();
</script>

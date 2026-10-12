/* WebAuthn platform verification for attendance; no biometric data leaves the device. */
(() => {
    'use strict';
    const root = document.getElementById('myWebauthn');
    if (!root) return;
    const register = document.getElementById('myWebauthnRegister');
    const punch = document.getElementById('myWebauthnPunch');
    const status = document.getElementById('myWebauthnStatus');
    if (!register || !punch || !status) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4)), c => c.charCodeAt(0));
    const encode = value => {
        let binary = '';
        for (const byte of new Uint8Array(value)) binary += String.fromCharCode(byte);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };
    const options = (publicKey, registration) => {
        const result = {...publicKey, challenge: decode(publicKey.challenge)};
        if (registration) result.user = {...publicKey.user, id: decode(publicKey.user.id)};
        for (const field of ['excludeCredentials', 'allowCredentials']) {
            if (publicKey[field]) result[field] = publicKey[field].map(item => ({...item, id: decode(item.id)}));
        }
        return result;
    };
    async function post(url, payload = {}) {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = Object.values(data.errors || {}).flat()[0];
            throw new Error(firstError || data.message || 'تعذر إتمام العملية. حدّث الصفحة وأعد المحاولة.');
        }
        return data;
    }
    let busy = false;
    async function ceremony(registration) {
        if (busy) return;
        const label = document.getElementById('myWebauthnLabel').value.trim();
        if (registration && !label) { status.textContent = 'اكتب اسمًا للمفتاح أولًا.'; return; }
        busy = true;
        register.disabled = punch.disabled = true;
        status.textContent = 'انتظر ظهور نافذة التحقق الخاصة بالجوال…';
        let completed = false;
        try {
            const start = await post(registration ? root.dataset.registerOptions : root.dataset.punchOptions, registration ? {label} : {});
            const credential = registration
                ? await navigator.credentials.create({publicKey: options(start.publicKey, true)})
                : await navigator.credentials.get({publicKey: options(start.publicKey, false)});
            if (!credential) throw new Error('لم يتم إكمال التحقق.');
            const response = {clientDataJSON: encode(credential.response.clientDataJSON)};
            if (registration) response.attestationObject = encode(credential.response.attestationObject);
            else {
                response.authenticatorData = encode(credential.response.authenticatorData);
                response.signature = encode(credential.response.signature);
                response.userHandle = credential.response.userHandle ? encode(credential.response.userHandle) : null;
            }
            const result = await post(registration ? root.dataset.register : root.dataset.punch, {
                token: start.token, id: encode(credential.rawId), type: credential.type, response,
            });
            status.textContent = result.message;
            completed = true;
            window.setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            const messages = {
                NotAllowedError: 'تم إلغاء التحقق أو انتهت مهلته. حاول مجددًا من جوالك.',
                InvalidStateError: 'هذا المفتاح مسجّل بالفعل. استخدمه أو اطلب إلغاءه من المسؤول.',
                NotSupportedError: 'الجهاز أو المتصفح لا يدعم هذا النوع من المفاتيح.',
                SecurityError: 'افتح النظام من رابط HTTPS المعتمد مباشرة.',
            };
            status.textContent = messages[error.name] || error.message || 'تعذر التحقق.';
        } finally {
            busy = false;
            register.disabled = punch.disabled = completed;
        }
    }
    register.addEventListener('click', () => ceremony(true));
    punch.addEventListener('click', () => ceremony(false));
    async function init() {
        if (!window.isSecureContext || !window.PublicKeyCredential || !navigator.credentials || !csrf) {
            status.textContent = 'استخدم متصفحًا يدعم WebAuthn وافتح رابط النظام الآمن HTTPS.';
            return;
        }
        try {
            if (!await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()) {
                status.textContent = 'فعّل قفل الشاشة على جوالك أو استخدم جهازًا يدعم التحقق المحلي.';
                return;
            }
            register.disabled = punch.disabled = false;
            status.textContent = 'جاهز. التسجيل الجديد يحتاج اعتماد مسؤول الحضور.';
        } catch {
            status.textContent = 'تعذر فحص دعم الجهاز. جرّب متصفحًا آخر على جوالك.';
        }
    }
    init();
})();

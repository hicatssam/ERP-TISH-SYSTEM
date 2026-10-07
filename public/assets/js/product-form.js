(() => {
    'use strict';

    function imageError(file) {
        if (!file) return '';
        if (file.size > 2 * 1024 * 1024) {
            return 'حجم صورة المنتج يجب ألا يتجاوز 2 ميجابايت. اختر صورة أصغر.';
        }
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const allowedName = /\.(jpe?g|png|webp)$/i.test(file.name || '');
        if ((file.type && !allowedTypes.includes(file.type)) || (!file.type && !allowedName)) {
            return 'صيغة الصورة يجب أن تكون JPEG أو JPG أو PNG أو WebP.';
        }
        return '';
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.product-form-shell').forEach(shell => {
            const form = shell.closest('form');
            const input = shell.querySelector('[data-product-image]');
            const error = shell.querySelector('[data-product-image-error]');
            if (!form || !input || !error) return;

            function validateImage() {
                const message = imageError(input.files?.[0]);
                input.setCustomValidity(message);
                input.setAttribute('aria-invalid', message ? 'true' : 'false');
                error.textContent = message;
                error.hidden = !message;
                return !message;
            }

            input.addEventListener('change', validateImage);
            form.addEventListener('submit', event => {
                if (!validateImage()) {
                    event.preventDefault();
                    error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    input.focus();
                }
            });
            input.addEventListener('invalid', () => {
                if (!error.hidden) error.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            const summary = form.querySelector('[data-product-errors]');
            if (summary) {
                // Run after the Products page has reopened its creation modal.
                requestAnimationFrame(() => {
                    summary.scrollIntoView({ block: 'center' });
                    summary.focus({ preventScroll: true });
                });
            }
        });
    });
})();

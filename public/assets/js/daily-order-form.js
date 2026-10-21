/* Daily orders: validate first, confirm second, lock only the actual submission. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-daily-order-form]').forEach(form => {
        if (form.dataset.dailyOrderBound === '1') return;
        form.dataset.dailyOrderBound = '1';
        form.dataset.confirmBypass = '1';
        let confirmed = false;
        let pending = false;
        const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
        const original = buttons.map(button => ({button, text: button.textContent, disabled: button.disabled}));
        const error = form.querySelector('[data-order-error]');

        form.addEventListener('submit', event => {
            if (pending) {
                event.preventDefault();
                return;
            }
            // Earlier page validators own their inline errors. Never confirm invalid data.
            if (event.defaultPrevented || !form.reportValidity()) {
                confirmed = false;
                event.preventDefault();
                return;
            }
            error.hidden = true;
            if (!confirmed) {
                event.preventDefault();
                if (typeof window.openConfirmModal !== 'function' || !document.getElementById('confirmModal')) {
                    error.textContent = 'تعذر فتح نافذة التأكيد. حدّث الصفحة ثم حاول مجددًا.';
                    error.hidden = false;
                    error.focus();
                    return;
                }
                window.openConfirmModal({
                    title: 'تأكيد إرسال الطلب اليومي',
                    body: 'سيُرسل الطلب إلى المصنع. تُراجع المواد الخام المرفقة وتُصرف بشكل مستقل عن الحلويات والكيك.',
                    type: 'warning',
                    confirmText: 'تأكيد الإرسال',
                    trigger: event.submitter,
                    form,
                    onConfirm: () => {
                        confirmed = true;
                        form.requestSubmit(event.submitter || undefined);
                    }
                });
                // Dialog cancellation closes the modal without submitting the form.
                return;
            }
            confirmed = false;
            pending = true;
            form.setAttribute('aria-busy', 'true');
            buttons.forEach(button => {
                button.disabled = true;
                button.textContent = 'جارٍ حفظ وإرسال الطلب…';
            });
            // Success is rendered only by the server after its transaction commits.
        });

        window.addEventListener('pageshow', () => {
            pending = false;
            confirmed = false;
            form.removeAttribute('aria-busy');
            original.forEach(({button, text, disabled}) => {
                button.textContent = text;
                button.disabled = disabled;
            });
        });
    });
});

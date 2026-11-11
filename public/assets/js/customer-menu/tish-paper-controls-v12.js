/* TISH paper controls v12 — visual motion only.
   The actual PWA installation and cart behavior belong to their original code. */
(function (window, document) {
    'use strict';
    const timers = new WeakMap();
    function reducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }
    function press(el, className, duration) {
        if (!el || reducedMotion()) return;
        const previous = timers.get(el);
        if (previous) window.clearTimeout(previous);
        el.classList.remove(className);
        // Restart only upon a user gesture or an actual cart-changed event.
        void el.offsetWidth;
        el.classList.add(className);
        timers.set(el, window.setTimeout(function () {
            el.classList.remove(className);
            timers.delete(el);
        }, duration));
    }

    document.addEventListener('pointerdown', function (event) {
        const quantity = event.target.closest('.tish-cart-page #cartItems .row-actions button');
        if (quantity) press(quantity, 'paper-cart-qty-press', 380);
    });
    document.addEventListener('click', function (event) {
        const pin = event.target.closest('#pwaInstallBtn.tish-paper-pin-control');
        if (pin && !pin.hidden) press(pin, 'paper-pin-fold', 800);
    }, true);
    // The cart engine dispatches this ONLY after it has persisted its cart.
    document.addEventListener('customer-menu:cart-changed', function () {
        press(document.querySelector('.bottom-nav .tish-paper-cart-nav'), 'paper-cart-icon-pop', 400);
        press(document.getElementById('cmStickyCart'), 'paper-cart-icon-pop', 400);
    });
})(window, document);
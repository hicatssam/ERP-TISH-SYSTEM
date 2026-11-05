/* Progressive image placeholders and scroll reveals for Tish's public catalog.
 * All animations are progressive: content remains visible without JavaScript. */
(function () {
    'use strict';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const canReveal = !reduced.matches && 'IntersectionObserver' in window;
    let revealObserver = null;
    let imageObserver = null;
    const seen = new WeakSet();

    function reveal(card) {
        card.classList.remove('tish-reveal-pending');
        card.classList.add('tish-reveal-visible');
        revealObserver?.unobserve(card);
    }

    if (canReveal) {
        revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) reveal(entry.target); });
        }, { threshold: .06, rootMargin: '0px 0px 85px 0px' });
    }

    function finalizeImage(img, frame) {
        frame.classList.remove('tish-image-loading');
        frame.classList.add('tish-image-settled');
        if (img.complete && img.naturalWidth === 0) frame.classList.add('tish-image-error');
    }

    function prepareImage(img, frame) {
        if (img.dataset.tishImageBound) return;
        img.dataset.tishImageBound = '1';
        img.decoding = 'async';
        if (!img.hasAttribute('loading')) img.loading = 'lazy';
        if (img.complete) {
            finalizeImage(img, frame);
            return;
        }
        frame.classList.add('tish-image-loading');
        img.addEventListener('load', () => finalizeImage(img, frame), { once: true });
        img.addEventListener('error', () => finalizeImage(img, frame), { once: true });
        // Native lazy-loading handles viewport prioritization; don't assign src anew.
    }

    function prepareCard(card, index) {
        if (seen.has(card)) return;
        seen.add(card);
        const frame = card.querySelector('.product-media, .tish-addon-photo');
        const img = frame?.querySelector('img');
        if (img) prepareImage(img, frame);
        if (!canReveal) return;
        card.style.setProperty('--tish-reveal-delay', Math.min(index % 5, 4) * 65 + 'ms');
        const bounds = card.getBoundingClientRect();
        if (bounds.top < window.innerHeight + 30 && bounds.bottom > -30) {
            card.classList.add('tish-reveal-visible');
        } else {
            card.classList.add('tish-reveal-pending');
            revealObserver.observe(card);
        }
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        let index = 0;
        if (root.matches?.('.product, .tish-addon-card')) prepareCard(root, index++);
        root.querySelectorAll('.product, .tish-addon-card').forEach(card => prepareCard(card, index++));
    }

    function start() {
        scan(document);
        if (!('MutationObserver' in window)) return;
        const observer = new MutationObserver(records => {
            records.forEach(record => {
                record.addedNodes.forEach(node => {
                    if (node.nodeType === 1) scan(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) document.querySelectorAll('.tish-reveal-pending').forEach(card => {
                const bounds = card.getBoundingClientRect();
                if (bounds.top < window.innerHeight && bounds.bottom > 0) reveal(card);
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();

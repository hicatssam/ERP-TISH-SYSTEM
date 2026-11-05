/* Progressive image placeholders and scroll reveals for Tish's public catalog.
 * All animations are progressive: content remains visible without JavaScript. */
(function () {
    'use strict';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const canReveal = !reduced.matches && 'IntersectionObserver' in window;
    let revealObserver = null;
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
        if (!frame || !img.isConnected) return;
        frame.classList.remove('tish-image-loading');
        frame.classList.add('tish-image-settled');
        frame.classList.toggle('tish-image-error', !img.naturalWidth);
    }

    function refreshImage(img, frame) {
        if (!frame) return;
        frame.classList.remove('tish-image-settled', 'tish-image-error');
        if (img.complete) {
            finalizeImage(img, frame);
        } else {
            frame.classList.add('tish-image-loading');
        }
    }

    function prepareImage(img, frame) {
        if (!frame) return;
        if (!img.dataset.tishImageBound) {
            img.dataset.tishImageBound = '1';
            img.decoding = 'async';
            if (!frame.classList.contains('detail-media') && !img.hasAttribute('loading')) img.loading = 'lazy';
            // Persistent handlers also cover a variant switching on the same image.
            img.addEventListener('load', () => finalizeImage(img, frame));
            img.addEventListener('error', () => finalizeImage(img, frame));
        }
        refreshImage(img, frame);
    }

    document.addEventListener('tish:product-image-changed', event => {
        const img = event.detail?.image;
        if (img instanceof HTMLImageElement) prepareImage(img, img.closest('.detail-media'));
    });

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
        const detailImage = root.matches?.('.detail-media img') ? root : null;
        if (detailImage) prepareImage(detailImage, detailImage.closest('.detail-media'));
        root.querySelectorAll('.detail-media img').forEach(img => prepareImage(img, img.closest('.detail-media')));
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

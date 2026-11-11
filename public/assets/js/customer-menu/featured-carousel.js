/* Standalone featured-carousel behavior; reuses customer-menu product cards and cart handlers. */
(function () {
    'use strict';

    const root = document.getElementById('featuredShowcase') || document.getElementById('relatedShowcase');
    if (!root) return;

    const stage = root.querySelector('#featuredGrid');
    const prev = root.querySelector('[data-showcase-prev]');
    const next = root.querySelector('[data-showcase-next]');
    const dots = root.querySelector('#featuredDots');
    const pause = root.querySelector('[data-showcase-pause]');
    const autoplayMs = Number(root.dataset.autoplayMs) || 6000;
    let manuallyPaused = false;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let slides = [];
    let index = 0;
    let timer = null;
    let hovering = false;
    let focusWithin = false;
    let touchStartX = null;
    let inView = !('IntersectionObserver' in window);
    const folds = new WeakMap();

    function shouldPause() {
        return manuallyPaused || reduced.matches || document.hidden || !inView || hovering || focusWithin || touchStartX !== null ||
            root.closest('[hidden]') !== null || slides.length < 2;
    }

    function schedule() {
        if (pause) pause.hidden = slides.length < 2 || reduced.matches;
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
        if (!shouldPause()) {
            timer = window.setInterval(function () {
                if (!shouldPause()) show(index + 1);
            }, autoplayMs);
        }
    }

    function fold(slide, keyframes) {
        const card = slide.querySelector('.tish-circle-card, .tish-featured-card, .product');
        if (!card?.animate) return;
        folds.get(card)?.cancel();
        folds.set(card, card.animate(keyframes, {duration: 320, easing: 'cubic-bezier(.22,1,.36,1)'}));
    }

    function show(position, animate = true) {
        if (!slides.length) return;
        const previous = index;
        index = (position % slides.length + slides.length) % slides.length;
        if (animate && !reduced.matches && previous !== index) {
            fold(slides[previous], [{scale: '1'}, {scale: '.76 .94', offset: .32}, {scale: '1'}]);
            fold(slides[index], [{scale: '.74 .93'}, {scale: '1.025 1', offset: .72}, {scale: '1'}]);
        }
        slides.forEach(function (slide, i) {
            let distance = i - index;
            if (distance > slides.length / 2) distance -= slides.length;
            if (distance < -slides.length / 2) distance += slides.length;
            if (slides.length === 2 && distance === -1 && i > index) distance = 1;
            const offset = Math.abs(distance) > 2 ? 'outside' : String(distance);
            slide.dataset.offset = offset;
            const active = i === index;
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            slide.querySelectorAll('a, button, input, [tabindex]').forEach(function (el) {
                if (active) el.removeAttribute('tabindex');
                else el.setAttribute('tabindex', '-1');
            });
        });
        Array.from(dots.children).forEach(function (dot, i) {
            dot.setAttribute('aria-current', i === index ? 'true' : 'false');
        });
    }

    function refresh() {
        slides = Array.from(stage.querySelectorAll('[data-showcase-slide]'));
        const enabled = slides.length >= 2;
        root.dataset.showcaseReady = 'true';
        prev.hidden = !enabled;
        next.hidden = !enabled;
        dots.hidden = !enabled;
        if (index >= slides.length) index = 0;
        dots.innerHTML = slides.map(function (_, i) {
            return '<button class="cm-showcase-dot" type="button" data-showcase-dot="' + i +
                '" aria-label="' + (root.dataset.slideLabel || 'عرض الصنف المميز') + ' ' + (i + 1) + '"></button>';
        }).join('');
        show(index, false);
        schedule();
    }

    pause?.addEventListener('click', function () {
        manuallyPaused = !manuallyPaused;
        pause.setAttribute('aria-pressed', String(manuallyPaused));
        pause.querySelector('i').className = manuallyPaused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
        schedule();
    });

    root.addEventListener('click', function (event) {
        const previous = event.target.closest('[data-showcase-prev]');
        const following = event.target.closest('[data-showcase-next]');
        const dot = event.target.closest('[data-showcase-dot]');
        if (previous) show(index - 1);
        else if (following) show(index + 1);
        else if (dot) show(Number(dot.dataset.showcaseDot));
        else return;
        schedule();
    });

    root.addEventListener('keydown', function (event) {
        if (event.target.matches('input, textarea, select')) return;
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            show(index - 1);
            schedule();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            show(index + 1);
            schedule();
        }
    });
    root.addEventListener('mouseenter', function () { hovering = true; schedule(); });
    root.addEventListener('mouseleave', function () { hovering = false; schedule(); });
    root.addEventListener('focusin', function () { focusWithin = true; schedule(); });
    root.addEventListener('focusout', function () {
        window.setTimeout(function () {
            focusWithin = root.contains(document.activeElement);
            schedule();
        }, 0);
    });
    stage.addEventListener('touchstart', function (event) {
        touchStartX = event.touches.length === 1 ? event.touches[0].clientX : null;
        schedule();
    }, { passive: true });
    stage.addEventListener('touchend', function (event) {
        if (touchStartX !== null && event.changedTouches.length === 1) {
            const delta = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(delta) >= 42) show(index + (delta < 0 ? 1 : -1));
        }
        touchStartX = null;
        schedule();
    }, { passive: true });
    stage.addEventListener('touchcancel', function () {
        touchStartX = null;
        schedule();
    }, { passive: true });
    if ('IntersectionObserver' in window) {
        new window.IntersectionObserver(function ([entry]) {
            inView = entry.isIntersecting;
            schedule();
        }, {threshold: .2}).observe(stage);
    }
    document.addEventListener('visibilitychange', schedule);
    if (typeof reduced.addEventListener === 'function') reduced.addEventListener('change', schedule);
    else if (typeof reduced.addListener === 'function') reduced.addListener(schedule);

    if (root.id === 'relatedShowcase') refresh();
    else window.CustomerMenuFeaturedCarousel = { refresh: refresh };
})();

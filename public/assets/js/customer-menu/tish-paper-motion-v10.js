/* Paper Motion for public TISH product circles. No dependencies. */
(function (window, document) {
    'use strict';
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    const noMotion = () => !!(reduced && reduced.matches);
    const enabled = 'IntersectionObserver' in window && !noMotion();
    const seenIds = new Set();
    let observer = null;
    const elements = new WeakSet();
    const categoryFolds = new WeakMap();

    function finish(card) {
        card.classList.add('paper-motion-visible', 'paper-motion-done');
        card.classList.remove('paper-motion-pending');
    }
    function getObserver() {
        if (!enabled) return null;
        if (observer) return observer;
        observer = new window.IntersectionObserver(function (entries) {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const card = entry.target;
                observer.unobserve(card);
                const key = String(card.dataset.id || '');
                if (key) seenIds.add(key);
                card.classList.add('paper-motion-visible');
                // Allow the staggered CTA to finish its own animation.
                const delay = Number(card.dataset.paperDelay || 0);
                window.setTimeout(function () { finish(card); }, 180 + delay);
            }
        }, { threshold: .15, rootMargin: '0px 0px 45px 0px' });
        return observer;
    }
    function observe(root) {
        if (!root) return;
        const cards = root.querySelectorAll('.tish-circle-card');
        if (!enabled) {
            for (const card of cards) finish(card);
            return;
        }
        document.body.classList.add('paper-motion-enabled');
        const io = getObserver();
        for (let i=0; i<cards.length; i++) {
            const card=cards[i];
            if (elements.has(card)) continue;
            elements.add(card);
            if (seenIds.has(String(card.dataset.id))) {finish(card);continue;}
            card.dataset.paperDelay = String((i%4)*50);
            card.style.setProperty('--paper-delay',card.dataset.paperDelay+'ms');
            card.classList.add('paper-motion-pending');
            io.observe(card);
        }
    }
    function syncFavorites(id, active) {
        const escaped=String(id);
        document.querySelectorAll('.tish-circle-favorite[data-fav]').forEach(function (button) {
            if (String(button.dataset.fav) !== escaped) return;
            button.classList.toggle('active', !!active);
            button.setAttribute('aria-pressed', String(!!active));
            const icon=button.querySelector('i');
            if (icon) icon.className=(active?'fa-solid':'fa-regular')+' fa-heart';
            button.setAttribute('aria-label',(active?'إزالة من المفضلة':'إضافة للمفضلة')+' '+
                (button.closest('.tish-circle-card')?.querySelector('.tish-circle-name')?.textContent||''));
            if (!noMotion()) {
                button.classList.remove('paper-heart-pop','paper-heart-out');
                // Restart only from the actual interaction, never during scroll.
                void button.offsetWidth;
                button.classList.add(active?'paper-heart-pop':'paper-heart-out');
                button.addEventListener('animationend',function cleanup(e){
                    if (e.target!==button) return;
                    button.classList.remove('paper-heart-pop','paper-heart-out');
                    button.removeEventListener('animationend',cleanup);
                });
            }
        });
    }
    function categories(root, selected) {
        if (!enabled || !root) return;
        const target = selected || root.querySelector('.cat.active');
        if (!target) return;
        let indicator = root.querySelector('.paper-category-indicator');
        if (!indicator) {
            indicator = document.createElement('span');
            indicator.className='paper-category-indicator';
            indicator.setAttribute('aria-hidden','true');
            root.appendChild(indicator);
            categoryFolds.set(indicator, {target});
            // Initial position is written before enabling transitions.
            indicator.style.transition='none';
            root.classList.add('paper-category-motion');
            setPosition(indicator,target);
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function(){indicator.style.transition='';});
            });
        } else {
            const previous = categoryFolds.get(indicator);
            if (!noMotion() && previous?.target !== target && indicator.animate) {
                previous?.animation?.cancel();
                const animation = indicator.animate(
                    [{scale: '1'}, {scale: '.68 .85', offset: .36}, {scale: '1'}],
                    {duration: 250, easing: 'ease-out'}
                );
                categoryFolds.set(indicator, {target, animation});
            }
            setPosition(indicator,target);
        }
    }
    function setPosition(indicator, button) {
        indicator.style.setProperty('--paper-category-left',button.offsetLeft+'px');
        indicator.style.width=button.offsetWidth+'px';
        indicator.style.height=button.offsetHeight+'px';
        indicator.style.top=button.offsetTop+'px';
    }
    if (reduced && typeof reduced.addEventListener==='function') {
        reduced.addEventListener('change',function () {
            if (noMotion()) {
                document.body.classList.remove('paper-motion-enabled');
                document.querySelectorAll('.tish-circle-card').forEach(finish);
            }
        });
    }
    window.TishPaperMotion = {observe:observe, syncFavorites:syncFavorites, categories:categories};
})(window,document);
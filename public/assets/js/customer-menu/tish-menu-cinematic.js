(function () {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const ease = 'cubic-bezier(.22,1,.36,1)';
    let lastProductCard;
    let cartFlight;

    function openBooth(intro) {
        if (reducedMotion.matches || !intro.animate) return null;

        const video = intro.querySelector('video');
        const bounds = intro.getBoundingClientRect();
        const frame = document.createElement('canvas');

        if (video && !video.hidden && video.readyState >= 2 && video.videoWidth && bounds.width && bounds.height) {
            const ratio = Math.min(1, 960 / Math.max(bounds.width, bounds.height));
            const videoBounds = video.getBoundingClientRect();
            const scale = Math.max(videoBounds.width / video.videoWidth, videoBounds.height / video.videoHeight);
            const width = video.videoWidth * scale;
            const height = video.videoHeight * scale;
            frame.width = Math.round(bounds.width * ratio);
            frame.height = Math.round(bounds.height * ratio);
            const context = frame.getContext('2d');
            if (context) {
                context.scale(ratio, ratio);
                try {
                    context.drawImage(video,
                        videoBounds.left - bounds.left + (videoBounds.width - width) / 2,
                        videoBounds.bottom - bounds.top - height, width, height);
                    intro.classList.add('tish-booth-has-frame');
                } catch {
                    // A video frame can disappear during a playback error. Use the paper doors.
                }
            }
        }

        const panels = Array.from(intro.querySelectorAll('.tish-booth-panel'));
        if (intro.classList.contains('tish-booth-has-frame')) {
            const copy = frame.cloneNode();
            copy.getContext('2d').drawImage(frame, 0, 0);
            panels[0].append(frame);
            panels[1].append(copy);
        }

        intro.classList.add('tish-booth-opening');
        intro.inert = true;
        intro.setAttribute('aria-hidden', 'true');

        moveBrandToHeader(bounds);
        return Promise.all(panels.map((panel, index) => panel.animate([
            { transform: 'rotateY(0) translateX(0)', opacity: 1 },
            { transform: index ? 'rotateY(-74deg) translateX(18%)' : 'rotateY(74deg) translateX(-18%)', opacity: 0 }
        ], { duration: 620, easing: ease, fill: 'both' }).finished)).catch(() => {});
    }

    function moveBrandToHeader(bounds) {
        const logo = document.querySelector('.reference-client-logo');
        if (!logo?.complete || !logo.naturalWidth) return;

        const target = logo.getBoundingClientRect();
        if (!target.width || target.bottom <= 0 || target.top >= window.innerHeight) return;

        const mark = logo.cloneNode();
        mark.className = 'tish-booth-logo';
        mark.setAttribute('aria-hidden', 'true');
        Object.assign(mark.style, {
            left: target.left + 'px',
            top: target.top + 'px',
            width: target.width + 'px',
            height: target.height + 'px'
        });
        document.body.append(mark);
        const dx = bounds.left + bounds.width / 2 - target.left - target.width / 2;
        const dy = bounds.top + bounds.height * .41 - target.top - target.height / 2;
        const arrival = mark.animate([
            { opacity: 0, transform: 'translate(' + dx + 'px,' + dy + 'px) scale(1.55) rotate(-9deg)' },
            { opacity: 1, offset: .24 },
            { opacity: 1, transform: 'none' }
        ], { duration: 580, easing: ease, fill: 'both' });
        arrival.finished.then(() => mark.remove(), () => mark.remove());
    }

    function revealFirstCard(section) {
        if (!section || reducedMotion.matches || !section.animate || !('IntersectionObserver' in window)) return;
        const observer = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) return;
            observer.disconnect();
            if (reducedMotion.matches) return;

            const card = section.querySelector('[data-offset="0"] .tish-circle-card')
                || section.querySelector('#productGrid .tish-circle-card');
            if (!card) return;

            card.querySelector('.tish-circle-paper')?.animate([
                { transform: 'perspective(650px) rotateX(56deg) scale(.82,.36)', opacity: .45 },
                { transform: 'perspective(650px) rotateX(-6deg) scale(1.035)', offset: .76 },
                { transform: 'none', opacity: 1 }
            ], { duration: 660, easing: ease });
            card.querySelector('.tish-circle-photo')?.animate([
                { translate: '0 5px', scale: '.92', opacity: .8 },
                { translate: '0 -14px', scale: '1.14', opacity: 1, offset: .52 },
                { translate: '0', scale: '1', opacity: 1 }
            ], { duration: 850, easing: ease });
        }, { rootMargin: '0px 0px -20% 0px' });
        observer.observe(section);
    }

    function flyToCart(image, button) {
        if (reducedMotion.matches || !button.animate) return;
        const target = document.querySelector('.tish-paper-cart-nav');
        if (!target) return;

        const sourceBounds = image?.complete && image.naturalWidth ? image.getBoundingClientRect() : null;
        const buttonBounds = button.getBoundingClientRect();
        const source = sourceBounds?.width && sourceBounds.bottom > 0 && sourceBounds.top < window.innerHeight
            ? sourceBounds : buttonBounds;
        const icon = target.querySelector('.tish-paper-cart-icon-disc');
        const iconBounds = icon?.getBoundingClientRect();
        const destination = iconBounds?.width ? iconBounds : target.getBoundingClientRect();
        if (!destination.width || !source.width) return;

        cartFlight?.cancel();
        const token = document.createElement('span');
        token.className = 'tish-cart-flight';
        token.setAttribute('aria-hidden', 'true');
        if (source === sourceBounds) {
            const photo = new Image();
            photo.src = image.currentSrc || image.src;
            photo.alt = '';
            token.append(photo);
        } else {
            token.textContent = '+';
        }

        const x = source.left + source.width / 2;
        const y = source.top + source.height / 2;
        const dx = destination.left + destination.width / 2 - x;
        const dy = destination.top + destination.height / 2 - y;
        token.style.left = x + 'px';
        token.style.top = y + 'px';
        document.body.append(token);

        const position = (horizontal, vertical) => `calc(-50% + ${horizontal}px) calc(-50% + ${vertical}px)`;
        const arc = Math.min(56, window.innerWidth * .15) * (x < window.innerWidth / 2 ? 1 : -1);
        const flight = token.animate([
            { translate: position(0, 0), rotate: '-8deg', scale: '1' },
            { translate: position(dx * .12 + arc * .62, dy * .08 - 16), rotate: '4deg', scale: '1.08', offset: .22 },
            { translate: position(dx * .56 + arc, dy * .46), rotate: '12deg', scale: '.72', offset: .6 },
            { translate: position(dx, dy), rotate: '0deg', scale: '.24', opacity: 0 }
        ], { duration: 720, easing: 'cubic-bezier(.3,.12,.4,1)' });
        cartFlight = flight;
        flight.finished.then(() => {
            if (reducedMotion.matches) return;
            icon?.animate([
                { scale: '1' }, { scale: '1.28', offset: .38 }, { scale: '1' }
            ], { duration: 320, easing: ease });
            target.querySelector('.nav-badge')?.animate([
                { scale: '1' }, { scale: '1.4', offset: .35 }, { scale: '1' }
            ], { duration: 320, easing: ease });
        }).catch(() => {}).finally(() => token.remove());
    }

    function productPhoto(url) {
        if (!url) return null;
        const matches = card => card.querySelector('.tish-circle-image')?.href === url;
        const visible = card => {
            const bounds = card.getBoundingClientRect();
            return !card.closest('[hidden], [inert]') && bounds.width && bounds.bottom > 0 && bounds.top < window.innerHeight;
        };
        const card = lastProductCard?.isConnected && matches(lastProductCard) && visible(lastProductCard)
            ? lastProductCard : Array.from(document.querySelectorAll('.tish-circle-card')).find(item => matches(item) && visible(item));
        return card?.querySelector('.tish-circle-photo');
    }

    document.addEventListener('click', event => {
        const card = event.target.closest('.tish-circle-card');
        if (card) lastProductCard = card;
    }, true);

    function setTransitionPhoto(event, url) {
        if (!event.viewTransition) return;
        const photo = document.body?.classList.contains('tish-product-page')
            ? document.querySelector('#detailMedia img') : productPhoto(url);
        if (reducedMotion.matches || !photo?.complete || !photo.naturalWidth) {
            event.viewTransition.skipTransition();
            return;
        }
        photo.style.viewTransitionName = 'tish-product-photo';
        const reset = () => photo.style.removeProperty('view-transition-name');
        event.viewTransition.finished.then(reset, reset);
    }

    window.addEventListener('pageswap', event => setTransitionPhoto(event, event.activation?.entry?.url));
    window.addEventListener('pagereveal', event => setTransitionPhoto(event, window.navigation?.activation?.from?.url));
    window.TishMenuCinematic = { openBooth, revealFirstCard, flyToCart };
})();

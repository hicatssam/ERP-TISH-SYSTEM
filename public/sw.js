const CACHE_NAME = 'dahab-pwa-shell-v6';
const CUTOUT_CACHE = 'tish-versioned-cutouts-v1';
const CUTOUT_LIMIT = 160;

function isVersionedCutout(request, url) {
    return request.destination === 'image' && !url.search
        && !request.headers.has('Range')
        && /^\/menu-assets\/menu-cutouts\/[a-f0-9]{40}\.v1-[0-9]+-[0-9]+\/webp$/.test(url.pathname);
}

function validCutout(response) {
    return response && response.status === 200
        && !response.redirected
        && /^image\/webp(?:\s*;|$)/i.test(response.headers.get('content-type') || '');
}

async function versionedCutout(request) {
    let cache;
    try {
        cache = await caches.open(CUTOUT_CACHE);
        const cached = await cache.match(request);
        if (validCutout(cached)) return cached;
    } catch (_) { /* Storage failure must not block an image. */ }

    const response = await fetch(request);
    if (cache && validCutout(response)) {
        try {
            await cache.put(request, response.clone());
            const keys = await cache.keys();
            await Promise.all(keys.slice(0, Math.max(0, keys.length - CUTOUT_LIMIT))
                .map(key => cache.delete(key)));
        } catch (_) { /* Quota or storage errors are non-fatal. */ }
    }
    return response;
}

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => /^dahab-pwa-shell-v\d+$/.test(name) && name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode === 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/api/')
        || url.pathname.startsWith('/login')
        || url.pathname.startsWith('/logout')
    ) {
        return;
    }

    // Only public, versioned image URLs are cache-first. HTML, stock,
    // meal availability, orders, payment and other requests keep their policy.
    if (isVersionedCutout(request, url)) {
        event.respondWith(versionedCutout(request));
        return;
    }

    const cacheableDestination = [
        'style',
        'script',
        'image',
        'font',
        'manifest'
    ].includes(request.destination);

    const isPwaAsset = url.pathname.startsWith('/pwa/');

    if (! cacheableDestination && ! isPwaAsset) {
        return;
    }

    event.respondWith(
        (async () => {
            const cached = await caches.match(request);

            // Fetch current assets first; cached responses are the offline fallback.
            // Clone immediately (before the
            // response body can be read anywhere else), cache the clone,
            // and return the original. Any caching failure is swallowed
            // so it never breaks the actual page/image/font load.
            try {
                const response = await fetch(request, { cache: 'no-cache' });

                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => cache.put(request, copy))
                        .catch(() => {});
                }

                return response;
            } catch (error) {
                return cached || Response.error();
            }
        })()
    );
});


/* Real background Web Push, separate from page polling. */
self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let data = {};
        try { data = event.data ? event.data.json() : {}; } catch (_) {}
        const target = new URL(String(data.url || '/menu/'), self.location.origin);
        if (target.origin !== self.location.origin || !target.pathname.startsWith('/menu/track/')) {
            target.pathname = '/menu/';
            target.search = '';
        }
        await self.registration.showNotification(String(data.title || 'TISH — تحديث الطلب').slice(0, 100), {
            body: String(data.body || 'تم تحديث حالة طلبك.').slice(0, 240),
            icon: '/assets/tish-paper/pwa/apple-touch-icon-180.png',
            badge: '/assets/tish-paper/pwa/apple-touch-icon-180.png',
            tag: String(data.tag || 'tish-status'),
            renotify: true,
            data: {url: target.href},
        });
    })());
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const url = new URL(String(event.notification.data?.url || '/menu/'), self.location.origin);
        if (url.origin !== self.location.origin) return;
        const windows = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
        for (const windowClient of windows) {
            if (windowClient.url === url.href && 'focus' in windowClient) {
                return windowClient.focus();
            }
        }
        return self.clients.openWindow(url.href);
    })());
});
